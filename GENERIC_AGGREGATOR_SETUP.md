# Generic Payment Aggregator Setup

The BOQ system supports payment aggregators through the `generic_aggregator` driver. This is intended for providers that expose a REST API for collections and status checks but do not yet have a dedicated driver.

## Security model

- API base URLs and OAuth token URLs must use HTTPS.
- Initiation and status endpoints are relative paths under the configured base URL; arbitrary per-request URLs are rejected.
- Credentials remain in the encrypted `payment_gateways.config` field.
- Incoming callbacks never settle a payment directly. The server re-queries the configured status endpoint before activating a subscription.
- Existing amount, currency and reference checks in `PaymentSettlementService` still apply.
- Successful transactions are idempotent and cannot be downgraded by late callbacks.

## Super Admin configuration

Create or edit a gateway under **Admin → Payment Gateways** and choose:

`Aggregator — Generic REST API`

Use a unique gateway code such as `my_aggregator`. Configure supported currencies, countries and methods. For Uganda a common method list is:

`mobile_money, card`

Click **Load driver template** and replace the sample endpoints and credentials with the provider's documented values.

### Authentication types

`auth.type` supports:

- `oauth2_client_credentials`
- `bearer`
- `api_key_header`

For OAuth2 configure `token_url`, `client_id`, `client_secret`, and optionally `token_extra`.

For API-key authentication configure `header` and `value`.

## Endpoint configuration

- `base_url`: provider API origin, HTTPS only.
- `initiate_path`: relative collection/payment creation path.
- `initiate_method`: normally `POST`.
- `status_path`: relative status path containing `{id}`.
- `status_by_reference_path`: optional path containing `{reference}`.
- `redirect_url`: optional app/web return URL.
- `callback_url`: optional callback URL sent to the provider.

## Field mapping

`request_fields` maps BOQ's canonical fields to provider request keys:

- amount
- currency
- reference
- method
- phone
- network
- email
- name
- callback
- redirect

`response_fields` maps provider responses back to canonical values. Each entry can contain several dotted JSON paths, tried in order.

Required for reliable reconciliation:

- `gateway_transaction_id`
- `status`

Strongly recommended:

- `reference`
- `amount`
- `currency`
- `checkout_url`

## Status mapping

Map provider-specific statuses into exactly four BOQ states:

- `successful`
- `failed`
- `under_review`
- `pending`

Do not map an ambiguous provider state to `successful`.

## Mobile-money channel mapping

Use `method_map` and `network_map` when a provider expects different values, for example:

```json
{
  "method_map": {
    "mobile_money": "MOBILE_MONEY",
    "card": "CARD"
  },
  "network_map": {
    "mtn": "MTN_UG",
    "airtel": "AIRTEL_UG"
  }
}
```

`phone_required_methods` controls which methods require the Flutter app to collect a phone number.

## When to build a dedicated driver

Use `generic_aggregator` for straightforward REST collection/status APIs. Build a dedicated driver when the provider requires request signing, asymmetric cryptography, unusual token exchange, webhook signatures, multi-step checkout, recurring mandates, or provider-specific refund/dispute behaviour.
