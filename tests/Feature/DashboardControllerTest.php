<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_dashboard_summary(): void
    {
        $user = User::factory()->create();

        Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'active',
            'contract_value' => 100000,
        ]);
        Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'completed',
            'contract_value' => 50000,
        ]);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'active',
        ]);
        Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'under_review',
        ]);
        Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'analysed',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total_projects', 3)
            ->assertJsonPath('data.active_projects', 2)
            ->assertJsonPath('data.completed_projects', 1)
            ->assertJsonPath('data.total_boqs', 2)
            ->assertJsonPath('data.boqs_awaiting_review', 1)
            ->assertJsonPath('data.boqs_analysed', 1)
            ->assertJsonPath('data.total_estimated_value', 150000);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
    }
}
