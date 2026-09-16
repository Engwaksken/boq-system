# BOQ System Production Deployment

## 1. Server requirements

Use PHP 8.2+ with MySQL/MariaDB and the extensions required by Laravel and this project, including: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `xmlwriter`, and `zip`. Composer 2 is required. Node.js/npm is required when assets are built on the server; otherwise build `public/build` in CI and deploy it with the release.

The web server document root must point to the Laravel `public/` directory, not to the repository root.

## 2. Production environment

Copy `.env.production.example` to `.env`, configure the database, mail settings and HTTPS application URL, then run:

```bash
php artisan key:generate
```

Keep `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` on HTTPS. Never commit or distribute `.env`.

Payment/API credentials are managed in **Super Admin > Payment Gateways** and are encrypted by the application. Do not expose them in Flutter or JavaScript.

## 3. First deployment

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Or run:

```bash
./scripts/deploy-production.sh
```

## 4. Scheduler and queue

Add exactly one scheduler cron job:

```cron
* * * * * cd /FULL/PATH/TO/boq-system && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

The scheduler currently handles:

- hardware price fetch every day at 06:00 Africa/Kampala;
- subscription expiry hourly;
- payment reconciliation every five minutes.

Because hardware-price work is queued, run a queue worker continuously. With Supervisor/systemd use:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

On cPanel without Supervisor, create a cron worker that runs frequently and exits safely, for example:

```cron
* * * * * cd /FULL/PATH/TO/boq-system && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=3 --timeout=120 >> /dev/null 2>&1
```

Do not run both a persistent worker and the cPanel `--stop-when-empty` cron for the same queue unless intentionally scaling workers.

## 5. Payment callbacks

Expose the public webhook endpoint over HTTPS:

```text
POST https://YOUR-DOMAIN/api/v1/payment-webhooks/{gatewayCode}
```

Configure each provider/aggregator to use its matching gateway code. A webhook does not directly grant service: the backend re-verifies payment with the provider before settlement.

Before enabling live mode, verify each gateway's test/sandbox credentials, currency, callback URL, return URL and supported payment methods.

## 6. Production verification

Run:

```bash
./scripts/post-deploy-check.sh
```

Then verify manually:

1. Login and registration.
2. New registration receives the configured trial.
3. Project and BOQ creation/upload.
4. Hardware price list/history/comparison/recommendations.
5. Plan selection and pending subscription creation.
6. One sandbox payment from initiation through webhook/reconciliation to active subscription.
7. Receipt generation after successful settlement.
8. Duplicate webhook does not duplicate activation, entitlement usage or invoice.
9. Super Admin permissions prevent unauthorised access.
10. Email delivery and password reset/notifications where enabled.

## 7. Update deployment

Back up the database and uploaded files first. Then deploy the new release and run `./scripts/deploy-production.sh`. Laravel maintenance mode is enabled while dependencies/migrations/caches are updated.

## 8. Rollback

Keep the previous release and a database backup. If application code must be rolled back, restore the previous release first. Only roll back database migrations when the migration is explicitly designed as reversible and data impact is understood. For production incidents, restoring the pre-deployment database backup is safer than blindly running `migrate:rollback`.
