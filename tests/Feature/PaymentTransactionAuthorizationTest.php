<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTransactionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_transaction(): void
    {
        $owner = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $owner->id]);
        $transaction = Transaction::factory()->create(['subscription_id' => $subscription->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->getJson("/api/v1/transactions/{$transaction->id}")
            ->assertOk()->assertJsonPath('data.id', $transaction->id);
    }

    public function test_guest_cannot_view_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $this->getJson("/api/v1/transactions/{$transaction->id}")->assertUnauthorized();
    }

    public function test_unrelated_user_cannot_view_transaction(): void
    {
        $owner = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $owner->id]);
        $transaction = Transaction::factory()->create(['subscription_id' => $subscription->id, 'user_id' => $owner->id]);

        $this->actingAs(User::factory()->create())->getJson("/api/v1/transactions/{$transaction->id}")->assertForbidden();
    }

    public function test_proxy_payer_can_view_transaction(): void
    {
        $beneficiary = User::factory()->create();
        $payer = User::factory()->create();
        $subscription = Subscription::factory()->forBeneficiary($beneficiary, $payer)->create();
        $transaction = Transaction::factory()->forBeneficiary($beneficiary, $payer)->create(['subscription_id' => $subscription->id]);

        $this->actingAs($payer)->getJson("/api/v1/transactions/{$transaction->id}")
            ->assertOk()->assertJsonPath('data.id', $transaction->id);
    }

    public function test_receipt_is_not_available_until_payment_succeeds(): void
    {
        $owner = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $owner->id]);
        $transaction = Transaction::factory()->pending()->create(['subscription_id' => $subscription->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->getJson("/api/v1/transactions/{$transaction->id}/receipt")
            ->assertUnprocessable()->assertJsonPath('error_code', 'RECEIPT_NOT_AVAILABLE');
    }
}
