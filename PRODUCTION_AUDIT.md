# Production Readiness Audit

**System:** BOQ Works: Laravel 12 / Livewire 4 web app and API (`boq-system`) and Flutter client (`boq_mobile`)
**Date:** 27 September 2026
**Scope:** security, data integrity, payments and subscriptions, operations, product completeness, mobile, and tests. Based on code review, route and schedule listings, and read-only checks against https://boq.kemmytech.com.

## Verdict

**Ready for a paid launch, subject to the checks below.** All P0 findings are resolved:
- Subscriptions are enforced on the web as well as the API.
- Web users can pay through the new checkout, including international cards via Stripe.
- All P1 items are resolved except security headers and error monitoring, which are configuration tasks.

Before launch:
- Enter live gateway credentials (including Stripe keys, if cards are wanted).
- Point an uptime monitor at `/health`.
- Run one real low-value payment end to end on each gateway.

**Status key:** ✅ fixed in this release · ⛔ open, blocks launch · ⚠️ open, fix soon · 💡 improvement

---

## P0: must be resolved before production

| # | Finding | Status |
|---|---------|--------|
| 1 | **Payment replay.** When verifying, a client could send an older or cheaper successful MoMo transaction ID. MTN/Airtel results have no amount or reference, so the replayed payment could activate a new subscription or top-up. | ✅ Verification now always uses the ID stored when the payment was started. A client ID is accepted only if none was stored and no other transaction has used it (`PAYMENT_ALREADY_USED`). Applies to normal and proxy payments. |
| 2 | **Cross-tenant AI pricing job (IDOR).** Any user with `boq.edit` could start a pricing job on another customer's BOQ, read its results and overwrite its rates. | ✅ The request and controller now authorise against the specific BOQ (`BoqPolicy::process`). |
| 3 | **Web app has no paywall.** `entitlement:` middleware exists only on API routes, so web users can upload and price BOQs without a subscription. | ✅ Web BOQ pages require `boq.management` (same as the API); unsubscribed users go to Subscriptions. Checks persist on Livewire requests, and web BOQ generation consumes the import allowance through the shared `EntitlementGate`. |
| 4 | **Web users cannot pay.** The web Subscriptions page creates a pending subscription, but there is no web checkout, and the Top-ups page has no buy buttons. | ✅ Web checkout for plans and top-ups (`/checkout/{plan|topup}/{id}`): mobile money with an approval prompt and status polling, and hosted card pages (Stripe Checkout is now a real integration) that return to the checkout page to confirm. Admins can still activate manual payments. |
| 5 | **Web registration differed from the API.** Users got no role (so 403 on Projects), no trial, no rate limit, and no way to close sign-ups. | ✅ Web sign-up now grants the viewer role and the trial like the API, is limited to 5 attempts per minute, and follows **Settings → Registration & Access → Allow account sign up** (web and API). Email verification is still not required; decide whether it should be. |
| 6 | **Disabled users kept access.** Web login ignored `is_active`, and disabled users kept their API tokens. | ✅ Web login refuses disabled accounts. New `EnsureUserIsActive` middleware signs them out of web sessions and returns `ACCOUNT_DISABLED` on the API. Disabling a user (single or bulk) revokes their API tokens. Biometric login also refuses disabled accounts. |

## P1: fix soon after launch

| # | Finding | Status |
|---|---------|--------|
| 1 | AI pricing endpoints (`price-all`, `price`, `pricing-batches`) have no entitlement or throttle. `priceAll` runs synchronously and resets already-approved rates. | ✅ Entitlement and throttle middleware added; `price-all` skips approved items. It stays synchronous because the mobile app expects the count in the response. |
| 2 | Users with no organisation could read each other's BOQ item price matches (`null === null`). | ✅ The check now uses `BoqPolicy`. |
| 3 | Admin Livewire actions relied only on route middleware, which doesn't run on Livewire updates. | ✅ Every admin component re-checks super-admin on each request (`boot()`). |
| 4 | Grace periods are calculated but never used, and there are no expiry reminders or renewal for expired subscriptions. | ✅ Entitlements last through the grace period. `subscriptions:expire` moves subscriptions to `grace_period`, then expires them, with in-app notices. `subscriptions:remind` sends reminders 7 and 1 days before the end date. Renewal is done by choosing a plan, which goes to checkout. |
| 5 | The **maintenance mode** setting is saved but not enforced. | ✅ Enforced by `EnforceMaintenanceMode`; super admins, sign-in, health checks, mobile config and payment webhooks are exempt. |
| 6 | Deploy script: no `queue:restart`, no database backup before `migrate --force`, and the site comes back up even when a migration fails. | ✅ The deploy script backs up the database, runs `queue:restart`, and stays in maintenance mode if any step fails. |
| 7 | Operations: single log file with no rotation, no error monitoring, and the `/up` health check doesn't test the database or queue. | ✅ Production examples use daily rotating logs (14 days). `/health` checks the database, cache, storage and queue backlog. ⚠️ Still add an error monitor (Sentry or Flare). |
| 8 | Web localisation is not wired: no locale middleware, and views don't use `__()`. | ✅ `SetLocale` middleware (user, then guest choice, then browser, then site default). 775 UI strings are wrapped and listed in `lang/en.json`, and `lang/lg.json` has 221 Luganda translations (the rest fall back to English). Admins translate any language under Settings → Languages → Translate; edits are stored in the database and survive deployments. |
| 9 | Money flows lack tests: verify, webhook, reconcile, expire, proxy subscriptions. | ✅ Added tests for Stripe checkout and return, amount mismatch, checkout authorisation, grace period and expiry, reminders, maintenance mode and health. ⚠️ Mobile money gateway `status()` fakes are still worth adding. |
| 10 | Mobile: most requests have no timeout, 401s aren't handled centrally, and there are about 168 hard casts that crash on null fields. | ✅ 30s timeout on every request; 401 and `ACCOUNT_DISABLED` clear the token and return to sign-in (with tests). ⚠️ Null-safe JSON parsing is still to do. |
| 11 | Android release build signs silently unsigned when `key.properties` is missing. | ✅ Release builds fail unless `android/key.properties` exists. |

## P2: improvements

| # | Finding | Status |
|---|---------|--------|
| 1 | SVG logo and favicon uploads (stored-XSS risk), with extension taken from the client. | ✅ SVG no longer accepted; the extension is detected server-side. |
| 2 | No security headers (HSTS, X-Frame-Options, CSP, nosniff). | 💡 Add them at the web server or in middleware. |
| 3 | `User::$fillable` includes `organisation_id` and `is_active`. | 💡 Remove them and set explicitly (the code already does in most places). |
| 4 | Account deletion hard-deletes the user, orphans projects and cascades away top-up purchase history. | 💡 Soft-delete users and keep financial records. |
| 5 | Dead `app/Console/Kernel.php` whose schedule differs from `routes/console.php`. | ✅ Removed. |
| 6 | Pending transactions older than 48h are never marked abandoned. | 💡 Mark them abandoned in `payments:reconcile`. |
| 7 | "Enable 2FA (Coming Soon)" placeholder. | ✅ Replaced by working biometric (passkey) sign-in. |
| 8 | About 100 hard-coded strings in the mobile app outside the translation files. | 💡 Move them into the ARB files. |

---

## Delivered in this release (feature requests)

- **Password visibility:** an eye toggle on every password field. The login and register fields previously rendered as plain text boxes: the toggle relied on Alpine, which isn't loaded on sign-in pages. Fixed as part of this.
- **Biometric sign-in (WebAuthn passkeys):** Windows Hello, Touch ID and fingerprint. The login button appears only on devices that support it. Users enable it under **Profile → Security**, where they can also remove devices. Password login still works. Needs `php artisan migrate` (adds `webauthn_credentials`).
- **Building categories from the database:** 27 categories with default items (materials, MEP, finishes, external works, plant, PPE, labour). They are seeded idempotently, keep admin edits, and the hard-coded list was removed from the price-fetching service.
- **Currencies and languages:** new Settings tabs to add, edit, deactivate, set default and bulk delete (the default is protected). Every currency field in the system is now a dropdown from this list.
- **Bulk delete** on Plans, Top-ups, Versions, Suppliers, Payment Gateways, Rate Library, Hardware Prices, Projects, Currencies, Languages and Subscriptions. Records still in use are skipped and reported, not cascade-deleted.
- **Admin subscriptions actions:** activate (manual or bank payments), extend by N days, cancel. Each works on a single row or in bulk.
- **Statistics:**
  - The duplicate "Statistics" tab on the admin overview is removed.
  - "Active subscriptions" is counted the same way on every page.
  - Hardware price cards no longer overlap.
  - The overview only queries the open tab, with its own pagination.
- **Legal pages accept HTML:** sanitised (no scripts, event handlers or `javascript:` links). The mobile app receives a plain-text version plus a link to the formatted page.

## Global readiness (follow-up release)

The system was built around Uganda: UGX, Kampala, Africa/Kampala and a Ugandan AI prompt were hard-coded in about 40 places. Region is now configuration, not code.

- **Region settings:** Admin → Settings → General sets the **default country**, **default market location** (used for daily AI price research) and **system timezone** (used by the scheduler), next to default currency and language. `App\Support\Regional` is the single source for these defaults. The fallback currency is `APP_DEFAULT_CURRENCY` (USD), not UGX.
- **Reference data in the database:**
  - `countries`: 200 countries and territories (ISO 3166) with dialling code and currency.
  - `hardware_items`: category items, replacing the JSON list. The JSON list is kept in sync automatically when categories are edited.
  - `currencies` and `languages`: managed in Settings.
- **Price research:**
  - The AI prompt uses the configured location, country and currency.
  - The Ugandan supplier, manufacturer and location lists are removed; suppliers and locations now come from recorded prices and the Suppliers table.
  - Fixed a bug where the daily fetch command always failed: it passed a wrong parameter name to the fetching service.
- **User-level localisation:**
  - The profile timezone offers every IANA zone, grouped by region.
  - Preferences now save language, currency, date format (ISO by default), number format and rows per page. They were previously non-functional placeholders.
  - Phone placeholders use the international format.
  - Project country fields suggest countries from the table.
- **Mobile config:** returns the worldwide country list (default country first), `countries_detailed` (ISO code, dialling code, currency), `default_country` and `default_currency`.
- **Done since:**
  - Web views are translatable (P1 #8).
  - Money, numbers and dates render in each user's format and timezone (`App\Support\Format`, `<x-money>`, `<x-date>`).
  - List pages start at each user's rows-per-page setting.
  - Stripe Checkout gives the web checkout a global card gateway.
- **Remaining:**
  - Complete the Luganda translation, and add further languages as needed (Settings → Languages → Translate).
  - The mobile app still uses its own ARB translation files.

## Deployment steps for this release

```bash
# back up the database first
git fetch origin && git merge --ff-only origin/main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force        # adds tables: webauthn_credentials, currencies, countries, hardware_items, translations; user preference columns
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

Biometric sign-in requires HTTPS on the real domain (already the case). If the site is ever served from a different host name, set `WEBAUTHN_ID` in `.env`.

## What is already solid

- `.env` is not committed, `APP_DEBUG` is off, the deploy script refuses to run with debug on, and `/.env` and `/storage/logs` return 403 live.
- Session cookies are secure, httponly, encrypted and SameSite=lax.
- Webhooks re-query the provider instead of trusting the payload. Settlement is transactional with row locks, and invoices are unique per transaction. Payment initiation supports idempotency keys.
- Scheduled jobs (expire hourly, reconcile every 5 minutes, daily price fetch) are registered with overlap protection.
- API auth endpoints are rate-limited, and forgot-password doesn't reveal whether an account exists.
- Ownership checks on projects, subscriptions, transactions and notifications are consistent.
- No unescaped `{!! !!}` output in views. Uploads are validated by type and size.
- The mobile app stores tokens in secure storage, uses HTTPS by default, and release builds are minified.
