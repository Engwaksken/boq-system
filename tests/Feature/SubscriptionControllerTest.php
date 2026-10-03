<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_subscription_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true, 'is_archived' => false]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/subscriptions', [
                'plan_id' => $plan->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function test_store_ignores_arbitrary_organisation_id(): void
    {
        $user = User::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true, 'is_archived' => false]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/subscriptions', [
                'plan_id' => $plan->id,
                'organisation_id' => $otherOrg->id,
            ]);

        $response->assertStatus(201);

        // The subscription must be tied to the authenticated user's organisation, not the arbitrary one.
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'plan_id' => $plan->id,
        ]);
        $this->assertDatabaseMissing('subscriptions', ['organisation_id' => $otherOrg->id]);
    }

    public function test_store_rejects_unavailable_plan(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['is_active' => false, 'is_archived' => false]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id]);

        $response->assertStatus(422)
            ->assertJson(['error_code' => 'PLAN_NOT_AVAILABLE']);
    }

    public function test_store_requires_authentication(): void
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        $response = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id]);

        $response->assertStatus(401);
    }

    public function test_store_denies_user_who_already_has_an_active_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true, 'is_archived' => false]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id])
            ->assertForbidden();
    }

    public function test_store_validates_an_absent_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/subscriptions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_store_validates_an_invalid_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/subscriptions', ['plan_id' => 'not-a-plan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_index_lists_subscriptions_for_user(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ])->save();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/subscriptions');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data']);
    }

    public function test_show_returns_subscription_for_owner(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ])->save();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/subscriptions/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $subscription->id);
    }

    public function test_show_denies_access_to_other_users_subscription(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $otherUser->id,
            'organisation_id' => $otherUser->organisation_id,
        ])->save();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/subscriptions/{$subscription->id}");

        $response->assertStatus(403);
    }

    public function test_show_denies_an_ordinary_member_of_the_same_organisation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['organisation_id' => $owner->organisation_id]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $owner->id,
            'organisation_id' => $owner->organisation_id,
        ])->save();

        $this->actingAs($member)
            ->getJson("/api/v1/subscriptions/{$subscription->id}")
            ->assertForbidden();
    }

    public function test_show_allows_an_authorized_organisation_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['organisation_id' => $owner->organisation_id]);
        $permission = Permission::factory()->create(['slug' => 'subscriptions.view']);
        $role = Role::factory()->create(['slug' => 'administrator']);
        $role->permissions()->attach($permission);
        $admin->roles()->attach($role, ['organisation_id' => $owner->organisation_id]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $owner->id,
            'organisation_id' => $owner->organisation_id,
        ])->save();

        $this->actingAs($admin)
            ->getJson("/api/v1/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id);
    }

    public function test_show_denies_a_user_from_an_unrelated_organisation(): void
    {
        $owner = User::factory()->create();
        $unrelated = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $owner->id,
            'organisation_id' => $owner->organisation_id,
        ])->save();

        $this->actingAs($unrelated)
            ->getJson("/api/v1/subscriptions/{$subscription->id}")
            ->assertForbidden();
    }

    public function test_subscription_policy_is_mapped_to_plan_for_create_authorization(): void
    {
        $this->assertInstanceOf(\App\Policies\SubscriptionPolicy::class, Gate::getPolicyFor(Plan::class));
    }

    public function test_proxy_subscription_is_visible_to_beneficiary_and_payer(): void
    {
        $beneficiary = User::factory()->create();
        $payer = User::factory()->create();
        $subscription = Subscription::factory()->forBeneficiary($beneficiary, $payer)->create();

        $this->actingAs($beneficiary)->getJson("/api/v1/subscriptions/{$subscription->id}")
            ->assertOk()->assertJsonPath('data.id', $subscription->id);

        $this->actingAs($payer)->getJson("/api/v1/subscriptions/{$subscription->id}")
            ->assertOk()->assertJsonPath('data.id', $subscription->id);
    }

    public function test_can_pay_allows_proxy_payer_and_regular_subscription_owner_only(): void
    {
        $beneficiary = User::factory()->create();
        $payer = User::factory()->create();
        $outsider = User::factory()->create();
        $proxy = Subscription::factory()->forBeneficiary($beneficiary, $payer)->create();
        $selfSubscription = Subscription::factory()->create(['user_id' => $beneficiary->id]);
        $policy = app(\App\Policies\SubscriptionPolicy::class);

        $this->assertTrue($policy->canPay($payer, $proxy));
        $this->assertFalse($policy->canPay($beneficiary, $proxy));
        $this->assertTrue($policy->canPay($beneficiary, $selfSubscription));
        $this->assertFalse($policy->canPay($outsider, $selfSubscription));
    }

    public function test_index_only_includes_subscriptions_related_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $own = Subscription::factory()->create(['user_id' => $user->id]);
        $unrelated = Subscription::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/subscriptions')->assertOk();

        $this->assertContains($own->id, collect($response->json('data'))->pluck('id')->all());
        $this->assertNotContains($unrelated->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_current_returns_active_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'active',
        ])->save();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/subscriptions/current');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_current_returns_404_when_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/subscriptions/current');

        $response->assertStatus(404)
            ->assertJson(['error_code' => 'NO_ACTIVE_SUBSCRIPTION']);
    }

    public function test_show_missing_subscription_returns_controlled_json_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/subscriptions/999999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
