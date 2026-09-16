<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenericAggregatorGateway implements PaymentGatewayInterface
{
    public function __construct(private array $config = []) {}

    public function initiate(Transaction $transaction): array
    {
        $method = strtolower((string) ($transaction->payment_method ?: 'mobile_money'));
        $customerInput = (array) ($transaction->metadata['customer_input'] ?? []);
        $phone = $this->normaliseMsisdn((string) ($customerInput['phone_number'] ?? $transaction->user?->phone ?? ''));
        $network = strtolower(trim((string) ($customerInput['network'] ?? '')));

        if ($this->methodNeedsPhone($method) && $phone === '') {
            throw new RuntimeException('A mobile money phone number is required for this payment method.');
        }

        $payload = [
            $this->field('amount', 'amount') => (float) $transaction->amount,
            $this->field('currency', 'currency') => strtoupper((string) $transaction->currency),
            $this->field('reference', 'reference') => $transaction->reference,
            $this->field('method', 'payment_method') => $this->mapMethod($method),
            $this->field('phone', 'phone_number') => $phone !== '' ? $phone : null,
            $this->field('network', 'network') => $network !== '' ? $this->mapNetwork($network) : null,
            $this->field('email', 'email') => $transaction->user?->email,
            $this->field('name', 'name') => $transaction->user?->name,
            $this->field('callback', 'callback_url') => $this->config['callback_url'] ?? $this->config['_webhook_url'] ?? null,
            $this->field('redirect', 'redirect_url') => $this->config['redirect_url'] ?? null,
        ];

        $extra = is_array($this->config['initiate_extra'] ?? null) ? $this->config['initiate_extra'] : [];
        $payload = array_merge($payload, $extra);
        $payload = array_filter($payload, static fn ($value) => $value !== null && $value !== '');

        $response = $this->request()->send(
            strtoupper((string) ($this->config['initiate_method'] ?? 'POST')),
            $this->urlForPath((string) ($this->config['initiate_path'] ?? '/payments')),
            ['json' => $payload],
        );

        if (! $response->successful()) {
            throw new RuntimeException('The payment aggregator rejected the payment request.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('The payment aggregator returned an invalid response.');
        }

        $gatewayId = trim((string) $this->pick($body, (array) ($this->config['response_fields']['gateway_transaction_id'] ?? ['id', 'transaction_id', 'data.id'])));
        if ($gatewayId === '') {
            throw new RuntimeException('The payment aggregator did not return a transaction identifier.');
        }

        $providerStatus = (string) $this->pick($body, (array) ($this->config['response_fields']['status'] ?? ['status', 'data.status']), 'pending');
        $checkoutUrl = trim((string) $this->pick($body, (array) ($this->config['response_fields']['checkout_url'] ?? ['checkout_url', 'redirect_url', 'data.checkout_url']), ''));

        return [
            'success' => true,
            'gateway' => 'generic_aggregator',
            'provider' => (string) ($this->config['provider_name'] ?? 'Payment aggregator'),
            'aggregator' => true,
            'payment_method' => $method,
            'transaction_reference' => $transaction->reference,
            'gateway_transaction_id' => $gatewayId,
            'checkout_url' => $checkoutUrl !== '' ? $checkoutUrl : null,
            'status' => $this->mapStatus($providerStatus),
            'provider_status' => $providerStatus,
            'instructions' => (string) ($this->config['instructions'] ?? 'Complete the payment with your selected provider, then wait for confirmation.'),
        ];
    }

    public function verify(array $payload): array
    {
        $gatewayId = trim((string) ($payload['gateway_transaction_id'] ?? ''));
        if ($gatewayId !== '') {
            return $this->status($gatewayId);
        }

        $reference = trim((string) ($payload['reference'] ?? ''));
        if ($reference !== '' && filled($this->config['status_by_reference_path'] ?? null)) {
            return $this->statusByReference($reference);
        }

        return ['success' => false, 'gateway' => 'generic_aggregator', 'status' => 'pending'];
    }

    public function status(string $gatewayTransactionId): array
    {
        $path = (string) ($this->config['status_path'] ?? '/payments/{id}');
        $path = str_replace('{id}', rawurlencode($gatewayTransactionId), $path);
        $response = $this->request()->get($this->urlForPath($path));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to confirm payment status with the aggregator.');
        }

        return $this->normaliseStatusResponse($response->json(), $gatewayTransactionId);
    }

    public function refund(Transaction $transaction, array $options = []): array
    {
        $refundPath = trim((string) ($this->config['refund_path'] ?? ''));
        if ($refundPath === '') {
            return ['success' => false, 'gateway' => 'generic_aggregator', 'refund_status' => 'manual_required'];
        }

        $path = str_replace('{id}', rawurlencode((string) $transaction->gateway_transaction_id), $refundPath);
        $payload = array_merge([
            'amount' => (float) ($options['amount'] ?? $transaction->amount),
            'reference' => $transaction->reference,
        ], is_array($this->config['refund_extra'] ?? null) ? $this->config['refund_extra'] : []);

        $response = $this->request()->post($this->urlForPath($path), $payload);

        return [
            'success' => $response->successful(),
            'gateway' => 'generic_aggregator',
            'refund_status' => $response->successful() ? 'submitted' : 'failed',
        ];
    }

    public function supportsAutoRenewal(): bool
    {
        return (bool) ($this->config['supports_auto_renewal'] ?? false);
    }

    private function statusByReference(string $reference): array
    {
        $path = str_replace('{reference}', rawurlencode($reference), (string) $this->config['status_by_reference_path']);
        $response = $this->request()->get($this->urlForPath($path));
        if (! $response->successful()) {
            throw new RuntimeException('Unable to confirm payment status with the aggregator.');
        }
        return $this->normaliseStatusResponse($response->json(), null, $reference);
    }

    private function normaliseStatusResponse(mixed $body, ?string $fallbackId = null, ?string $fallbackReference = null): array
    {
        if (! is_array($body)) {
            throw new RuntimeException('The payment aggregator returned an invalid status response.');
        }

        $fields = (array) ($this->config['response_fields'] ?? []);
        $providerStatus = (string) $this->pick($body, (array) ($fields['status'] ?? ['status', 'data.status']), 'pending');
        $gatewayId = trim((string) $this->pick($body, (array) ($fields['gateway_transaction_id'] ?? ['id', 'transaction_id', 'data.id']), $fallbackId ?? ''));
        $reference = trim((string) $this->pick($body, (array) ($fields['reference'] ?? ['reference', 'external_id', 'data.reference']), $fallbackReference ?? ''));
        $amount = $this->pick($body, (array) ($fields['amount'] ?? ['amount', 'data.amount']));
        $currency = $this->pick($body, (array) ($fields['currency'] ?? ['currency', 'data.currency']));

        return [
            'success' => true,
            'gateway' => 'generic_aggregator',
            'provider' => (string) ($this->config['provider_name'] ?? 'Payment aggregator'),
            'aggregator' => true,
            'gateway_transaction_id' => $gatewayId,
            'reference' => $reference !== '' ? $reference : null,
            'status' => $this->mapStatus($providerStatus),
            'provider_status' => $providerStatus,
            'amount' => $amount,
            'currency' => $currency,
        ];
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout(max(5, (int) ($this->config['http_timeout_seconds'] ?? 30)))
            ->acceptJson();

        $authType = strtolower((string) ($this->config['auth']['type'] ?? 'bearer'));
        $auth = (array) ($this->config['auth'] ?? []);

        if ($authType === 'oauth2_client_credentials') {
            $request = $request->withToken($this->oauthToken($auth));
        } elseif ($authType === 'bearer' && filled($auth['token'] ?? null)) {
            $request = $request->withToken((string) $auth['token']);
        } elseif ($authType === 'api_key_header') {
            $header = trim((string) ($auth['header'] ?? 'X-API-Key'));
            $value = trim((string) ($auth['value'] ?? ''));
            if ($value === '') {
                throw new RuntimeException('Aggregator API key is missing.');
            }
            $request = $request->withHeaders([$header => $value]);
        }

        $headers = is_array($this->config['headers'] ?? null) ? $this->config['headers'] : [];
        if ($headers) {
            $request = $request->withHeaders($headers);
        }

        return $request;
    }

    private function oauthToken(array $auth): string
    {
        $tokenUrl = $this->absoluteHttpsUrl((string) ($auth['token_url'] ?? ''));
        $clientId = trim((string) ($auth['client_id'] ?? ''));
        $clientSecret = trim((string) ($auth['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Aggregator OAuth client credentials are missing.');
        }

        $response = Http::timeout(max(5, (int) ($this->config['http_timeout_seconds'] ?? 30)))
            ->asForm()
            ->acceptJson()
            ->post($tokenUrl, array_merge([
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ], is_array($auth['token_extra'] ?? null) ? $auth['token_extra'] : []));

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Unable to authenticate with the payment aggregator.');
        }

        return (string) $response->json('access_token');
    }

    private function urlForPath(string $path): string
    {
        if ($path === '' || str_contains($path, '://')) {
            throw new RuntimeException('Aggregator endpoint paths must be relative to the configured base URL.');
        }
        $base = $this->absoluteHttpsUrl((string) ($this->config['base_url'] ?? ''));
        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private function absoluteHttpsUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || blank($parts['host'] ?? null)) {
            throw new RuntimeException('Aggregator URLs must use HTTPS.');
        }
        return $url;
    }

    private function field(string $key, string $default): string
    {
        return (string) ($this->config['request_fields'][$key] ?? $default);
    }

    private function pick(array $body, array $paths, mixed $default = null): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($body, (string) $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return $default;
    }

    private function mapStatus(string $status): string
    {
        $normalised = strtolower(trim($status));
        $map = (array) ($this->config['status_map'] ?? []);
        foreach ($map as $local => $providerStatuses) {
            foreach ((array) $providerStatuses as $providerStatus) {
                if ($normalised === strtolower(trim((string) $providerStatus))) {
                    return in_array($local, ['successful', 'failed', 'under_review', 'pending'], true) ? $local : 'pending';
                }
            }
        }

        return match ($normalised) {
            'success', 'successful', 'completed', 'paid' => 'successful',
            'failed', 'cancelled', 'canceled', 'rejected', 'reversed' => 'failed',
            'review', 'under_review', 'processing_review' => 'under_review',
            default => 'pending',
        };
    }

    private function mapMethod(string $method): string
    {
        return (string) (($this->config['method_map'][$method] ?? null) ?: $method);
    }

    private function mapNetwork(string $network): string
    {
        return (string) (($this->config['network_map'][$network] ?? null) ?: $network);
    }

    private function methodNeedsPhone(string $method): bool
    {
        $methods = array_map('strtolower', (array) ($this->config['phone_required_methods'] ?? ['mobile_money', 'mtn', 'airtel']));
        return in_array(strtolower($method), $methods, true);
    }

    private function normaliseMsisdn(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        $countryCode = preg_replace('/\D+/', '', (string) ($this->config['default_country_code'] ?? '256')) ?? '256';
        if (str_starts_with($phone, '0')) {
            $phone = $countryCode.substr($phone, 1);
        }
        return $phone;
    }
}
