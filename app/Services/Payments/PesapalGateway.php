<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PesapalGateway implements PaymentGatewayInterface
{
    public function __construct(private array $config = []) {}

    public function initiate(Transaction $transaction): array
    {
        $notificationId = $this->required('notification_id');
        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->post($this->baseUrl().'/Transactions/SubmitOrderRequest', [
                'id' => $transaction->reference,
                'currency' => $transaction->currency,
                'amount' => (float) $transaction->amount,
                'description' => 'BOQ subscription payment',
                'callback_url' => $this->config['callback_url'] ?? url('/'),
                'notification_id' => $notificationId,
                'branch' => $this->config['branch'] ?? 'Online',
                'billing_address' => [
                    'email_address' => $transaction->user?->email,
                    'phone_number' => $transaction->metadata['customer_input']['phone_number'] ?? $transaction->user?->phone,
                    'first_name' => $transaction->user?->name,
                    'country_code' => $this->config['country_code'] ?? 'UG',
                ],
            ]);

        if (! $response->successful() || ! $response->json('redirect_url')) {
            throw new RuntimeException('Pesapal could not create the checkout session.');
        }

        return [
            'success' => true,
            'gateway' => 'pesapal',
            'provider' => 'Pesapal',
            'transaction_reference' => $transaction->reference,
            'gateway_transaction_id' => (string) $response->json('order_tracking_id'),
            'checkout_url' => (string) $response->json('redirect_url'),
            'status' => 'pending',
            'instructions' => 'Open the Pesapal checkout page, complete payment, then return to verify the transaction.',
        ];
    }

    public function verify(array $payload): array
    {
        $id = trim((string) ($payload['gateway_transaction_id'] ?? ''));
        if ($id === '') return ['success' => false, 'gateway' => 'pesapal', 'status' => 'pending'];
        return $this->status($id);
    }

    public function status(string $gatewayTransactionId): array
    {
        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->get($this->baseUrl().'/Transactions/GetTransactionStatus', [
                'orderTrackingId' => $gatewayTransactionId,
            ]);

        if (! $response->successful()) throw new RuntimeException('Unable to confirm Pesapal payment status.');

        $body = $response->json();
        $providerStatus = strtoupper((string) ($body['payment_status_description'] ?? 'PENDING'));
        return [
            'success' => true,
            'gateway' => 'pesapal',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => match ($providerStatus) {
                'COMPLETED', 'SUCCESSFUL', 'SUCCESS' => 'successful',
                'FAILED', 'INVALID', 'CANCELLED', 'CANCELED' => 'failed',
                default => 'pending',
            },
            'provider_status' => $providerStatus,
            'confirmation_code' => $body['confirmation_code'] ?? null,
        ];
    }

    public function refund(Transaction $transaction, array $options = []): array
    {
        return ['success' => false, 'gateway' => 'pesapal', 'refund_status' => 'manual_required'];
    }

    public function supportsAutoRenewal(): bool
    {
        return false;
    }

    private function accessToken(): string
    {
        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->post($this->baseUrl().'/Auth/RequestToken', [
                'consumer_key' => $this->required('consumer_key'),
                'consumer_secret' => $this->required('consumer_secret'),
            ]);
        if (! $response->successful() || ! $response->json('token')) throw new RuntimeException('Unable to authenticate with Pesapal.');
        return (string) $response->json('token');
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://cybqa.pesapal.com/pesapalv3/api'), '/');
    }

    private function timeout(): int { return max(5, (int) ($this->config['http_timeout_seconds'] ?? 30)); }
    private function required(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));
        if ($value === '') throw new RuntimeException("Pesapal configuration [{$key}] is missing.");
        return $value;
    }
}
