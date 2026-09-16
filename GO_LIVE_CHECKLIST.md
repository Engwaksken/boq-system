# BOQ System Go-Live Checklist

## Backend
- [ ] Production domain resolves and HTTPS is valid.
- [ ] Web root points to Laravel `public/`.
- [ ] `.env.production.example` copied to `.env` and configured privately.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Africa/Kampala`.
- [ ] `php artisan key:generate` completed once for production.
- [ ] MySQL database/user created and configured.
- [ ] SMTP configured and test email delivered.
- [ ] `composer validate --no-check-publish` passes on the deployment server.
- [ ] `composer install --no-dev --prefer-dist --optimize-autoloader` completed.
- [ ] `npm ci && npm run build` completed, or CI-built `public/build` deployed.
- [ ] `php artisan migrate --force` completed.
- [ ] `php artisan storage:link` completed.
- [ ] Scheduler cron runs every minute.
- [ ] Queue worker is running.
- [ ] `php artisan schedule:list` shows hardware fetch, expiry and payment reconciliation.
- [ ] `php artisan queue:failed` has no unexplained failures.

## Payments
- [ ] At least one gateway is configured in sandbox/test mode.
- [ ] Gateway secrets are stored only in encrypted server-side settings.
- [ ] Callback points to `/api/v1/payment-webhooks/{gatewayCode}` over HTTPS.
- [ ] Sandbox payment reaches confirmed state.
- [ ] Subscription activates only after verified payment.
- [ ] Duplicate callback does not duplicate invoice/activation.
- [ ] Receipt endpoint returns the paid invoice.
- [ ] Pending transaction reconciliation works.
- [ ] Gateway switches to live mode only after sandbox acceptance.

## BOQ and AI pricing
- [ ] Project creation works.
- [ ] Excel/PDF upload works.
- [ ] AI pricing fails gracefully if provider quota/API is unavailable.
- [ ] Hardware list/history/comparison/recommendations load.
- [ ] Scheduled hardware fetch succeeds from the queue.
- [ ] Historical prices remain after refresh.

## Access control
- [ ] Super Admin pages are blocked for ordinary users.
- [ ] Plans, subscriptions, users and roles/permissions work for authorised users.
- [ ] Expired trial blocks paid features without deleting BOQ/project data.

## Mobile
- [ ] Private Android signing keystore created and backed up securely.
- [ ] `android/key.properties` configured locally and not committed.
- [ ] `flutter analyze` passes.
- [ ] `flutter test` passes.
- [ ] Release AAB built against production HTTPS API.
- [ ] Registration/login/trial tested on a physical Android device.
- [ ] BOQ upload/hardware pricing tested on device.
- [ ] Direct provider and/or aggregator payment tested on device.
- [ ] Receipt displays after successful payment.

## Backups and rollback
- [ ] Database backup taken immediately before go-live.
- [ ] Uploaded files/storage backed up.
- [ ] Previous release retained.
- [ ] Production `.env`, payment credentials and Android keystore stored securely outside release archive.
