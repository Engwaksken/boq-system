<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $slug): User
    {
        $permission = Permission::factory()->create(['slug' => $slug]);
        $role = Role::factory()->create(['slug' => 'project-manager']);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_index_lists_projects_for_user(): void
    {
        $user = $this->userWithPermission('projects.view');
        Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/projects');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['data']]);
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/projects');

        $response->assertStatus(403);
    }

    public function test_store_creates_project(): void
    {
        $user = $this->userWithPermission('projects.create');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/projects', [
                'name' => 'New Project',
                'client' => 'Client A',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.name', 'New Project');

        $this->assertDatabaseHas('projects', [
            'name' => 'New Project',
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'draft',
        ]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/projects', ['name' => 'New Project']);

        $response->assertStatus(403);
    }

    public function test_show_returns_project_for_owner(): void
    {
        $user = $this->userWithPermission('projects.view');
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $project->id);
    }

    public function test_show_denies_access_to_other_users_project(): void
    {
        $user = $this->userWithPermission('projects.view');
        $otherUser = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $otherUser->id,
            'organisation_id' => $otherUser->organisation_id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(403);
    }

    public function test_update_updates_project(): void
    {
        $user = $this->userWithPermission('projects.edit');
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/projects/{$project->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Updated Name']);
    }

    public function test_update_denies_access_to_other_users_project(): void
    {
        $user = $this->userWithPermission('projects.edit');
        $otherUser = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $otherUser->id,
            'organisation_id' => $otherUser->organisation_id,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/projects/{$project->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_destroy_deletes_project(): void
    {
        $user = $this->userWithPermission('projects.edit');
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_destroy_denies_access_to_other_users_project(): void
    {
        $user = $this->userWithPermission('projects.edit');
        $otherUser = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $otherUser->id,
            'organisation_id' => $otherUser->organisation_id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(403);
    }
}
