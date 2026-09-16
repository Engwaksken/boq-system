<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwaveGateway implements PaymentGatewayInterface
{
    public function __construct(private array $config = []) {}

    public function initiate(Transaction $transaction): array
    {
        $payload = [
            'tx_ref' => $transaction->reference,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'redirect_url' => $this->config['redirect_url'] ?? url('/'),
            'payment_options' => $this->config['payment_options'] ?? 'card,mobilemoneyuganda',
            'customer' => [
                'email' => $transaction->user?->email,
                'phonenumber' => $transaction->metadata['customer_input']['phone_number'] ?? $transaction->user?->phone,
                'name' => $transaction->user?->name,
            ],
            'customizations' => [
                'title' => $this->config['title'] ?? 'BOQ Subscription',
                'description' => 'Payment for '.$transaction->reference,
            ],
            'meta' => [
                'transaction_id' => $transaction->id,
                'subscription_id' => $transaction->subscription_id,
            ],
        ];

        $response = Http::timeout($this->timeout())
            ->withToken($this->required('secret_key'))
            ->acceptJson()
            ->post($this->baseUrl().'/payments', $payload);

        if (! $response->successful() || ! $response->json('data.link')) {
            throw new RuntimeException('Flutterwave could not create the checkout session.');
        }

        return [
            'success' => true,
            'gateway' => 'flutterwave',
            'provider' => 'Flutterwave',
            'transaction_reference' => $transaction->reference,
            'checkout_url' => (string) $response->json('data.link'),
            'status' => 'pending',
            'instructions' => 'Open the secure checkout page, complete payment, then return to verify the transaction.',
        ];
    }

    public function verify(array $payload): array
    {
        $id = trim((string) ($payload['gateway_transaction_id'] ?? ''));
        if ($id !== '' && ctype_digit($id)) return $this->status($id);

        $reference = trim((string) ($payload['reference'] ?? ''));
        if ($reference === '') return ['success' => false, 'gateway' => 'flutterwave', 'status' => 'pending'];

        $response = Http::timeout($this->timeout())
            ->withToken($this->required('secret_key'))
            ->get($this->baseUrl().'/transactions/verify_by_reference', ['tx_ref' => $reference]);

        return $this->mapVerification($response->successful() ? $response->json() : [], $id ?: null);
    }

    public function status(string $gatewayTransactionId): array
    {
        $response = Http::timeout($this->timeout())
            ->withToken($this->required('secret_key'))
            ->get($this->baseUrl().'/transactions/'.$gatewayTransactionId.'/verify');

        if (! $response->successful()) throw new RuntimeException('Unable to confirm Flutterwave payment status.');
        return $this->mapVerification($response->json(), $gatewayTransactionId);
    }

    public function refund(Transaction $transaction, array $options = []): array
    {
        if (! $transaction->gateway_transaction_id) {
            return ['success' => false, 'gateway' => 'flutterwave', 'refund_status' => 'not_available'];
        }
        $response = Http::timeout($this->timeout())
            ->withToken($this->required('secret_key'))
            ->post($this->baseUrl().'/transactions/'.$transaction->gateway_transaction_id.'/refund', array_filter([
                'amount' => $options['amount'] ?? null,
            ], fn ($value) => $value !== null));

        return [
            'success' => $response->successful(),
            'gateway' => 'flutterwave',
            'refund_status' => $response->successful() ? 'submitted' : 'failed',
        ];
    }

    public function supportsAutoRenewal(): bool
    {
        return false;
    }

    private function mapVerification(array $body, ?string $fallbackId): array
    {
        $data = $body['data'] ?? [];
        $providerStatus = strtolower((string) ($data['status'] ?? $body['status'] ?? 'pending'));
        return [
            'success' => true,
            'gateway' => 'flutterwave',
            'gateway_transaction_id' => (string) ($data['id'] ?? $fallbackId ?? ''),
            'status' => match ($providerStatus) {
                'successful', 'success', 'completed' => 'successful',
                'failed', 'cancelled', 'canceled' => 'failed',
                default => 'pending',
            },
            'provider_status' => $providerStatus,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'tx_ref' => $data['tx_ref'] ?? null,
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://api.flutterwave.com/v3'), '/');
    }

    private function timeout(): int { return max(5, (int) ($this->config['http_timeout_seconds'] ?? 30)); }
    private function required(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));
        if ($value === '') throw new RuntimeException("Flutterwave configuration [{$key}] is missing.");
        return $value;
    }
}
