<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $user->organisation_id]);

        return $user;
    }

    public function test_procurement_officer_can_add_boqs_and_see_prices(): void
    {
        $user = $this->user('procurement-officer');

        $this->assertTrue($user->hasPermission('boq.view'));
        $this->assertTrue($user->hasPermission('boq.edit'));
        $this->assertTrue($user->hasPermission('boq.upload'));
        $this->assertTrue($user->hasPermission('hardware-prices.view'));
    }

    public function test_finance_can_view_projects_and_boqs_but_not_edit(): void
    {
        $user = $this->user('finance');

        $this->assertTrue($user->hasPermission('projects.view'));
        $this->assertTrue($user->hasPermission('boq.view'));
        $this->assertTrue($user->hasPermission('hardware-prices.view'));
        $this->assertFalse($user->hasPermission('boq.edit'));
    }

    public function test_invited_members_do_not_see_billing_in_the_sidebar(): void
    {
        $this->actingAs($this->user('procurement-officer'))->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('plans.index'), false)
            ->assertDontSee(route('topups.index'), false)
            ->assertDontSee('Plans & Billing');

        $this->actingAs($this->user('project-manager'))->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('plans.index'), false);
    }

    public function test_account_owner_still_sees_billing_in_the_sidebar(): void
    {
        $this->actingAs($this->user('user'))->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('plans.index'), false)
            ->assertSee(route('topups.index'), false);
    }
}
