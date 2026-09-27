<?php

namespace Tests\Feature;

use App\Livewire\Projects\Create as ProjectsCreate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class NewCustomerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_newly_registered_user_can_create_a_project(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->post('/register', [
            'name' => 'New Customer',
            'email' => 'customer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertRedirect();

        $user = User::where('email', 'customer@example.com')->firstOrFail();

        $this->get(route('projects.create'))->assertOk();

        Livewire::actingAs($user)
            ->test(ProjectsCreate::class)
            ->set('name', 'First Project')
            ->set('status', 'draft')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Project::where('name', 'First Project')->where('user_id', $user->id)->exists());
        $this->assertTrue($user->hasPermission('projects.edit'));
        $this->assertTrue($user->hasPermission('boq.edit'));
    }

    public function test_migration_repairs_old_viewer_role_and_roleless_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Simulate a database seeded before the viewer role could create projects.
        $viewer = Role::where('slug', 'viewer')->firstOrFail();
        $viewer->permissions()->detach(
            \App\Models\Permission::whereIn('slug', ['projects.create', 'projects.edit', 'boq.edit'])->pluck('id')
        );
        $roleless = User::factory()->create();

        $this->assertFalse($roleless->hasPermission('projects.create'));

        $migration = require database_path('migrations/2026_09_27_190000_grant_customer_permissions_to_viewer_role.php');
        $migration->up();
        $migration->up(); // idempotent

        $roleless = $roleless->fresh();
        $this->assertTrue($roleless->hasAnyRole(['viewer']));
        $this->assertTrue($roleless->hasPermission('projects.create'));
        $this->assertSame(1, DB::table('role_user')->where('user_id', $roleless->id)->count());
    }
}
