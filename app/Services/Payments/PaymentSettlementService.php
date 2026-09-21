<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\SubscriptionService;
use App\Services\TopupService;
use Illuminate\Support\Facades\DB;

class PaymentSettlementService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly TopupService $topups,
    ) {
    }

    /**
     * Persist a provider verification result safely and idempotently.
     */
    public function settle(Transaction $transaction, array $result, string $source = 'verification'): Transaction
    {
        return DB::transaction(function () use ($transaction, $result, $source) {
            /** @var Transaction $locked */
            $locked = Transaction::query()
                ->with(['subscription.plan', 'paymentGateway'])
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            // A confirmed payment is terminal. Late or duplicated callbacks must never
            // downgrade it or re-activate the subscription/reset entitlement usage.
            if ($locked->status === 'successful') {
                $this->ensurePaidInvoice($locked);
                return $locked->fresh(['subscription.plan', 'paymentGateway', 'invoice']);
            }

            $status = (string) ($result['status'] ?? 'pending');
            $gatewayId = $result['gateway_transaction_id'] ?? $locked->gateway_transaction_id;

            if ($status === 'successful' && ! $this->matchesExpectedPayment($locked, $result)) {
                $status = 'failed';
                $result['verification_mismatch'] = true;
            }

            $metadata = $locked->metadata ?? [];
            $metadata[$source] = $result;

            $locked->update([
                'gateway_transaction_id' => $gatewayId,
                'status' => $status,
                'completed_at' => $status === 'successful' ? now() : $locked->completed_at,
                'failed_at' => $status === 'failed' ? now() : $locked->failed_at,
                'failure_reason' => $status === 'failed' && ($result['verification_mismatch'] ?? false)
                    ? 'Payment details did not match the expected amount, currency or reference.'
                    : $locked->failure_reason,
                'metadata' => $metadata,
            ]);

            if ($status === 'successful') {
                if ($locked->subscription && ! (
                    $locked->subscription->status === 'active'
                    && $locked->subscription->payment_status === 'paid'
                )) {
                    $this->subscriptions->activate($locked->subscription);
                }

                if ($locked->product_type === 'topup') {
                    $this->topups->activateForTransaction($locked);
                }

                $this->ensurePaidInvoice($locked->fresh());
            }

            return $locked->fresh(['subscription.plan', 'paymentGateway', 'invoice']);
        });
    }

    private function matchesExpectedPayment(Transaction $transaction, array $result): bool
    {
        $amount = $result['amount'] ?? null;
        $currency = strtoupper((string) ($result['currency'] ?? ''));
        $reference = (string) ($result['tx_ref'] ?? $result['reference'] ?? '');

        if ($amount !== null && abs((float) $amount - (float) $transaction->amount) > 0.01) {
            return false;
        }
        if ($currency !== '' && $currency !== strtoupper((string) $transaction->currency)) {
            return false;
        }
        if ($reference !== '' && $reference !== $transaction->reference) {
            return false;
        }

        return true;
    }

    private function ensurePaidInvoice(Transaction $transaction): Invoice
    {
        return Invoice::updateOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'invoice_number' => 'INV-'.$transaction->reference,
                'user_id' => $transaction->user_id,
                'organisation_id' => $transaction->organisation_id,
                'subscription_id' => $transaction->subscription_id,
                'product_type' => $transaction->product_type,
                'product_id' => $transaction->product_id,
                'amount' => $transaction->amount,
                'tax_amount' => 0,
                'total_amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => 'paid',
                'payment_date' => $transaction->completed_at ?? now(),
                'locale' => $transaction->user?->locale ?? 'en',
                'metadata' => [
                    'payment_reference' => $transaction->reference,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                    'payment_method' => $transaction->payment_method,
                    'gateway' => $transaction->paymentGateway?->code,
                ],
            ]
        );
    }
}
