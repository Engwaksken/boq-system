# ioTec Pay aggregator setup

The BOQ payment layer supports ioTec Pay as an aggregator while preserving direct MTN MoMo, Airtel Money, Flutterwave, Pesapal, Stripe, bank transfer, and generic mobile-money gateway choices.

## Super Admin configuration

Open **Admin > Payment Gateways**, create a gateway and select **Aggregator — ioTec Pay**.

Recommended values:

- Code: `iotec`
- Driver: `iotec_pay`
- Supported currencies: `UGX, USD`
- Supported countries: `UG`
- Supported methods: `mobile_money, card`
- Base URL: `https://pay.iotec.io`
- Auth URL: `https://id.iotec.io/connect/token`
- Client ID: supplied by ioTec
- Client secret: supplied by ioTec
- Wallet ID: the ioTec Pay collection wallet UUID
- Currency: `UGX`
- Transaction charges category: `ChargeWallet` or the merchant-agreed option
- Channel: `BOQ`
- Redirect URL: an HTTPS page in the BOQ application to which card users may return

The `config` field is encrypted by the existing PaymentGateway model cast. Stored secrets remain masked in the Super Admin editor.

## Callback

Configure the collection callback in the ioTec Pay wallet portal to:

`https://YOUR-DOMAIN/api/v1/payment-webhooks/iotec`

The code in the callback URL must match the gateway `code`, not the driver name. The BOQ webhook never trusts the callback status by itself; it re-verifies the transaction with ioTec Pay before settlement.

If ioTec callback security headers are configured in their portal, keep those credentials private. Provider API re-verification remains the authoritative settlement check in BOQ.

## Flow

1. User selects a BOQ plan.
2. User selects ioTec Pay.
3. For mobile money, BOQ sends a collection request using the user's MSISDN.
4. For card, BOQ starts ioTec card collection and exposes the hosted checkout URL to Flutter.
5. ioTec returns a request ID; BOQ stores it as `gateway_transaction_id`.
6. Flutter polls/requests verification while callbacks may also arrive.
7. BOQ verifies using ioTec's collection status API.
8. Only a verified `Success` result settles the transaction and activates the subscription.
9. Existing idempotent settlement prevents duplicate activation/receipts.

## Extending to another aggregator

Implement `PaymentGatewayInterface`, register the new driver in `PaymentManager`, then add its template/label to `Admin\\PaymentGateways`. The subscription and settlement controllers do not need to be rewritten.
