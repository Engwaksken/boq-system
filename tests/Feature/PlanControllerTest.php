<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_active_plans(): void
    {
        Plan::factory()->create(['is_active' => true, 'is_archived' => false]);
        Plan::factory()->create(['is_active' => false, 'is_archived' => false]);
        Plan::factory()->create(['is_active' => true, 'is_archived' => true]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data']);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_returns_active_plan(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true, 'is_archived' => false]);
        $feature = Feature::factory()->create();
        $plan->features()->attach($feature);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/plans/{$plan->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $plan->id);
    }

    public function test_show_returns_404_for_inactive_plan(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['is_active' => false, 'is_archived' => false]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/plans/{$plan->id}");

        $response->assertStatus(404)
            ->assertJson(['error_code' => 'PLAN_NOT_FOUND']);
    }

    public function test_show_returns_404_for_archived_plan(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true, 'is_archived' => true]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/plans/{$plan->id}");

        $response->assertStatus(404)
            ->assertJson(['error_code' => 'PLAN_NOT_FOUND']);
    }
}
