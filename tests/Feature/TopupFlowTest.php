<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\ProductVersion;
use App\Models\Subscription;
use App\Models\Topup;
use App\Models\TopupPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TopupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_grants_included_feature_entitlement(): void
    {
        $user = User::factory()->create();
        $feature = Feature::factory()->create(['code' => 'variations']);
        $topup = Topup::factory()->create([
            'type' => 'feature_unlock',
            'included_features' => ['variations'],
            'is_permanent' => true,
        ]);
        $purchase = TopupPurchase::factory()->create([
            'topup_id' => $topup->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $service = app(TopupService::class);
        $activated = $service->activate($purchase);

        $this->assertEquals('active', $activated->status);
        $this->assertTrue($activated->is_permanent);
        $this->assertCount(1, Entitlement::query()->where('user_id', $user->id)->get());
        $entitlement = Entitlement::query()->where('user_id', $user->id)->first();
        $this->assertEquals($feature->id, $entitlement->feature_id);
        $this->assertEquals('topup', $entitlement->source);
        $this->assertEquals($activated->id, $entitlement->topup_purchase_id);
    }

    public function test_activation_is_idempotent(): void
    {
        $user = User::factory()->create();
        $topup = Topup::factory()->create([
            'type' => 'feature_unlock',
            'included_features' => ['variations'],
            'is_permanent' => true,
        ]);
        $purchase = TopupPurchase::factory()->create([
            'topup_id' => $topup->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $service = app(TopupService::class);
        $service->activate($purchase);
        $service->activate($purchase->fresh());

        $this->assertCount(1, Entitlement::query()->where('user_id', $user->id)->get());
    }

    public function test_usage_credit_topup_grants_credit_entitlement(): void
    {
        $user = User::factory()->create();
        $topup = Topup::factory()->create([
            'type' => 'ai_credit_topup',
            'usage_credits' => ['ai_credits' => 50],
            'duration_days' => 30,
        ]);
        $purchase = TopupPurchase::factory()->create([
            'topup_id' => $topup->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $service = app(TopupService::class);
        $activated = $service->activate($purchase);

        $creditFeature = Feature::query()->where('code', Topup::creditFeatureCode('ai_credits'))->first();
        $this->assertNotNull($creditFeature);
        $entitlement = Entitlement::query()->where('feature_id', $creditFeature->id)->first();
        $this->assertEquals(50, $entitlement->limits['ai_credits'] ?? null);
        $this->assertEquals(50, $entitlement->remainingFor('ai_credits'));
        $this->assertEquals(30, $activated->activated_at->diffInDays($activated->expires_at));
    }

    public function test_version_update_bumps_subscription_product_version(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'product_version' => '1.0',
        ]);
        ProductVersion::factory()->count(1)->create();
        $topup = Topup::factory()->create([
            'type' => 'version_update',
            'release_version' => '2.0',
            'included_features' => ['cost.tracking'],
            'is_permanent' => true,
        ]);
        $purchase = TopupPurchase::factory()->create([
            'topup_id' => $topup->id,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        app(TopupService::class)->activate($purchase);

        $this->assertEquals('2.0', $subscription->fresh()->product_version);
    }

    public function test_purchase_limit_blocks_extra_purchases(): void
    {
        $user = User::factory()->create();
        $topup = Topup::factory()->create([
            'type' => 'feature_unlock',
            'purchase_limit' => 1,
        ]);
        TopupPurchase::factory()->create([
            'topup_id' => $topup->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(TopupService::class);
        $this->assertFalse($service->purchasableBy($topup, $user));
    }

    public function test_settlement_activates_topup_transaction(): void
    {
        $user = User::factory()->create();
        $topup = Topup::factory()->create([
            'type' => 'feature_unlock',
            'included_features' => ['variations'],
            'is_permanent' => true,
        ]);
        Feature::factory()->create(['code' => 'variations']);

        $transaction = Transaction::create([
            'reference' => 'BOQ-TP-TEST-1',
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'product_type' => 'topup',
            'product_id' => $topup->id,
            'amount' => $topup->price,
            'currency' => 'UGX',
            'payment_method' => 'momo',
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        app(\App\Services\Payments\PaymentSettlementService::class)
            ->settle($transaction, ['status' => 'successful', 'tx_ref' => $transaction->reference]);

        $this->assertEquals('successful', $transaction->fresh()->status);
        $this->assertNotNull(TopupPurchase::query()->where('transaction_id', $transaction->id)->first());
        $this->assertCount(1, Entitlement::query()->where('user_id', $user->id)->get());
        $this->assertNotNull($transaction->fresh()->invoice);
    }
}