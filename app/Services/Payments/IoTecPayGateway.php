<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IoTecPayGateway implements PaymentGatewayInterface
{
    public function __construct(private array $config = []) {}

    public function initiate(Transaction $transaction): array
    {
        $method = strtolower((string) ($transaction->payment_method ?: 'mobile_money'));
        $isCard = in_array($method, ['card', 'visa', 'mastercard'], true);
        $payer = $isCard
            ? trim((string) ($transaction->user?->email ?? ''))
            : $this->normaliseMsisdn((string) (($transaction->metadata['customer_input']['phone_number'] ?? null) ?: $transaction->user?->phone));

        if ($payer === '') {
            throw new RuntimeException($isCard
                ? 'An email address is required for ioTec card payments.'
                : 'A mobile money phone number is required for ioTec Pay.');
        }

        $payload = [
            'category' => $isCard ? 'Card' : 'MobileMoney',
            'currency' => strtoupper((string) ($this->config['currency'] ?? $transaction->currency)),
            'walletId' => $this->required('wallet_id'),
            'externalId' => $transaction->reference,
            'payer' => $payer,
            'payerName' => trim((string) ($transaction->user?->name ?? '')) ?: null,
            'payerNote' => (string) ($this->config['payer_note'] ?? 'BOQ subscription payment'),
            'amount' => (float) $transaction->amount,
            'payeeNote' => (string) ($this->config['payee_note'] ?? $transaction->reference),
            'channel' => (string) ($this->config['channel'] ?? 'BOQ'),
            'transactionChargesCategory' => (string) ($this->config['transaction_charges_category'] ?? 'ChargeWallet'),
            'redirectUrl' => $this->redirectUrl(),
        ];

        // Mobile collection can optionally carry a network/channel hint supplied by the app.
        if (! $isCard) {
            $network = trim((string) ($transaction->metadata['customer_input']['network'] ?? ''));
            if ($network !== '') {
                $payload['channel'] = $network;
            }
        }

        $endpoint = $isCard ? '/api/collections/collect/card' : '/api/collections/collect';
        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->post($this->baseUrl().$endpoint, array_filter($payload, static fn ($value) => $value !== null && $value !== ''));

        if (! $response->successful()) {
            throw new RuntimeException('ioTec Pay rejected the payment request.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('ioTec Pay returned an invalid response.');
        }

        $gatewayId = trim((string) ($body['id'] ?? $body['requestId'] ?? $body['transactionId'] ?? ''));
        if ($gatewayId === '') {
            throw new RuntimeException('ioTec Pay did not return a transaction identifier.');
        }

        $providerStatus = (string) ($body['status'] ?? 'Pending');
        $checkoutUrl = trim((string) ($body['cardRedirectUrl'] ?? $body['redirectUrl'] ?? ''));

        return [
            'success' => true,
            'gateway' => 'iotec_pay',
            'provider' => 'ioTec Pay',
            'aggregator' => true,
            'payment_method' => $isCard ? 'card' : 'mobile_money',
            'transaction_reference' => $transaction->reference,
            'gateway_transaction_id' => $gatewayId,
            'phone' => $isCard ? null : $payer,
            'checkout_url' => $checkoutUrl !== '' ? $checkoutUrl : null,
            'status' => $this->mapStatus($providerStatus),
            'provider_status' => $providerStatus,
            'instructions' => $isCard
                ? 'Open the secure ioTec Pay card checkout to complete payment, then return to verify your subscription.'
                : 'Approve the mobile money prompt sent through ioTec Pay, then wait for confirmation.',
        ];
    }

    public function verify(array $payload): array
    {
        $id = trim((string) ($payload['gateway_transaction_id'] ?? ''));
        if ($id !== '') {
            return $this->status($id);
        }

        $reference = trim((string) ($payload['reference'] ?? $payload['externalId'] ?? ''));
        if ($reference !== '') {
            return $this->statusByExternalId($reference);
        }

        return ['success' => false, 'gateway' => 'iotec_pay', 'status' => 'pending'];
    }

    public function status(string $gatewayTransactionId): array
    {
        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->get($this->baseUrl().'/api/collections/status/'.rawurlencode($gatewayTransactionId));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to confirm ioTec Pay payment status.');
        }

        return $this->normaliseStatusResponse($response->json(), $gatewayTransactionId);
    }

    public function refund(Transaction $transaction, array $options = []): array
    {
        // Refund API behaviour can vary by the merchant's ioTec product agreement.
        // Keep refunds controlled by an administrator until a merchant-specific API is configured.
        return [
            'success' => false,
            'gateway' => 'iotec_pay',
            'refund_status' => 'manual_required',
        ];
    }

    public function supportsAutoRenewal(): bool
    {
        return false;
    }

    private function statusByExternalId(string $externalId): array
    {
        $response = Http::timeout($this->timeout())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->get($this->baseUrl().'/api/collections/external-id/'.rawurlencode($externalId));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to confirm ioTec Pay payment status.');
        }

        return $this->normaliseStatusResponse($response->json(), null, $externalId);
    }

    private function normaliseStatusResponse(mixed $body, ?string $fallbackId = null, ?string $externalId = null): array
    {
        if (! is_array($body)) {
            throw new RuntimeException('ioTec Pay returned an invalid status response.');
        }

        $providerStatus = (string) ($body['status'] ?? 'Pending');
        $gatewayId = trim((string) ($body['id'] ?? $fallbackId ?? ''));

        return [
            'success' => true,
            'gateway' => 'iotec_pay',
            'provider' => 'ioTec Pay',
            'aggregator' => true,
            'gateway_transaction_id' => $gatewayId,
            'external_id' => $body['externalId'] ?? $externalId,
            'status' => $this->mapStatus($providerStatus),
            'provider_status' => $providerStatus,
            'provider_status_code' => $body['statusCode'] ?? null,
            'provider_message' => $body['statusMessage'] ?? null,
            'vendor' => $body['vendor'] ?? null,
            'vendor_transaction_id' => $body['vendorTransactionId'] ?? null,
            'amount' => $body['amount'] ?? null,
            'currency' => $body['currency'] ?? null,
        ];
    }

    private function accessToken(): string
    {
        $response = Http::timeout($this->timeout())
            ->asForm()
            ->acceptJson()
            ->post($this->authUrl(), [
                'client_id' => $this->required('client_id'),
                'client_secret' => $this->required('client_secret'),
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Unable to authenticate with ioTec Pay.');
        }

        return (string) $response->json('access_token');
    }

    private function mapStatus(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'SUCCESS', 'SUCCESSFUL', 'COMPLETED' => 'successful',
            'FAILED', 'CANCELLED', 'REJECTED', 'ROLLEDBACK', 'ROLLED_BACK' => 'failed',
            'AWAITINGAPPROVAL', 'AWAITING_APPROVAL' => 'under_review',
            default => 'pending', // Pending and SentToVendor remain non-final.
        };
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://pay.iotec.io'), '/');
    }

    private function authUrl(): string
    {
        return (string) ($this->config['auth_url'] ?? 'https://id.iotec.io/connect/token');
    }

    private function redirectUrl(): ?string
    {
        $url = trim((string) ($this->config['redirect_url'] ?? ''));
        return $url !== '' ? $url : null;
    }

    private function timeout(): int
    {
        return max(5, (int) ($this->config['http_timeout_seconds'] ?? 30));
    }

    private function required(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("ioTec Pay configuration [{$key}] is missing.");
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
