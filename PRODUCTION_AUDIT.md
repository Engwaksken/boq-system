# Production Readiness Audit

**System:** BOQ Works: Laravel 12 / Livewire 4 web app and API (`boq-system`) and Flutter client (`boq_mobile`)
**Date:** 27 September 2026
**Scope:** security, data integrity, payments and subscriptions, operations, product completeness, mobile, and tests. Based on code review, route and schedule listings, and read-only checks against https://boq.kemmytech.com.

## Verdict

**Not ready for a paid public launch yet.** The foundations are sound: tenancy checks on core records, webhook handling, secure sessions, and scheduled jobs. The security holes found in this audit are closed. Two product gaps still block charging web customers:

1. **No web paywall.** Subscriptions are only enforced on the API.
2. **No web checkout.** Web users cannot pay; only the mobile app can.

Decide on both before launch. The work is scoped under "Open P0" below.

**Status key:** ✅ fixed in this release · ⛔ open, blocks launch · ⚠️ open, fix soon · 💡 improvement

---

## P0: must be resolved before production

| # | Finding | Status |
|---|---------|--------|
| 1 | **Payment replay.** When verifying, a client could send an older or cheaper successful MoMo transaction ID. MTN/Airtel results have no amount or reference, so the replayed payment could activate a new subscription or top-up. | ✅ Verification now always uses the ID stored when the payment was started. A client ID is accepted only if none was stored and no other transaction has used it (`PAYMENT_ALREADY_USED`). Applies to normal and proxy payments. |
| 2 | **Cross-tenant AI pricing job (IDOR).** Any user with `boq.edit` could start a pricing job on another customer's BOQ, read its results and overwrite its rates. | ✅ The request and controller now authorise against the specific BOQ (`BoqPolicy::process`). |
| 3 | **Web app has no paywall.** `entitlement:` middleware exists only on API routes, so web users can upload and price BOQs without a subscription. | ⛔ **Decision needed:** enforce on the web now, or launch web as free and mobile as paid. Enforcing means adding `entitlement:boq.management` (plus import/pricing features) to the web BOQ routes and checking the same entitlements inside Livewire actions. |
| 4 | **Web users cannot pay.** The web Subscriptions page creates a pending subscription, but there is no web checkout, and the Top-ups page has no buy buttons. | ⛔ Build a web checkout (choose gateway → initiate → poll verify) that reuses `PaymentManager`, or send users to the app. Until then, admins can activate bank or manual payments from **Admin → Subscriptions → Activate** (new). |
| 5 | **Web registration differed from the API.** Users got no role (so 403 on Projects), no trial, no rate limit, and no way to close sign-ups. | ✅ Web sign-up now grants the viewer role and the trial like the API, is limited to 5 attempts per minute, and follows **Settings → Registration & Access → Allow account sign up** (web and API). Email verification is still not required; decide whether it should be. |
| 6 | **Disabled users kept access.** Web login ignored `is_active`, and disabled users kept their API tokens. | ✅ Web login refuses disabled accounts. New `EnsureUserIsActive` middleware signs them out of web sessions and returns `ACCOUNT_DISABLED` on the API. Disabling a user (single or bulk) revokes their API tokens. Biometric login also refuses disabled accounts. |

## P1: fix soon after launch

| # | Finding | Status |
|---|---------|--------|
| 1 | AI pricing endpoints (`price-all`, `price`, `pricing-batches`) have no entitlement or throttle. `priceAll` runs synchronously and resets already-approved rates. | ⚠️ Add entitlement and throttle middleware, send the work to the queued job, and skip approved items. |
| 2 | Users with no organisation could read each other's BOQ item price matches (`null === null`). | ✅ The check now uses `BoqPolicy`. |
| 3 | Admin Livewire actions relied only on route middleware, which doesn't run on Livewire updates. | ✅ Every admin component re-checks super-admin on each request (`boot()`). |
| 4 | Grace periods are calculated but never used, and there are no expiry reminders or renewal for expired subscriptions. | ⚠️ Move to `grace_period` at the end date and expire at the grace end. Send reminders. Admins can now extend or reactivate manually. |
| 5 | The **maintenance mode** setting is saved but not enforced. | ⚠️ Enforce it in middleware (sign-up enforcement is done: P0 #5). |
| 6 | Deploy script: no `queue:restart`, no database backup before `migrate --force`, and the site comes back up even when a migration fails. | ⚠️ Add a backup step and `queue:restart`, and stay in maintenance mode on failure. |
| 7 | Operations: single log file with no rotation, no error monitoring, and the `/up` health check doesn't test the database or queue. | ⚠️ Use the `daily` log channel, add Sentry or Flare, and extend the health check. |
| 8 | Web localisation is not wired: no locale middleware, and views don't use `__()`. | ⚠️ Add a locale middleware (user locale, then browser) and translate the views. Admins can now manage languages in Settings. |
| 9 | Money flows lack tests: verify, webhook, reconcile, expire, proxy subscriptions. | ⚠️ Partly addressed: replay, IDOR, disabled-user and subscription-admin tests added. Gateway `status()` fakes are still needed. |
| 10 | Mobile: most requests have no timeout, 401s aren't handled centrally, and there are about 168 hard casts that crash on null fields. | ⚠️ Add a timeout to every request, clear the token and show login on 401, and make parsing null-safe. |
| 11 | Android release build signs silently unsigned when `key.properties` is missing. | ⚠️ Fail the release build when the keystore is absent. |

## P2: improvements

| # | Finding | Status |
|---|---------|--------|
| 1 | SVG logo and favicon uploads (stored-XSS risk), with extension taken from the client. | ✅ SVG no longer accepted; the extension is detected server-side. |
| 2 | No security headers (HSTS, X-Frame-Options, CSP, nosniff). | 💡 Add them at the web server or in middleware. |
| 3 | `User::$fillable` includes `organisation_id` and `is_active`. | 💡 Remove them and set explicitly (the code already does in most places). |
| 4 | Account deletion hard-deletes the user, orphans projects and cascades away top-up purchase history. | 💡 Soft-delete users and keep financial records. |
| 5 | Dead `app/Console/Kernel.php` whose schedule differs from `routes/console.php`. | 💡 Delete it. |
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

## Deployment steps for this release

```bash
# back up the database first
git fetch origin && git merge --ff-only origin/main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force        # webauthn_credentials, currencies, languages, building categories
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
