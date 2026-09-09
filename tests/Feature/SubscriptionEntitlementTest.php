<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_activate_monthly_subscription_sets_dates(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'type' => 'monthly',
            'duration_days' => 30,
            'grace_period_days' => 7,
        ]);
        $subscription = Subscription::factory()->create([
            'plan_id' => $plan->id,
        ]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ])->save();

        $service = app(SubscriptionService::class);
        $activated = $service->activate($subscription);

        $this->assertEquals('active', $activated->status);
        $this->assertEquals('paid', $activated->payment_status);
        $this->assertNotNull($activated->start_date);
        $this->assertNotNull($activated->end_date);
        $this->assertEquals(30, $activated->start_date->diffInDays($activated->end_date));
        $this->assertNotNull($activated->grace_period_end_date);
        $this->assertEquals('monthly', $activated->access_type);
    }

    public function test_activate_lifetime_subscription_has_no_end_date(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'type' => 'lifetime',
            'duration_days' => null,
        ]);
        $subscription = Subscription::factory()->create([
            'plan_id' => $plan->id,
        ]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'status' => 'pending',
        ])->save();

        $service = app(SubscriptionService::class);
        $activated = $service->activate($subscription);

        $this->assertEquals('active', $activated->status);
        $this->assertNull($activated->end_date);
        $this->assertEquals('lifetime', $activated->access_type);
    }

    public function test_activate_grants_plan_entitlements(): void
    {
        $user = User::factory()->create();
        $feature1 = Feature::factory()->create(['code' => 'boq.management']);
        $feature2 = Feature::factory()->create(['code' => 'project.management']);
        $plan = Plan::factory()->create(['type' => 'monthly', 'duration_days' => 30]);
        $plan->features()->attach([$feature1->id, $feature2->id]);

        $subscription = Subscription::factory()->create([
            'plan_id' => $plan->id,
        ]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'status' => 'pending',
        ])->save();

        $service = app(SubscriptionService::class);
        $service->activate($subscription);

        $this->assertCount(2, $subscription->fresh()->entitlements);
        $this->assertTrue($service->hasFeature($user, 'boq.management'));
        $this->assertTrue($service->hasFeature($user, 'project.management'));
    }

    public function test_has_feature_returns_false_without_entitlement(): void
    {
        $user = User::factory()->create();
        $service = app(SubscriptionService::class);

        $this->assertFalse($service->hasFeature($user, 'boq.management'));
    }

    public function test_entitlement_is_valid_when_active_and_not_expired(): void
    {
        $user = User::factory()->create();
        $feature = Feature::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill(['user_id' => $user->id])->save();

        $entitlement = Entitlement::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'is_permanent' => false,
        ]);

        $this->assertTrue($entitlement->isValid());
    }

    public function test_entitlement_is_invalid_when_expired(): void
    {
        $user = User::factory()->create();
        $feature = Feature::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill(['user_id' => $user->id])->save();

        $entitlement = Entitlement::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
            'is_permanent' => false,
        ]);

        $this->assertFalse($entitlement->isValid());
    }

    public function test_permanent_entitlement_is_always_valid(): void
    {
        $user = User::factory()->create();
        $feature = Feature::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill(['user_id' => $user->id])->save();

        $entitlement = Entitlement::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'expires_at' => null,
            'is_permanent' => true,
        ]);

        $this->assertTrue($entitlement->isValid());
    }

    public function test_entitlement_remaining_for_returns_remaining(): void
    {
        $user = User::factory()->create();
        $feature = Feature::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill(['user_id' => $user->id])->save();

        $entitlement = Entitlement::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'limits' => ['ai_credits' => 100],
            'usage' => ['ai_credits' => 30],
        ]);

        $this->assertEquals(70, $entitlement->remainingFor('ai_credits'));
        $this->assertNull($entitlement->remainingFor('unlimited_key'));
    }

    public function test_current_subscription_returns_active(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'status' => 'active',
        ])->save();

        $service = app(SubscriptionService::class);
        $current = $service->currentSubscription($user);

        $this->assertNotNull($current);
        $this->assertEquals('active', $current->status);
    }

    public function test_current_subscription_returns_null_when_none_active(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'status' => 'expired',
        ])->save();

        $service = app(SubscriptionService::class);
        $current = $service->currentSubscription($user);

        $this->assertNull($current);
    }

    public function test_super_admin_has_all_features(): void
    {
        $superAdminRole = \App\Models\Role::factory()->create(['slug' => 'super-admin']);
        $user = User::factory()->create();
        $user->roles()->attach($superAdminRole);

        $service = app(SubscriptionService::class);
        $this->assertTrue($service->hasFeature($user, 'any.feature.code'));
    }
}
