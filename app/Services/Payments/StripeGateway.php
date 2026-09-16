<?php

namespace App\Services\Payments;

use App\Models\Transaction;

class StripeGateway implements PaymentGatewayInterface
{
    /**
     * The gateway configuration (from config placeholders).
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function initiate(Transaction $transaction): array
    {
        // Placeholder implementation. Real Stripe integration is out of scope
        // for Phase 1. Credentials are read from config placeholders only.
        return [
            'success' => true,
            'gateway' => 'stripe',
            'transaction_reference' => $transaction->reference,
            'checkout_url' => config('payments.stripe.checkout_url'),
            'mode' => $this->config['mode'] ?? config('payments.stripe.mode', 'test'),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function verify(array $payload): array
    {
        $gatewayTransactionId = $payload['gateway_transaction_id'] ?? null;

        return [
            'success' => true,
            'gateway' => 'stripe',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => (($this->config['mode'] ?? 'test') === 'test' && ($this->config['allow_test_auto_success'] ?? false)) ? 'successful' : 'pending',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $gatewayTransactionId): array
    {
        return [
            'success' => true,
            'gateway' => 'stripe',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => (($this->config['mode'] ?? 'test') === 'test' && ($this->config['allow_test_auto_success'] ?? false)) ? 'successful' : 'pending',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function refund(Transaction $transaction, array $options = []): array
    {
        return [
            'success' => true,
            'gateway' => 'stripe',
            'refund_status' => 'refunded',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function supportsAutoRenewal(): bool
    {
        return true;
    }
}
