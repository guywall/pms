#!/usr/bin/env bash
#
# PrintAbility — update an existing install (after git pull / Plesk deploy)
#
#   bash scripts/update.sh
#
# Runs the same steps as the deploy actions in DEPLOYMENT.md §8:
# deps → migrate → caches. Safe to run repeatedly.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

PHP_BIN=""
for candidate in /opt/plesk/php/8.3/bin/php /opt/plesk/php/8.2/bin/php /usr/bin/php "$(command -v php 2>/dev/null || true)"; do
    [ -n "$candidate" ] && [ -x "$candidate" ] || continue
    ver="$("$candidate" -r 'echo PHP_VERSION;' 2>/dev/null || true)"
    case "$ver" in
        8.2*|8.3*|8.4*|8.5*) PHP_BIN="$candidate"; break ;;
    esac
done
[ -n "$PHP_BIN" ] || { echo "No PHP >= 8.2 found" >&2; exit 1; }
# Make the found PHP visible to subprocesses (composer's shebang is
# `#!/usr/bin/env php` — on Plesk bare `php` isn't on the PATH).
PHP_DIR="$(dirname "$PHP_BIN")"
case ":$PATH:" in *":$PHP_DIR:"*) ;; *) export PATH="$PHP_DIR:$PATH" ;; esac

if [ -f composer.phar ]; then
    COMPOSER=("$PHP_BIN" composer.phar)
elif command -v composer >/dev/null 2>&1; then
    COMPOSER=(composer)
else
    echo "Composer not found — run scripts/install.sh once first" >&2
    exit 1
fi

echo "==> PHP: $PHP_BIN"
echo "==> Installing dependencies"
"${COMPOSER[@]}" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Running migrations"
"$PHP_BIN" artisan migrate --force

echo "==> Rebuilding caches"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache || true
"$PHP_BIN" artisan queue:restart || true

echo "==> Update complete"
