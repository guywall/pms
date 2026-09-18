# Deployment Guide — pms.threewalls.co.uk (Plesk Obsidian, AlmaLinux 8)

Step-by-step deployment to the existing Plesk server
(8-core EPYC, 16 GB RAM — ample for this app). No Docker, Node build or
Redis is required; only PHP 8.3 + MySQL + two cron tasks.

Total time: roughly 30–45 minutes.

---

## 0. Create the Git repo (your machine)

From `C:\Users\guy\Documents\PrintAbility\pms`:

```bash
git init
git add .
git commit -m "Initial PrintAbility application"
```

Create the empty repo on GitHub/GitLab/Bitbucket (Private), then:

```bash
git remote add origin git@github.com:<you>/printability.git
git branch -M main
git push -u origin main
```

> **Checked:** `.env` and `database/database.sqlite` (your local demo data)
> are git-ignored, so no secrets or junk go to the repo. `composer.lock` IS
> committed — always deploy with the exact locked versions.

If Plesk will pull from a **private** repo, add a *deploy key*:
generate on the server (`ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -N ""`),
copy `~/.ssh/id_ed25519.pub` into the repo host's *Deploy keys* settings.

---

## 1. Server prerequisites (one-off, via SSH or Plesk UI)

1. **PHP 8.3** — Plesk → *Tools & Settings* → *PHP Settings* → *Handle
   versions of PHP*. If 8.3 is missing, install via SSH:

   ```bash
   sudo dnf install plesk-php83-release
   sudo dnf install plesk-php83-php-{mysqlnd,mbstring,opcache,intl,gd,zip,exif,process}
   ```

   Verify every extension we need shows *on* for 8.3:
   `mysqlnd/pdo_mysql, mbstring, openssl, curl, fileinfo, gd, zip, intl, exif`.

2. **Composer** — Plesk → *Applications* → *Composer* (register an
   installation), or via SSH:

   ```bash
   sudo dnf install composer   # or follow getcomposer.org
   composer --version
   ```

3. **Git** — Plesk → *Extensions* → install **Git** (free).

4. **Laravel Toolkit** — Plesk → *Extensions* → install **Laravel Toolkit**
   (free). This gives a per-domain UI for artisan commands, scheduler and
   queue workers.

---

## 2. Database

Plesk → *Databases* → *Add Database*:

- Database name: `pms_threewalls`
- Related site: `pms.threewalls.co.uk`
- User / password: `pms_user` / generate a strong one — **save it**, the .env
  needs it in step 4.

Note the **MySQL server** shown for the database (usually `localhost:3306`).

---

## 3. Clone the code with Plesk Git

Plesk → *Websites & Domains* → `pms.threewalls.co.uk` → **Git**:

1. *Add Repository*
2. Remote: your repo URL; branch `main`
3. **Deployment mode:** *Quick* is fine — we override the actions below.
4. Before saving, set **"Apply 'git push' with the following deployment
   actions"**:

   - ✓ Pull files from repository into the site — set **Target root** to the
     **domain's home directory**
     (`/var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk`)

   ⚠️ The repo root maps to the domain home. Files like `artisan` will live at
   `/var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan` and the
   web root will be `.../pms.threewalls.co.uk/httpdocs` (see step 5 — if your
   layout differs, adjust the paths in later steps accordingly).

5. Click *OK*, then *Pull* / *Fetch & Deploy* once to get the code on the
   server.

If Plesk Git fights you on docroot/paths, the fallback is plain SSH:

```bash
cd /var/www/vhosts/threewalls.co.uk
git clone git@github.com:<you>/printability.git pms.threewalls.co.uk/app
# then skip step 5 and point docroot at .../pms.threewalls.co.uk/app/public
```

---

## 4. Configure the app (SSH)

All commands run as your subscription's system user
(`ssh <user>@threewalls.co.uk`), NOT as root.

```bash
cd /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk

# Environment
cp .env.example .env
nano .env
```

Edit to:

```env
APP_NAME=PrintAbility
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pms.threewalls.co.uk
APP_TIMEZONE=Europe/London
APP_KEY=            # leave empty, next command fills it

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pms_threewalls
DB_USERNAME=pms_user
DB_PASSWORD=<the password from step 2>

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.example.com      # Brevo/Postmark/etc — set SPF/DKIM!
MAIL_PORT=587
MAIL_USERNAME=<relay user>
MAIL_PASSWORD=<relay password>
MAIL_FROM_ADDRESS=print@threewalls.co.uk
MAIL_FROM_NAME=PrintAbility
```

Then:

```bash
# Right PHP version on PATH (Plesk prefixes plesk binaries)
PHP=/opt/plesk/php/8.3/bin/php
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader

$PHP artisan key:generate --force
$PHP artisan migrate --force
$PHP artisan db:seed --force          # FIRST DEPLOY ONLY
$PHP artisan storage:link             # public storage symlink
```

Permissions (subscription user owns everything; PHP-FPM runs as the same
user on Plesk, so this is normally enough):

```bash
chmod -R ug+rwX storage bootstrap/cache
```

---

## 5. Point the document root at /public

Plesk → *Websites & Domains* → `pms.threewalls.co.uk` → *Hosting Settings*:

- **Document root:** change from `httpdocs` to the app's `public` folder,
  e.g. `pms.threewalls.co.uk/public` (Laravel Toolkit can do this:
  *Laravel* → select `APP_DIR` → it offers to set the docroot).

Verify: `https://pms.threewalls.co.uk` should redirect to
`https://pms.threewalls.co.uk/admin/login` and show the PrintAbility login.

---

## 6. Scheduler + queue worker

Add **two** Plesk Scheduled Tasks (*Websites & Domains* → *Scheduled Tasks* →
*Add Task*, type **Run a command**), both **every minute**, run as the
subscription user:

**Task 1 — scheduler**

```
/opt/plesk/php/8.3/bin/php /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan schedule:run
```

**Task 2 — queue worker**

```
/opt/plesk/php/8.3/bin/php /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan queue:work --stop-when-empty --max-time=55
```

(The worker drains the queue then exits; cron restarts it next minute —
that's the safe Plesk pattern, no supervisor needed.)

Alternatively, *Laravel Toolkit* → the domain → *Scheduler / Queue workers*
toggles do the same thing via UI.

---

## 7. Production caches

```bash
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
```

> Re-run these after every deploy (or use the deploy actions in step 8).

---

## 8. Future updates (every release)

Update **Deployment actions** in the Plesk Git repo settings to:

```bash
/opt/plesk/php/8.3/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
/opt/plesk/php/8.3/bin/php /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan migrate --force
/opt/plesk/php/8.3/bin/php /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan config:cache
/opt/plesk/php/8.3/bin/php /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan route:cache
/opt/plesk/php/8.3/bin/php /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan view:cache
```

Then every `git push origin main` → *Fetch & Deploy* in Plesk = updated site.

Manual alternative over SSH:

```bash
cd /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk
git pull
/opt/plesk/php/8.3/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
/opt/plesk/php/8.3/bin/php artisan migrate --force --force
/opt/plesk/php/8.3/bin/php artisan config:cache && /opt/plesk/php/8.3/bin/php artisan route:cache && /opt/plesk/php/8.3/bin/php artisan view:cache
```

---

## 9. Post-deploy checklist

1. Log in at `/admin` with `admin@example.test` / `password`
   → **change the password immediately** (avatar menu → My account).
2. Delete/replace seed users you don't need (`artworker@`, `store@`,
   `portal@acme.test`, Acme Corp, demo orders) — or leave JOB-2026-00001/2
   as training examples, your call.
3. *Users* → create real staff accounts with roles.
4. *Customers* → add real customers + portal users.
5. *Pipeline stages* → tune names/sequence/gates to the real process
   (defaults: New order → Artwork → Pre-production → Printing → Embroidery →
   Finishing → QC → Packing → Complete).
6. *Stock items* → import opening stock (SKU, name, type, qty, reorder level).
7. Send a test email (any notification) to confirm the SMTP relay + SPF/DKIM.
8. Print a job ticket and a stock label; scan them with the `/scan` page.

---

## 10. Backups & monitoring

- Plesk *Backup Manager*: schedule **daily** backups of the subscription
  (includes DB + `storage/app` artwork) to remote storage (S3/FTP) if possible.
- Application logs: `storage/logs/laravel.log`.
- Queue health: if notifications stop sending, check *Scheduled Tasks* ran
  (task history) and `storage/logs/laravel.log`.

## Troubleshooting

| Symptom | Fix |
|---|---|
| 404 on every page | docroot not pointing at `public` |
| 500 after deploy | `php artisan config:clear`, check `storage/logs/laravel.log`, ensure `.env` has DB creds |
| "Composer not found" in tasks | use full path `/usr/local/bin/composer` or `/usr/bin/composer` |
| Emails not arriving | check relay creds, SPF/DKIM on threewalls.co.uk, `queue:work` cron running |
| Login works then immediately 403/redirect loop | user `is_staff` flag wrong for that panel |
| Slow first page load after deploy | caches not rebuilt — run step 8 cache commands |
