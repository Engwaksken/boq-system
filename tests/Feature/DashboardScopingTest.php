<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(Organisation $organisation, string $role): User
    {
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $organisation->id]);

        return $user;
    }

    public function test_members_only_see_assigned_projects_on_the_dashboard(): void
    {
        $org = Organisation::factory()->create();
        $owner = $this->user($org, 'administrator');
        $member = $this->user($org, 'user');

        $assigned = Project::factory()->create(['organisation_id' => $org->id, 'user_id' => $owner->id]);
        $assigned->assignments()->create(['user_id' => $member->id, 'role' => 'project-manager', 'assigned_by' => $owner->id]);
        Project::factory()->create(['organisation_id' => $org->id, 'user_id' => $owner->id]);

        Livewire::actingAs($member)->test(Dashboard::class)->assertSet('projectsCount', 1);
        Livewire::actingAs($owner)->test(Dashboard::class)->assertSet('projectsCount', 2);
    }
}
