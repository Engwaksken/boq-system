<?php

namespace Tests\Feature;

use App\Livewire\Projects\Create;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectAssignmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_grants_only_same_tenant_creators_and_preserves_revocations(): void
    {
        $user = User::factory()->create();
        $attributes = ['user_id' => $user->id, 'organisation_id' => $user->organisation_id];
        $legacy = Project::factory()->create($attributes);
        $existing = Project::factory()->assignedTo($user)->create($attributes);
        $revoked = Project::factory()->assignedTo($user)->create($attributes);
        $revoked->assignments()->first()->delete();
        $foreign = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => Organisation::factory()->create()->id]);
        $deleted = Project::factory()->create($attributes);
        $deleted->delete();
        $migration = require database_path('migrations/2026_10_05_000001_backfill_project_creator_assignments.php');
        $migration->up();
        $migration->up();
        $this->assertSame(1, $legacy->assignments()->count());
        $this->assertSame(1, $existing->assignments()->count());
        $this->assertSame(0, $revoked->assignments()->count());
        $this->assertSame(0, $foreign->assignments()->count());
        $this->assertSame(0, $deleted->assignments()->count());
        $this->assertTrue($legacy->isAccessibleTo($user));
    }

    public function test_web_project_creator_receives_an_active_assignment_and_can_import_boqs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'), ['organisation_id' => $user->organisation_id]);
        Livewire::actingAs($user)->test(Create::class)->set('name', 'Web project')->set('currency', 'UGX')
            ->call('save')->assertHasNoErrors();
        $project = Project::where('name', 'Web project')->firstOrFail();
        $this->assertDatabaseHas('project_assignments', ['project_id' => $project->id, 'user_id' => $user->id, 'deleted_at' => null]);
        $this->assertTrue($user->can('create', [\App\Models\Boq::class, $project]));
    }
}
