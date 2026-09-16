# BOQ System Implementation Update — 2026-09-15

Implemented in this package:

- Added Super Admin subscription plan management (`/admin/plans`).
- Added Super Admin user management and role assignment (`/admin/users`).
- Added Super Admin roles/permissions management (`/admin/roles-permissions`).
- Fixed Super Admin revenue calculation to use successful transactions rather than a non-existent subscription `amount` column.
- Fixed subscription administration rendering and schema field mismatches.
- Added subscription suspend, reactivate, cancel and extend actions while preserving BOQ/user data.
- Site Settings trial duration now defaults to 7 days and is used when granting new-user trials.
- Current subscription lookup now excludes subscriptions whose end date has passed.
- Added `subscriptions:expire` command and hourly scheduler entry to expire ended subscriptions and non-permanent entitlements.
- Existing daily hardware price fetch remains scheduled for 06:00 Africa/Kampala and now uses `withoutOverlapping()`.
- Added authenticated payment API flow:
  - `GET /api/v1/payment-gateways`
  - `POST /api/v1/subscriptions/{subscription}/payments`
  - `GET /api/v1/transactions/{transaction}`
  - `POST /api/v1/transactions/{transaction}/verify`
- Successful payment verification activates the subscription through `SubscriptionService` and grants plan entitlements.
- Payment initiation/verification actions are written to `audit_logs`.

## Validation performed

- `php -l` passed for all modified/new PHP files.
- `php artisan route:list --path=api/v1` succeeded and registered the new payment routes.
- `php artisan route:list --path=admin` succeeded and registered all 8 Super Admin routes.
- Full PHPUnit/view-cache validation could not run in the packaging environment because PHP DOM, mbstring and xmlwriter extensions are unavailable there. Run the checks below in the deployment environment.

## Deployment commands

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan route:cache
php artisan view:cache
php artisan schedule:list
php artisan subscriptions:expire
```

Ensure the server cron runs Laravel scheduler every minute:

```cron
* * * * * cd /path/to/boq-system && php artisan schedule:run >> /dev/null 2>&1
```

If queue driver is not `sync`, keep a queue worker running for `FetchDailyHardwarePrices`.

## Provider payment integration update

Added provider-aware payment drivers:
- `mtn_momo` — MTN MoMo Collections RequestToPay + provider status polling.
- `airtel_money` — Airtel Money OAuth collection + provider status polling.
- `flutterwave` — hosted checkout + transaction verification.
- `pesapal` — API 3.0 token, SubmitOrderRequest, and transaction status verification.

Security behaviour:
- Initiation stores provider transaction references.
- User/API verification never directly marks a transaction paid; the driver queries the provider.
- Successful Flutterwave responses are cross-checked against expected amount, currency, and BOQ transaction reference when those values are returned.
- Public callbacks only identify a transaction; the backend re-queries the provider before activation.
- `payments:reconcile --hours=48` runs every five minutes to recover delayed/missed callbacks.
- Legacy generic `stripe` and `mobile_money` adapters no longer auto-confirm payments unless `allow_test_auto_success=true` is explicitly configured in test mode.
- Gateway `config` remains encrypted by the model cast. Super Admin editing masks secret/key/token/password fields as `***stored***`.

### MTN MoMo configuration example
```json
{
  "base_url": "https://sandbox.momodeveloper.mtn.com",
  "subscription_key": "YOUR_COLLECTION_SUBSCRIPTION_KEY",
  "api_user": "YOUR_API_USER",
  "api_key": "YOUR_API_KEY",
  "target_environment": "sandbox",
  "currency_override": "EUR"
}
```
For production, replace sandbox values with the production onboarding values supplied by MTN and use the production transaction currency required for the Uganda collection account.

### Airtel Money configuration example
```json
{
  "base_url": "https://openapiuat.airtel.africa",
  "client_id": "YOUR_CLIENT_ID",
  "client_secret": "YOUR_CLIENT_SECRET",
  "country": "UG",
  "currency": "UGX"
}
```
Use `https://openapi.airtel.africa` after production onboarding.

### Flutterwave configuration example
```json
{
  "base_url": "https://api.flutterwave.com/v3",
  "secret_key": "YOUR_SECRET_KEY",
  "payment_options": "card,mobilemoneyuganda",
  "redirect_url": "https://YOUR_DOMAIN/payment/return"
}
```

### Pesapal configuration example
```json
{
  "base_url": "https://cybqa.pesapal.com/pesapalv3/api",
  "consumer_key": "YOUR_CONSUMER_KEY",
  "consumer_secret": "YOUR_CONSUMER_SECRET",
  "notification_id": "YOUR_REGISTERED_IPN_ID",
  "callback_url": "https://YOUR_DOMAIN/payment/return",
  "country_code": "UG"
}
```
Production API base: `https://pay.pesapal.com/v3/api`.

Recommended server cron remains:
```bash
* * * * * cd /path/to/boq-system && php artisan schedule:run >> /dev/null 2>&1
```

## Payment production hardening pass

Added in the latest pass:

- `PaymentSettlementService` centralises verification results from manual checks, webhooks, and reconciliation.
- Successful transactions are terminal and cannot be downgraded by a late/pending callback.
- Subscription activation is idempotent and will not re-grant/reset entitlement usage on duplicate callbacks.
- Successful payments create exactly one paid invoice/receipt per transaction.
- `transactions.idempotency_key` prevents duplicate payment attempts when the mobile client retries a request.
- `invoices.transaction_id` is unique to prevent duplicate receipts.
- `GET /api/v1/transactions/{transaction}/receipt` returns a safe structured receipt after successful settlement.
- Pending/under-review transactions continue to be reconciled by `payments:reconcile`.

Deploy with:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan route:list
php artisan schedule:list
```

The scheduler should continue to run `payments:reconcile --hours=48` every five minutes and the hardware price job daily at 06:00 Africa/Kampala.
