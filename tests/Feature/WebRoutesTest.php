<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\HardwarePrice;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $slug): User
    {
        $permission = Permission::factory()->create(['slug' => $slug]);
        $user = User::factory()->create();
        $user->permissions()->attach($permission);

        return $user;
    }

    private function createHardwarePrice(User $user): HardwarePrice
    {
        return HardwarePrice::create([
            'organisation_id' => $user->organisation_id,
            'item_name' => 'Cement 50kg',
            'brand' => 'Tororo',
            'category' => 'Cement',
            'specification' => 'Ordinary Portland',
            'unit' => 'bag',
            'price' => 45000,
            'currency' => 'UGX',
            'supplier' => 'Supplier A',
            'location' => 'Kampala',
            'fetched_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login_from_root(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_from_protected_pages(): void
    {
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/boqs')->assertRedirect('/login');
        $this->get('/hardware-prices')->assertRedirect('/login');
        $this->get('/plans')->assertRedirect('/login');
        $this->get('/subscriptions')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_authenticated_user_is_redirected_to_dashboard_from_root(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
    }

    public function test_projects_index_requires_projects_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/projects')->assertStatus(403);
    }

    public function test_projects_index_loads_with_permission(): void
    {
        $user = $this->userWithPermission('projects.view');

        $this->actingAs($user)->get('/projects')->assertOk();
    }

    public function test_projects_create_requires_projects_create_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/projects/create')->assertStatus(403);
    }

    public function test_projects_create_loads_with_permission(): void
    {
        $user = $this->userWithPermission('projects.create');

        $this->actingAs($user)->get('/projects/create')->assertOk();
    }

    public function test_projects_show_loads_for_owner(): void
    {
        $user = $this->userWithPermission('projects.view');
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);

        $this->actingAs($user)->get("/projects/{$project->id}")->assertOk();
    }

    public function test_projects_show_is_forbidden_for_non_owner(): void
    {
        $user = $this->userWithPermission('projects.view');
        $other = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $other->id,
            'organisation_id' => $other->organisation_id,
        ]);

        $this->actingAs($user)->get("/projects/{$project->id}")->assertStatus(403);
    }

    public function test_projects_edit_loads_for_owner_with_permission(): void
    {
        $user = $this->userWithPermission('projects.edit');
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);

        $this->actingAs($user)->get("/projects/{$project->id}/edit")->assertOk();
    }

    public function test_projects_edit_requires_projects_edit_permission(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);

        $this->actingAs($user)->get("/projects/{$project->id}/edit")->assertStatus(403);
    }

    public function test_boqs_index_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/boqs')->assertOk();
    }

    public function test_boqs_create_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/boqs/create')->assertOk();
    }

    public function test_boqs_show_loads_for_owner(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);
        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
        ]);

        $this->actingAs($user)->get("/boqs/{$boq->id}")->assertOk();
    }

    public function test_boqs_show_is_forbidden_for_non_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $other->id,
            'organisation_id' => $other->organisation_id,
        ]);
        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $other->organisation_id,
        ]);

        $this->actingAs($user)->get("/boqs/{$boq->id}")->assertStatus(403);
    }

    public function test_boqs_pdf_downloads_for_owner(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);
        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
        ]);

        $this->actingAs($user)->get("/boqs/{$boq->id}/pdf")->assertOk();
    }

    public function test_hardware_prices_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/hardware-prices')->assertStatus(403);
    }

    public function test_hardware_prices_index_loads_with_permission(): void
    {
        $user = $this->userWithPermission('hardware-prices.view');
        $this->createHardwarePrice($user);

        $this->actingAs($user)->get('/hardware-prices')->assertOk();
    }

    public function test_hardware_prices_compare_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/hardware-prices/compare')->assertStatus(403);
    }

    public function test_hardware_prices_compare_loads_with_permission(): void
    {
        $user = $this->userWithPermission('hardware-prices.view');
        $this->createHardwarePrice($user);

        $this->actingAs($user)->get('/hardware-prices/compare')->assertOk();
    }

    public function test_hardware_prices_recommendations_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/hardware-prices/recommendations')->assertStatus(403);
    }

    public function test_hardware_prices_recommendations_loads_with_permission(): void
    {
        $user = $this->userWithPermission('hardware-prices.view');
        $this->createHardwarePrice($user);

        $this->actingAs($user)->get('/hardware-prices/recommendations')->assertOk();
    }

    public function test_hardware_prices_show_loads_for_own_organisation(): void
    {
        $user = $this->userWithPermission('hardware-prices.view');
        $price = $this->createHardwarePrice($user);

        $this->actingAs($user)->get("/hardware-prices/{$price->id}")->assertOk();
    }

    public function test_hardware_prices_show_is_forbidden_for_other_organisation(): void
    {
        $user = $this->userWithPermission('hardware-prices.view');
        $other = User::factory()->create();
        $price = $this->createHardwarePrice($other);

        $this->actingAs($user)->get("/hardware-prices/{$price->id}")->assertStatus(403);
    }

    public function test_plans_index_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Plan::factory()->create(['is_active' => true, 'is_archived' => false]);

        $this->actingAs($user)->get('/plans')->assertOk();
    }

    public function test_subscriptions_index_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true, 'is_archived' => false]);
        Subscription::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/subscriptions')->assertOk();
    }
}