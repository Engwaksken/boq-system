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
        Project::factory()->assignedTo($user)->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/projects');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['data']]);
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/projects');

        $response->assertStatus(403);
    }

    public function test_unassigned_and_revoked_projects_are_hidden_and_cannot_be_read_changed_or_deleted(): void
    {
        $user = $this->userWithPermission('projects.view');
        $user->permissions()->attach(Permission::factory()->create(['slug' => 'projects.edit']));
        $assigned = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $unassigned = Project::factory()->create(['organisation_id' => $user->organisation_id]);
        $revoked = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $revoked->assignments()->first()->delete();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/projects')
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $assigned->id);
        foreach ([$unassigned, $revoked] as $project) {
            $this->getJson('/api/v1/projects/'.$project->id)->assertForbidden();
            $this->putJson('/api/v1/projects/'.$project->id, ['name' => 'Changed'])->assertForbidden();
            $this->deleteJson('/api/v1/projects/'.$project->id)->assertForbidden();
        }
    }

    public function test_project_detail_hides_nested_boqs_without_view_permission_and_foreign_tenant_boqs(): void
    {
        $user = $this->userWithPermission('projects.view');
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $boq = \App\Models\Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id]);
        $foreign = \App\Models\Boq::factory()->create(['project_id' => $project->id, 'status' => 'approved']);
        $boq->update(['status' => 'draft']);
        \App\Models\BoqItem::factory()->create(['boq_id' => $boq->id, 'quantity' => 2, 'original_rate' => 100, 'ai_suggested_rate' => 150, 'reviewed_rate' => null, 'approved_rate' => null]);
        \App\Models\BoqItem::factory()->create(['boq_id' => $foreign->id, 'quantity' => 10, 'original_rate' => 9999]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/projects/'.$project->id)
            ->assertOk()->assertJsonCount(0, 'data.boqs')
            ->assertJsonPath('data.totals.estimated_amount', 0)->assertJsonPath('data.totals.boqs', 0);
        $this->getJson('/api/v1/projects')->assertOk()
            ->assertJsonPath('data.data.0.boqs_count', 0)->assertJsonPath('data.data.0.totals.generated_total', 0);
        $this->getJson('/api/v1/projects?boq_status=draft')->assertOk()->assertJsonCount(0, 'data.data');
        $user->permissions()->attach(Permission::factory()->create(['slug' => 'boq.view']));
        $this->getJson('/api/v1/projects/'.$project->id)
            ->assertOk()->assertJsonCount(1, 'data.boqs')->assertJsonPath('data.boqs.0.id', $boq->id)
            ->assertJsonPath('data.totals.estimated_amount', 200)->assertJsonPath('data.totals.generated_total', 300);
        $this->getJson('/api/v1/projects')->assertOk()
            ->assertJsonPath('data.data.0.boqs_count', 1)->assertJsonPath('data.data.0.totals.estimated_amount', 200);
        $this->getJson('/api/v1/projects?boq_status=approved')->assertOk()->assertJsonCount(0, 'data.data');
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
        $this->assertDatabaseHas('project_assignments', [
            'project_id' => $response->json('data.id'), 'user_id' => $user->id, 'deleted_at' => null,
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
        $project = Project::factory()->assignedTo($user)->create([
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
        $project = Project::factory()->assignedTo($user)->create([
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
        $project = Project::factory()->assignedTo($user)->create([
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
