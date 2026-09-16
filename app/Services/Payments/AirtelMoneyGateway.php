<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AirtelMoneyGateway implements PaymentGatewayInterface
{
    public function __construct(private array $config = []) {}

    public function initiate(Transaction $transaction): array
    {
        $phone = $this->normaliseMsisdn((string) (($transaction->metadata['customer_input']['phone_number'] ?? null) ?: $transaction->user?->phone));
        if ($phone === '') {
            throw new RuntimeException('An Airtel Money phone number is required.');
        }

        $country = strtoupper((string) ($this->config['country'] ?? 'UG'));
        $currency = strtoupper((string) ($this->config['currency'] ?? $transaction->currency));

        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->withHeaders([
                'X-Country' => $country,
                'X-Currency' => $currency,
                'Content-Type' => 'application/json',
            ])
            ->post($this->baseUrl().'/merchant/v1/payments/', [
                'reference' => $transaction->reference,
                'subscriber' => [
                    'country' => $country,
                    'currency' => $currency,
                    'msisdn' => $phone,
                ],
                'transaction' => [
                    'amount' => (float) $transaction->amount,
                    'country' => $country,
                    'currency' => $currency,
                    'id' => $transaction->reference,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Airtel Money rejected the payment request.');
        }

        $body = $response->json();
        $gatewayId = (string) (data_get($body, 'data.transaction.id') ?: data_get($body, 'transaction.id') ?: $transaction->reference);

        return [
            'success' => true,
            'gateway' => 'airtel_money',
            'provider' => 'Airtel Money',
            'transaction_reference' => $transaction->reference,
            'gateway_transaction_id' => $gatewayId,
            'phone' => $phone,
            'status' => 'pending',
            'instructions' => 'Approve the Airtel Money prompt on your phone, then verify the payment in the app.',
        ];
    }

    public function verify(array $payload): array
    {
        $id = (string) ($payload['gateway_transaction_id'] ?? $payload['reference'] ?? '');
        if ($id === '') {
            return ['success' => false, 'gateway' => 'airtel_money', 'status' => 'pending'];
        }
        return $this->status($id);
    }

    public function status(string $gatewayTransactionId): array
    {
        $country = strtoupper((string) ($this->config['country'] ?? 'UG'));
        $currency = strtoupper((string) ($this->config['currency'] ?? 'UGX'));
        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->withHeaders(['X-Country' => $country, 'X-Currency' => $currency])
            ->get($this->baseUrl().'/standard/v1/payments/'.$gatewayTransactionId);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to confirm Airtel Money payment status.');
        }

        $body = $response->json();
        $providerStatus = strtoupper((string) (
            data_get($body, 'data.transaction.status')
            ?: data_get($body, 'transaction.status')
            ?: data_get($body, 'status.message')
            ?: 'PENDING'
        ));

        return [
            'success' => true,
            'gateway' => 'airtel_money',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => match ($providerStatus) {
                'TS', 'SUCCESS', 'SUCCESSFUL', 'COMPLETED' => 'successful',
                'TF', 'FAILED', 'REJECTED', 'EXPIRED' => 'failed',
                default => 'pending',
            },
            'provider_status' => $providerStatus,
        ];
    }

    public function refund(Transaction $transaction, array $options = []): array
    {
        return ['success' => false, 'gateway' => 'airtel_money', 'refund_status' => 'manual_required'];
    }

    public function supportsAutoRenewal(): bool
    {
        return false;
    }

    private function accessToken(): string
    {
        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->post($this->baseUrl().'/auth/oauth2/token', [
                'client_id' => $this->required('client_id'),
                'client_secret' => $this->required('client_secret'),
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Unable to authenticate with Airtel Money.');
        }

        return (string) $response->json('access_token');
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://openapiuat.airtel.africa'), '/');
    }

    private function timeout(): int
    {
        return max(5, (int) ($this->config['http_timeout_seconds'] ?? 30));
    }

    private function required(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));
        if ($value === '') throw new RuntimeException("Airtel Money configuration [{$key}] is missing.");
        return $value;
    }

    private function normaliseMsisdn(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '0')) $phone = '256'.substr($phone, 1);
        return $phone;
    }
}
