<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout (hosted payment page) for international card payments.
 *
 * Config: secret_key (required), publishable_key, success_url/cancel_url (optional;
 * a per-transaction return URL from the web checkout takes precedence).
 */
class StripeGateway implements PaymentGatewayInterface
{
    private const API = 'https://api.stripe.com/v1';

    /** Stripe charges these currencies in whole units (no minor unit). */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF',
        'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config = []) {}

    public function initiate(Transaction $transaction): array
    {
        $returnUrl = $transaction->metadata['return_url'] ?? null;
        $successUrl = $returnUrl ?? ($this->config['success_url'] ?? url('/subscriptions'));
        $cancelUrl = $transaction->metadata['cancel_url'] ?? ($this->config['cancel_url'] ?? $successUrl);

        $response = $this->client()->asForm()->post(self::API.'/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $transaction->reference,
            'customer_email' => $transaction->user?->email,
            'success_url' => $this->withSessionPlaceholder($successUrl),
            'cancel_url' => $cancelUrl,
            'metadata[reference]' => $transaction->reference,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower((string) $transaction->currency),
            'line_items[0][price_data][unit_amount]' => $this->toMinorUnits((float) $transaction->amount, (string) $transaction->currency),
            'line_items[0][price_data][product_data][name]' => $this->productName($transaction),
        ]);

        if (! $response->successful() || ! $response->json('url')) {
            throw new RuntimeException('Stripe checkout could not be created: '.($response->json('error.message') ?? $response->status()));
        }

        return [
            'success' => true,
            'gateway' => 'stripe',
            'gateway_transaction_id' => (string) $response->json('id'),
            'transaction_reference' => $transaction->reference,
            'checkout_url' => (string) $response->json('url'),
        ];
    }

    public function verify(array $payload): array
    {
        $sessionId = (string) ($payload['gateway_transaction_id'] ?? '');

        if ($sessionId === '') {
            return ['success' => false, 'gateway' => 'stripe', 'status' => 'pending'];
        }

        return $this->status($sessionId);
    }

    public function status(string $gatewayTransactionId): array
    {
        $response = $this->client()->get(self::API.'/checkout/sessions/'.urlencode($gatewayTransactionId));

        if (! $response->successful()) {
            throw new RuntimeException('Stripe session lookup failed: '.($response->json('error.message') ?? $response->status()));
        }

        $currency = strtoupper((string) $response->json('currency'));
        $paymentStatus = (string) $response->json('payment_status');
        $sessionStatus = (string) $response->json('status');

        return [
            'success' => true,
            'gateway' => 'stripe',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => match (true) {
                $paymentStatus === 'paid' => 'successful',
                $sessionStatus === 'expired' => 'failed',
                default => 'pending',
            },
            'amount' => $this->fromMinorUnits((int) $response->json('amount_total'), $currency),
            'currency' => $currency,
            'reference' => (string) ($response->json('client_reference_id') ?? $response->json('metadata.reference') ?? ''),
        ];
    }

    public function refund(Transaction $transaction, array $options = []): array
    {
        $session = $this->client()->get(self::API.'/checkout/sessions/'.urlencode((string) $transaction->gateway_transaction_id));
        $paymentIntent = (string) $session->json('payment_intent');

        if ($paymentIntent === '') {
            throw new RuntimeException('Stripe payment intent not found for refund.');
        }

        $response = $this->client()->asForm()->post(self::API.'/refunds', array_filter([
            'payment_intent' => $paymentIntent,
            'amount' => isset($options['amount'])
                ? $this->toMinorUnits((float) $options['amount'], (string) $transaction->currency)
                : null,
        ]));

        return [
            'success' => $response->successful(),
            'gateway' => 'stripe',
            'refund_status' => $response->successful() ? 'refunded' : 'failed',
            'refund_id' => $response->json('id'),
        ];
    }

    public function supportsAutoRenewal(): bool
    {
        return false;
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $secret = (string) ($this->config['secret_key'] ?? '');

        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return Http::withToken($secret)->acceptJson()->timeout(30);
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    private function fromMinorUnits(int $amount, string $currency): float
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? (float) $amount : $amount / 100;
    }

    private function withSessionPlaceholder(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
    }

    private function productName(Transaction $transaction): string
    {
        $label = $transaction->product_type === 'topup' ? 'Top-up' : 'Subscription';

        return trim(config('app.name').' '.$label.' '.$transaction->reference);
    }
}
