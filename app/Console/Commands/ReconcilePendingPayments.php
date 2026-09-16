<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Console\Command;
use Throwable;

class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile {--hours=48 : Only inspect pending payments created within this many hours}';
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

        $this->info("Checked {$checked}; activated {$activated}; failed {$failed}.");
        return self::SUCCESS;
    }
}
