<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
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
}
