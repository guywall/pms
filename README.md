# PrintAbility — Print & Embroidery Order Management

A production pipeline, artwork approval and stock control platform for print &
embroidery shops. Laravel 12 + Filament 4. Two panels:

- **Staff panel** (`/admin`): dashboard, production board (kanban by stage),
  gated stage workflow, artwork versioning, stock reservations + movement
  ledger, purchase orders, customers & portal users, pipeline stage editor,
  Xero CSV export, job ticket / stock label PDFs with QR codes, `/scan` page.
- **Customer portal** (`/portal`): order progress timeline, artwork approval
  (approve / request changes), file uploads.

## Quick start (local)

```bash
composer install
cp .env.example .env    # .env is already configured for local sqlite
php artisan key:generate --force
php artisan migrate --seed
php artisan serve       # http://127.0.0.1:8000
```

`php artisan migrate --seed` runs `ProductionSeeder` (roles, the default
pipeline stages and one admin account — it prompts for the admin email and
password when run interactively, or use `SEED_ADMIN_EMAIL` /
`SEED_ADMIN_PASSWORD` env vars), plus `DemoSeeder` in local environments.

### Demo logins (from seeder)

| User | Role | Panel |
|---|---|---|
| `admin@example.test` / `password` | Super admin | `/admin` |
| `artworker@example.test` / `password` | Artworker | `/admin` |
| `store@example.test` / `password` | Storekeeper | `/admin` |
| `portal@acme.test` / `password` | Acme Corp buyer | `/portal` |

Demo data includes `JOB-2026-00001` (60 allocated / 40 shortfall — try the
"Raise PO for shortfall" action) and `JOB-2026-00002` in the Artwork stage.

Demo data is **not** seeded on production (`APP_ENV=production`) unless you
ask for it: `php artisan db:seed --force --class=DemoSeeder`.

## Server install (Plesk)

```bash
bash scripts/install.sh   # one command from the app folder on the server
```

Full walkthrough: `DEPLOYMENT.md`. Future updates after a git pull:
`bash scripts/update.sh`.

## How it fits together

- **Pipeline**: `production_stages` rows are fully editable in the admin
  (*Pipeline stages*). Gates per stage: approved artwork / full stock
  allocation / digitised file. The board and stage actions enforce gates.
- **Artwork**: versions per order (`draft → awaiting customer → approved /
  rejected / superseded`). Approving sets `is_final` and supersedes any
  previous final. Portal users approve via `/portal`.
- **Stock**: `stock_items` (garments & consumables) with `qty_on_hand` and
  `qty_reserved`. Reservations are created per order (with backorder support),
  issued at production, and every change lands in the immutable
  `stock_movements` ledger. Low stock drives dashboard stats + notifications.
- **Procurement**: POs raised per shortfall; "Receive goods" increments stock
  and closes the PO when fully received.
- **Numbers**: `JOB-YYYY-#####` and `PO-YYYY-#####` generated race-safely in
  `number_sequences`.
- **Audit**: every order edit and stage move is captured by
  spatie/laravel-activitylog and shown on the order's History tab.

## Front-end theme

The Filament panels use a custom Tailwind theme
(`resources/css/filament/staff/theme.css`) that compiles Filament's own
package CSS **plus** the app's custom views (production board, scan
page). If you edit those views' classes, rebuild and commit:

```bash
npm install && npm run build   # commit public/build afterwards
```

The compiled `public/build` output is committed because the production
server has no Node.

## Testing

```bash
php artisan test
```

18 tests / 49 assertions: stage gates, artwork final-version invariant,
reservation arithmetic & backorders, releases, sequential numbering,
page-render smoke tests for both panels, PDF generation, panel access rules.

## Deployment

See `DEPLOYMENT.md` for the Plesk (AlmaLinux / MySQL / cron) deployment guide.
