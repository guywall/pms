#!/usr/bin/env bash
#
# PrintAbility — one-command server installer
#
#   bash scripts/install.sh
#
# Safe to re-run at any time (it skips anything already configured).
# Run it from the app directory on the server, as your subscription
# (FTP/SSH) user. Root is only needed for installing PHP extensions.
#
# What it does:
#   1. Finds PHP >= 8.2 (Plesk paths first) and checks the extensions
#   2. Makes sure Composer is available (downloads composer.phar if not)
#   3. Creates .env from the template and asks for URL / MySQL / SMTP
#   4. composer install --no-dev, app key, storage link, permissions
#   5. Runs migrations, seeds roles + pipeline, prompts for the admin account
#   6. Builds production caches and prints the final Plesk steps

set -euo pipefail

BLUE='\033[1;34m'; GREEN='\033[0;32m'; YELLOW='\033[0;33m'; RED='\033[0;31m'; OFF='\033[0m'
info() { printf '\n%s==>%s %s\n' "$BLUE" "$OFF" "$*"; }
ok()   { printf '%s ✓%s %s\n' "$GREEN" "$OFF" "$*"; }
warn() { printf '%s !%s %s\n' "$YELLOW" "$OFF" "$*"; }
die()  { printf '%s ✗ %s%s\n' "$RED" "$*" "$OFF" >&2; exit 1; }

[ -f composer.json ] || die "Run this from the app folder:  bash scripts/install.sh"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

INTERACTIVE=false
[ -t 0 ] && INTERACTIVE=true

ask()        { read -r -p "$1 [$2]: " REPLY || true; REPLY="${REPLY:-$2}"; }
ask_secret() { read -r -s -p "$1 (enter = $2): " REPLY || true; echo; REPLY="${REPLY:-$2}"; }
ask_yn()     { read -r -p "$1 [y/N]: " REPLY || true; [[ "${REPLY,,}" == y* ]]; }

# --------------------------------------------------------------- 1. PHP
info "Locating PHP >= 8.2"
PHP_BIN=""
for candidate in /opt/plesk/php/8.3/bin/php /opt/plesk/php/8.2/bin/php /usr/bin/php "$(command -v php 2>/dev/null || true)"; do
    [ -n "$candidate" ] && [ -x "$candidate" ] || continue
    ver="$("$candidate" -r 'echo PHP_VERSION;' 2>/dev/null || true)"
    case "$ver" in
        8.2*|8.3*|8.4*|8.5*) PHP_BIN="$candidate"; break ;;
    esac
done
[ -n "$PHP_BIN" ] || die "No PHP >= 8.2 found. Install plesk-php83 first (see DEPLOYMENT.md §1), then re-run."
ok "PHP $("$PHP_BIN" -r 'echo PHP_VERSION;') at $PHP_BIN"

info "Checking required PHP extensions"
missing=""
for ext in pdo_mysql mbstring openssl curl fileinfo gd zip intl exif; do
    "$PHP_BIN" -m | grep -qi "^${ext}$" || missing="$missing $ext"
done
if [ -n "$missing" ]; then
    warn "Missing extensions:$missing"
    cat <<'HINT'

Enable them, then re-run this script. On Plesk (AlmaLinux), as root:

  sudo dnf install plesk-php83-php-mysqlnd plesk-php83-php-mbstring \
      plesk-php83-php-opcache plesk-php83-php-intl plesk-php83-php-gd \
      plesk-php83-php-zip plesk-php83-php-exif plesk-php83-php-process

HINT
    die "Install the missing extensions, then re-run: bash scripts/install.sh"
fi
ok "All required extensions loaded"

# --------------------------------------------------------------- 2. Composer
info "Checking Composer"
if [ -f composer.phar ]; then
    COMPOSER=("$PHP_BIN" composer.phar)
elif command -v composer >/dev/null 2>&1; then
    COMPOSER=(composer)
else
    warn "Composer not found — downloading composer.phar into the project"
    curl -fsS https://getcomposer.org/installer -o /tmp/composer-setup.php
    expected="$(curl -fsS https://composer.github.io/installer.sha384sum | awk '{print $1}')"
    echo "$expected  /tmp/composer-setup.php" | sha384sum -c - >/dev/null
    "$PHP_BIN" /tmp/composer-setup.php --install-dir="$APP_DIR" --filename=composer.phar --quiet
    rm -f /tmp/composer-setup.php
    COMPOSER=("$PHP_BIN" composer.phar)
fi
ok "Composer ready: ${COMPOSER[*]}"

# --------------------------------------------------------------- 3. .env
esc() { printf '%s' "$1" | sed -e 's/[&|]/\\&/g'; }
set_env() {
    key="$1"; val="$2"
    sed -i "/^# *${key}=/d" .env
    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=\"$(esc "$val")\"|" .env
    else
        printf '%s="%s"\n' "$key" "$val" >> .env
    fi
}

info "Configuring .env"
if [ ! -f .env ]; then
    cp .env.example .env
    ok "Created .env from .env.example"
else
    warn ".env already exists — existing values are kept (re-run with a fresh .env to reconfigure)"
fi

if [ "$INTERACTIVE" = true ]; then
    if grep -q '^APP_URL=.*example\.com' .env; then
        ask "Public URL of the app" "https://pms.threewalls.co.uk"
        set_env APP_URL "$REPLY"
    fi
    set_env APP_NAME PrintAbility
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_TIMEZONE Europe/London

    if ! grep -q '^DB_CONNECTION=mysql' .env; then
        echo
        echo "MySQL — create the database in Plesk → Databases if you haven't already"
        ask "DB host" "127.0.0.1";     set_env DB_HOST "$REPLY"
        ask "DB port" "3306";          set_env DB_PORT "$REPLY"
        ask "DB name" "printability";  set_env DB_DATABASE "$REPLY"
        ask "DB user" "printability";  set_env DB_USERNAME "$REPLY"
        ask_secret "DB password" "";   set_env DB_PASSWORD "$REPLY"
        set_env DB_CONNECTION mysql
    fi

    if grep -q '^MAIL_HOST=smtp-relay.example.com' .env; then
        echo
        echo "Outgoing email (SMTP relay) — leave host as-is to finish this later"
        ask "SMTP host" "smtp-relay.example.com"
        set_env MAIL_HOST "$REPLY"
        ask "SMTP port" "587";                       set_env MAIL_PORT "$REPLY"
        ask "SMTP username" "";                      set_env MAIL_USERNAME "$REPLY"
        ask_secret "SMTP password" "";               set_env MAIL_PASSWORD "$REPLY"
        ask "From address" "print@threewalls.co.uk"; set_env MAIL_FROM_ADDRESS "$REPLY"
        set_env MAIL_FROM_NAME PrintAbility
    fi
else
    warn "Non-interactive run — edit .env manually or re-run in a terminal to be prompted"
fi

# --------------------------------------------------------------- 4. Deps + key
info "Installing dependencies (--no-dev)"
"${COMPOSER[@]}" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

grep -q '^APP_KEY=.\+' .env || "$PHP_BIN" artisan key:generate --force
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
ok "Dependencies, app key, storage link and permissions done"

# --------------------------------------------------------------- 5. Migrate + seed
info "Running database migrations"
"$PHP_BIN" artisan migrate --force

info "Seeding roles, pipeline stages and the admin account"
ADMIN_EMAIL="${SEED_ADMIN_EMAIL:-}"
ADMIN_PASSWORD="${SEED_ADMIN_PASSWORD:-}"
if [ "$INTERACTIVE" = true ]; then
    if [ -z "$ADMIN_EMAIL" ]; then
        ask "Admin email" "admin@threewalls.co.uk"
        ADMIN_EMAIL="$REPLY"
    fi
    if [ -z "$ADMIN_PASSWORD" ]; then
        GEN="$(openssl rand -hex 10 2>/dev/null || "$PHP_BIN" -r 'echo bin2hex(random_bytes(10));')"
        ask_secret "Admin password" "$GEN"
        ADMIN_PASSWORD="$REPLY"
    fi
fi
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.test}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-password}"
SEED_ADMIN_EMAIL="$ADMIN_EMAIL" SEED_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    "$PHP_BIN" artisan db:seed --force
ok "Admin account: $ADMIN_EMAIL (re-running with a different email adds another admin)"

if [ "$INTERACTIVE" = true ] && ask_yn "Load DEMO customers/orders for training? (N for a clean live system)"; then
    "$PHP_BIN" artisan db:seed --force --class=DemoSeeder
fi

# --------------------------------------------------------------- 6. Caches
info "Building production caches"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache || true

APP_URL_FINAL="$(grep '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"')"

cat <<EOF

============================================================
 Installation complete — two manual steps left in Plesk
============================================================

 1) Document root
    Websites & Domains → pms.threewalls.co.uk → Hosting Settings
    → Document root:  pms.threewalls.co.uk/public

 2) Scheduled tasks (Websites & Domains → Scheduled Tasks →
    Add Task → type "Run a command", run every minute):

      $PHP_BIN $APP_DIR/artisan schedule:run

      $PHP_BIN $APP_DIR/artisan queue:work --stop-when-empty --max-time=55

 Then open:  $APP_URL_FINAL/admin
 Login:      $ADMIN_EMAIL

 Handy commands (in $APP_DIR):
   $PHP_BIN artisan config:cache   # after any .env edit
   $PHP_BIN artisan queue:restart  # after code updates
   bash scripts/update.sh          # future deploys
============================================================
EOF
