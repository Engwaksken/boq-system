<?php

namespace App\Services\Payments;

use App\Models\Transaction;

class BankTransferGateway implements PaymentGatewayInterface
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
        // Bank transfer requires manual verification. Return bank details
        // from config placeholders so the user can complete the transfer.
        return [
            'success' => true,
            'gateway' => 'bank_transfer',
            'transaction_reference' => $transaction->reference,
            'bank_details' => config('payments.bank_transfer.details'),
            'requires_manual_verification' => true,
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
            'gateway' => 'bank_transfer',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'under_review',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function status(string $gatewayTransactionId): array
    {
        return [
            'success' => true,
            'gateway' => 'bank_transfer',
            'gateway_transaction_id' => $gatewayTransactionId,
            'status' => 'under_review',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function refund(Transaction $transaction, array $options = []): array
    {
        return [
            'success' => true,
            'gateway' => 'bank_transfer',
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
