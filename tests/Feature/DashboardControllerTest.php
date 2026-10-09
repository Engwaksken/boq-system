<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Role;
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
        $user->roles()->attach(
            \App\Models\Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator'])->id,
            ['organisation_id' => $user->organisation_id],
        );

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

    public function test_a_member_only_sees_assigned_projects(): void
    {
        $org = Organisation::factory()->create();

        $owner = User::factory()->create(['organisation_id' => $org->id]);
        $owner->roles()->attach(Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator'])->id, ['organisation_id' => $org->id]);

        $member = User::factory()->create(['organisation_id' => $org->id]);
        $member->roles()->attach(Role::firstOrCreate(['slug' => 'user'], ['name' => 'User'])->id, ['organisation_id' => $org->id]);

        $assigned = Project::factory()->create(['organisation_id' => $org->id, 'user_id' => $owner->id]);
        $assigned->assignments()->create(['user_id' => $member->id, 'role' => 'project-manager', 'assigned_by' => $owner->id]);
        Project::factory()->create(['organisation_id' => $org->id, 'user_id' => $owner->id]);

        $this->actingAs($member)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_projects', 1);
    }
}
