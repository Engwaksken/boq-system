<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Console\Command;
use Throwable;

class ReconcilePendingPayments extends Command
{
    /** Statuses a customer-initiated payment sits in while waiting on the provider. */
    public const ABANDONABLE_STATUSES = ['initiated', 'pending'];

    protected $signature = 'payments:reconcile
        {--hours=48 : Only inspect pending payments created within this many hours}
        {--abandon-after=48 : Mark initiated/pending payments older than this many hours as abandoned}';
    protected $description = 'Re-query payment providers for pending transactions and safely settle confirmed subscriptions';

    public function handle(PaymentManager $payments, PaymentSettlementService $settlements): int
    {
        $cutoff = now()->subHours(max(1, (int) $this->option('hours')));
        $checked = $activated = $failed = 0;

        Transaction::query()
            ->whereIn('status', ['initiated', 'pending', 'under_review'])
            ->where('created_at', '>=', $cutoff)
            ->with(['paymentGateway', 'subscription'])
            ->orderBy('id')
            ->chunkById(50, function ($transactions) use ($payments, $settlements, &$checked, &$activated, &$failed) {
                foreach ($transactions as $transaction) {
                    $gateway = $transaction->paymentGateway;
                    if (! $gateway || ! $gateway->is_active) {
                        continue;
                    }

                    try {
                        $result = $payments->resolve($gateway)->verify([
                            'gateway_transaction_id' => $transaction->gateway_transaction_id,
                            'reference' => $transaction->reference,
                        ]);
                        $checked++;

                        $before = $transaction->status;
                        $settled = $settlements->settle($transaction, $result, 'reconciliation');
                        if ($before !== 'successful' && $settled->status === 'successful') {
                            $activated++;
                        } elseif ($settled->status === 'failed') {
                            $failed++;
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        // Payments that never completed within the window are closed out so they stop
        // looking "in progress" in admin views and reports. Bank transfers awaiting
        // manual review (under_review) are left for an administrator. A late provider
        // callback can still settle an abandoned transaction as successful.
        $abandonCutoff = now()->subHours(max(1, (int) $this->option('abandon-after')));
        $abandoned = Transaction::query()
            ->whereIn('status', self::ABANDONABLE_STATUSES)
            ->where('created_at', '<', $abandonCutoff)
            ->update([
                'status' => 'abandoned',
                'failure_reason' => 'Payment was not completed and has been marked abandoned.',
                'updated_at' => now(),
            ]);

        $this->info("Checked {$checked}; activated {$activated}; failed {$failed}; abandoned {$abandoned}.");
        return self::SUCCESS;
    }
}
