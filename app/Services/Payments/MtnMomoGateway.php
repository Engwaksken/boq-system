<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MtnMomoGateway implements PaymentGatewayInterface
{
    public function __construct(private array $config = []) {}

    public function initiate(Transaction $transaction): array
    {
        $phone = $this->normaliseMsisdn((string) (($transaction->metadata['customer_input']['phone_number'] ?? null) ?: $transaction->user?->phone));
        if ($phone === '') {
            throw new RuntimeException('A mobile money phone number is required.');
        }

        $referenceId = (string) Str::uuid();
        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->required('subscription_key'),
                'X-Target-Environment' => $this->config['target_environment'] ?? 'sandbox',
                'X-Reference-Id' => $referenceId,
                'X-Callback-Url' => $this->config['callback_url'] ?? $this->config['_webhook_url'] ?? url('/api/v1/payment-webhooks/'.($this->config['_gateway_code'] ?? 'mtn_momo')),
                'Content-Type' => 'application/json',
            ])
            ->post($this->baseUrl().'/collection/v1_0/requesttopay', [
                'amount' => (string) $transaction->amount,
                'currency' => $this->config['currency_override'] ?? $transaction->currency,
                'externalId' => $transaction->reference,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $phone,
                ],
                'payerMessage' => $this->config['payer_message'] ?? 'BOQ subscription payment',
                'payeeNote' => $this->config['payee_note'] ?? $transaction->reference,
            ]);

        if ($response->status() !== 202) {
            throw new RuntimeException('MTN MoMo rejected the payment request.');
        }

        return [
            'success' => true,
            'gateway' => 'mtn_momo',
            'provider' => 'MTN MoMo',
            'transaction_reference' => $transaction->reference,
            'gateway_transaction_id' => $referenceId,
            'phone' => $phone,
            'status' => 'pending',
            'instructions' => 'Approve the MTN MoMo payment prompt on your phone, then verify the payment in the app.',
        ];
    }

    public function verify(array $payload): array
    {
        $id = (string) ($payload['gateway_transaction_id'] ?? '');
        if ($id === '') {
            return ['success' => false, 'gateway' => 'mtn_momo', 'status' => 'pending'];
        }

        return $this->status($id);
    }

    public function status(string $gatewayTransactionId): array
    {
        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->required('subscription_key'),
                'X-Target-Environment' => $this->config['target_environment'] ?? 'sandbox',
            ])
            ->get($this->baseUrl().'/collection/v1_0/requesttopay/'.$gatewayTransactionId);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to confirm MTN MoMo payment status.');
        }

        $body = $response->json();
        $providerStatus = strtoupper((string) ($body['status'] ?? 'PENDING'));

        return [
            'success' => true,
            'gateway' => 'mtn_momo',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => match ($providerStatus) {
                'SUCCESSFUL' => 'successful',
                'FAILED', 'REJECTED', 'EXPIRED' => 'failed',
                default => 'pending',
            },
            'provider_status' => $providerStatus,
        ];
    }

    public function refund(Transaction $transaction, array $options = []): array
    {
        return ['success' => false, 'gateway' => 'mtn_momo', 'refund_status' => 'manual_required'];
    }

    public function supportsAutoRenewal(): bool
    {
        return false;
    }

    private function accessToken(): string
    {
        $response = Http::timeout($this->timeout())
            ->withBasicAuth($this->required('api_user'), $this->required('api_key'))
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $this->required('subscription_key')])
            ->post($this->baseUrl().'/collection/token/');

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Unable to authenticate with MTN MoMo.');
        }

        return (string) $response->json('access_token');
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://sandbox.momodeveloper.mtn.com'), '/');
    }

    private function timeout(): int
    {
        return max(5, (int) ($this->config['http_timeout_seconds'] ?? 30));
    }

    private function required(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("MTN MoMo configuration [{$key}] is missing.");
        }
        return $value;
    }

    private function normaliseMsisdn(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '0')) {
            $phone = '256'.substr($phone, 1);
        }
        return $phone;
    }
}
