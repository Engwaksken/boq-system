<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileTeamSupportParityTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator', 'is_system' => true]);
        $user->roles()->attach($role, ['organisation_id' => $user->organisation_id]);

        return $user;
    }

    public function test_assignment_lifecycle_and_tenant_boundaries(): void
    {
        $admin = $this->administrator();
        $member = User::factory()->create(['organisation_id' => $admin->organisation_id]);
        $project = Project::factory()->create(['organisation_id' => $admin->organisation_id]);
        $foreign = User::factory()->create();
        $this->actingAs($admin)->getJson('/api/v1/project-assignments/options')->assertOk()
            ->assertJsonCount(1, 'data.projects')->assertJsonCount(2, 'data.members');
        $data = ['project_id' => $project->id, 'user_id' => $member->id, 'role' => 'finance'];
        $id = $this->postJson('/api/v1/project-assignments', $data)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/project-assignments')->assertOk()->assertJsonPath('data.0.user.email', $member->email);
        $this->postJson('/api/v1/project-assignments', [...$data, 'user_id' => $foreign->id])->assertNotFound();
        $this->postJson('/api/v1/project-assignments', [...$data, 'role' => 'administrator'])->assertUnprocessable();
        $this->deleteJson('/api/v1/project-assignments/'.$id)->assertNoContent();
        $this->assertSoftDeleted('project_assignments', ['id' => $id]);
        $this->postJson('/api/v1/project-assignments', [...$data, 'role' => 'user'])->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseHas('project_assignments', ['id' => $id, 'deleted_at' => null, 'role' => 'user']);
        $this->actingAs($foreign)->deleteJson('/api/v1/project-assignments/'.$id)->assertForbidden();
        $this->actingAs($member)->getJson('/api/v1/project-assignments/options')->assertForbidden();
        $admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($admin)->getJson('/api/v1/project-assignments')->assertForbidden();
    }

    public function test_reader_faqs_are_active_searchable_and_ordered(): void
    {
        Faq::create(['question' => 'Receipts?', 'answer' => 'Attach a PDF.', 'is_active' => true, 'sort_order' => 2]);
        Faq::create(['question' => 'Expenses?', 'answer' => 'Keep receipts.', 'is_active' => true, 'sort_order' => 1]);
        Faq::create(['question' => 'Hidden receipts?', 'answer' => 'Draft.', 'is_active' => false]);
        $this->getJson('/api/v1/faqs')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/faqs?search=receipts')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.question', 'Expenses?');
        $this->getJson('/api/v1/faqs?search=missing')->assertOk()->assertJsonCount(0, 'data');
    }
}
