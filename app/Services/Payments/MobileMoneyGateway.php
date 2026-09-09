<?php

namespace App\Services\Payments;

use App\Models\Transaction;

class MobileMoneyGateway implements PaymentGatewayInterface
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
        // Placeholder implementation. Real mobile money integration is out of
        // scope for Phase 1. Credentials are read from config placeholders only.
        return [
            'success' => true,
            'gateway' => 'mobile_money',
            'transaction_reference' => $transaction->reference,
            'provider' => $this->config['provider'] ?? config('payments.mobile_money.provider'),
            'phone' => $transaction->user?->phone,
            'mode' => $this->config['mode'] ?? config('payments.mobile_money.mode', 'test'),
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
            'gateway' => 'mobile_money',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'successful',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $gatewayTransactionId): array
    {
        return [
            'success' => true,
            'gateway' => 'mobile_money',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'successful',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function refund(Transaction $transaction, array $options = []): array
    {
        return [
            'success' => true,
            'gateway' => 'mobile_money',
            'refund_status' => 'refunded',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function supportsAutoRenewal(): bool
    {
        return false;
    }
}
