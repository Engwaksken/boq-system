<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcilePendingPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function transaction(string $status, int $hoursAgo, PaymentGateway $gateway): Transaction
    {
        $transaction = Transaction::factory()->create([
            'payment_gateway_id' => $gateway->id,
            'status' => $status,
            'completed_at' => null,
        ]);
        $transaction->forceFill([
            'created_at' => now()->subHours($hoursAgo),
            'updated_at' => now()->subHours($hoursAgo),
        ])->saveQuietly();

        return $transaction;
    }

    public function test_stale_pending_payments_are_marked_abandoned(): void
    {
        // Inactive gateway: recent transactions are skipped instead of re-queried.
        $gateway = PaymentGateway::factory()->create(['is_active' => false]);

        $stalePending = $this->transaction('pending', 49, $gateway);
        $staleInitiated = $this->transaction('initiated', 72, $gateway);
        $recentPending = $this->transaction('pending', 2, $gateway);
        $staleReview = $this->transaction('under_review', 96, $gateway);
        $staleSuccess = $this->transaction('successful', 96, $gateway);

        $this->artisan('payments:reconcile')
            ->expectsOutputToContain('abandoned 2')
            ->assertSuccessful();

        $this->assertSame('abandoned', $stalePending->fresh()->status);
        $this->assertNotNull($stalePending->fresh()->failure_reason);
        $this->assertSame('abandoned', $staleInitiated->fresh()->status);
        $this->assertSame('pending', $recentPending->fresh()->status);
        $this->assertSame('under_review', $staleReview->fresh()->status);
        $this->assertSame('successful', $staleSuccess->fresh()->status);
    }
}
