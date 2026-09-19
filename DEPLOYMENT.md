# Deployment Guide — pms.threewalls.co.uk (Plesk Obsidian, AlmaLinux 8)

Step-by-step deployment to the existing Plesk server (8-core EPYC,
16 GB RAM — ample). No Docker, Node build or Redis is required; only
PHP 8.3 + MySQL + two cron tasks.

**The short version:** there is an installer. Push the repo, open a
terminal on the server, run `bash scripts/install.sh`, answer the
prompts, and do the two Plesk steps it prints at the end. Everything
below is that process in detail, with a full manual fallback.

---

## 0. Get the code to the server (GitHub Desktop + Plesk Git)

GitHub Desktop already covers the local side (commit → publish/push).
On the server there are two equally good options:

**Option A — Plesk Git extension (recommended: push-to-deploy)**

1. Plesk → *Websites & Domains* → `pms.threewalls.co.uk` → **Git** →
   *Add Repository*.
2. Remote: your repo URL, branch `main`. For a **private** repo, add a
   deploy key first: SSH in, run `ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -N ""`,
   paste `~/.ssh/id_ed25519.pub` into GitHub → repo → *Deploy keys*.
3. Deployment mode: *Quick*, target = the domain's home directory
   (`/var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk`).
4. *Fetch & Deploy* once. The code now sits in the domain folder.

**Option B — plain SSH**

```bash
ssh <subscription-user>@threewalls.co.uk
cd /var/www/vhosts/threewalls.co.uk
git clone git@github.com:<you>/printability.git pms.threewalls.co.uk
```

> If the domain folder isn't empty (a default `httpdocs` exists), clone
> into a subfolder such as `pms.threewalls.co.uk/app` and use that path
> everywhere below, pointing the docroot at `app/public`.

---

## 1. The installer (does almost everything)

From the domain folder on the server:

```bash
bash scripts/install.sh
```

It will:

1. Locate PHP ≥ 8.2 (Plesk paths first) and **check all required
   extensions** — if any are missing it prints the exact `dnf` command
   and stops.
2. Make sure Composer exists (downloads a project-local
   `composer.phar` if the server has none).
3. Create `.env` and prompt for: the public URL, MySQL host/name/user/
   password (create the DB first — see §2), and your SMTP relay.
4. Run `composer install --no-dev --optimize-autoloader`, generate the
   app key, create the `storage` symlink, fix permissions.
5. Run migrations, then seed **roles + default pipeline stages + one
   admin account** (it prompts for the admin email/password; a strong
   password is pre-generated — just press enter to accept it).
6. Optionally load demo customers/orders (answer **N** on a live
   system — you can add it later with
   `php artisan db:seed --force --class=DemoSeeder`).
7. Build production caches and print the **two remaining Plesk steps**
   (document root + scheduled tasks, see §3).

Re-running the installer is safe: it keeps your existing `.env`, and
re-seeding only adds what's missing (e.g. a second admin account).

### Non-interactive / scripted install

Every prompt has an env-var equivalent:

```bash
SEED_ADMIN_EMAIL=admin@threewalls.co.uk \
SEED_ADMIN_PASSWORD='…' \
bash scripts/install.sh
```

## 2. The database (before the installer)

Plesk → *Databases* → *Add Database*:

- Database name: `pms_threewalls` (or anything you like)
- Related site: `pms.threewalls.co.uk`
- User / password: generate a strong one — the installer asks for it

## 3. The two manual Plesk steps

**Document root** — *Websites & Domains* → `pms.threewalls.co.uk` →
*Hosting Settings* → set **Document root** to the app's `public`
folder, e.g. `pms.threewalls.co.uk/public` (or `…/app/public` if you
cloned into a subfolder).

**Scheduled tasks** — *Websites & Domains* → *Scheduled Tasks* →
*Add Task* → type *Run a command*, both **every minute**, running as
the subscription user:

```
/opt/plesk/php/8.3/bin/php /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan schedule:run
```

```
/opt/plesk/php/8.3/bin/php /var/www/vhosts/threewalls.co.uk/pms.threewalls.co.uk/artisan queue:work --stop-when-empty --max-time=55
```

(The worker drains the queue then exits; cron restarts it next minute —
the safe shared-hosting pattern, no supervisor needed. Laravel
Toolkit's *Scheduler* / *Queue workers* toggles do the same via UI.)

Verify: `https://pms.threewalls.co.uk` lands on the PrintAbility login
at `/admin`.

---

## 4. Future updates

1. If CSS/blade views changed: build the theme locally and commit the
   compiled output (the server has no Node):

   ```bash
   npm install        # first time only
   npm run build
   git add public/build
   ```

2. Commit & push from GitHub Desktop.
3. Deploy the new code: *Fetch & Deploy* in Plesk Git.
4. Run:

```bash
bash scripts/update.sh
```

That does `composer install --no-dev`, `migrate --force` and rebuilds
all caches. To skip step 4 permanently, put exactly those commands into
the repo's **Deployment actions** in the Plesk Git settings:

```
bash scripts/update.sh
```

(Plesk runs deployment actions from the repo root, so the relative
path works. PHP/Composer discovery inside the script is automatic.)

> **Note:** `public/build` IS committed (the compiled Tailwind theme
> lives there and the server has no Node). `node_modules` is not.

## 5. Post-deploy checklist

1. Log in at `/admin` with the admin account you created in the
   installer → change the password if you didn't set one.
2. *Users* → create real staff accounts with roles
   (super_admin, manager, artworker, print/embroidery operator, storekeeper).
3. *Customers* → add real customers + portal users.
4. *Pipeline stages* → tune names/sequence/gates to the real process
   (defaults: New order → Artwork → Pre-production → Printing →
   Embroidery → Finishing → QC → Packing → Complete).
5. *Stock items* → import opening stock (SKU, name, type, qty, reorder level).
6. Send a test email (any notification) to confirm the SMTP relay +
   SPF/DKIM on threewalls.co.uk.
7. Print a job ticket and a stock label; scan them with the `/scan` page.
8. Demo data (if you loaded any): delete Acme Corp / JOB-2026-00001/2 /
   `*@example.test` users before going live.

## 6. Backups & monitoring

- Plesk *Backup Manager*: schedule **daily** backups of the subscription
  (includes DB + `storage/app` artwork) to remote storage if possible.
- Application logs: `storage/logs/laravel.log`.
- Queue health: if notifications stop sending, check *Scheduled Tasks*
  history and `storage/logs/laravel.log`.

---

## Appendix: full manual install (no installer)

Use only if you can't/won't run the script.

1. **PHP 8.3** with `mysqlnd/pdo_mysql, mbstring, openssl, curl,
   fileinfo, gd, zip, intl, exif` — *Tools & Settings → PHP Settings*;
   missing pieces via
   `sudo dnf install plesk-php83-php-{mysqlnd,mbstring,opcache,intl,gd,zip,exif,process}`.
2. **Composer** — *Applications → Composer* or `sudo dnf install composer`.
3. **Git + Laravel Toolkit** extensions (both free).
4. Create `.env` from `.env.example` and set APP_URL, MySQL, SMTP as in
   the installer section.
5. ```bash
   PHP=/opt/plesk/php/8.3/bin/php
   $PHP /usr/local/bin/composer install --no-dev --optimize-autoloader
   $PHP artisan key:generate --force
   $PHP artisan migrate --force
   $PHP artisan db:seed --force        # prompts for the admin account
   $PHP artisan storage:link
   chmod -R ug+rwX storage bootstrap/cache
   $PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
   ```
6. Document root + scheduled tasks: see §3.
7. Update routine: `git pull` → `composer install --no-dev` →
   `migrate --force` → rebuild caches (i.e. what `scripts/update.sh` does).

## Troubleshooting

| Symptom | Fix |
|---|---|
| Installer: "No PHP >= 8.2 found" | Install plesk-php83 (§ Appendix step 1), re-run |
| Installer lists missing extensions | Run the printed `dnf` command as root, re-run installer |
| 404 on every page | docroot not pointing at `public` |
| 500 after deploy | `php artisan config:clear`, check `storage/logs/laravel.log`, ensure `.env` has DB creds |
| "Composer not found" in tasks | installer drops `composer.phar` in the project — the scripts use it automatically |
| Emails not arriving | check relay creds, SPF/DKIM on threewalls.co.uk, queue cron running |
| Login works then 403/redirect loop | user `is_staff` flag wrong for that panel |
| Slow first page after deploy | caches not rebuilt — `bash scripts/update.sh` |
