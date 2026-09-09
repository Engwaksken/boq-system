<?php

namespace App\Services\Payments;

use App\Models\Transaction;

interface PaymentGatewayInterface
{
    /**
     * Initialise a payment for the given transaction.
     *
     * @return array<string, mixed> Payment initiation payload (redirect URL, reference, etc.)
     */
    public function initiate(Transaction $transaction): array;

    /**
     * Verify a payment using the gateway response or webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): array;

    /**
     * Check the status of a payment by gateway transaction id.
     */
    public function status(string $gatewayTransactionId): array;

    /**
     * Refund a previously successful transaction.
     *
     * @param  array<string, mixed>  $options
     */
    public function refund(Transaction $transaction, array $options = []): array;

    /**
     * Whether this gateway supports automatic recurring billing.
     */
    public function supportsAutoRenewal(): bool;
}
