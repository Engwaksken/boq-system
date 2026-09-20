<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsersManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_user(): void
    {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach($superAdminRole);

        $organisation = Organisation::firstOrCreate(['code' => 'DEFAULT'], [
            'name' => 'Default Organisation',
            'email' => 'admin@example.com',
            'default_locale' => 'en',
            'default_currency' => 'UGX',
            'is_active' => true,
        ]);
        $adminRole = Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']);

        $this->actingAs($superAdmin)
            ->get(route('admin.users'))
            ->assertStatus(200);

        Livewire::actingAs($superAdmin)
            ->test(\App\Livewire\Admin\UsersManager::class)
            ->set('newName', 'Jane Doe')
            ->set('newEmail', 'jane@example.com')
            ->set('newPassword', 'password123')
            ->set('newPhone', '+256700000000')
            ->set('newRoleId', $adminRole->id)
            ->set('newOrganisationId', $organisation->id)
            ->call('createUser');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'name' => 'Jane Doe']);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertEquals($organisation->id, $user->organisation_id);
        $this->assertTrue($user->hasRole('administrator'));
    }

    public function test_create_user_rejects_duplicate_email(): void
    {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach($superAdminRole);

        $organisation = Organisation::firstOrCreate(['code' => 'DEFAULT'], [
            'name' => 'Default Organisation',
            'email' => 'admin@example.com',
            'default_locale' => 'en',
            'default_currency' => 'UGX',
            'is_active' => true,
        ]);
        $adminRole = Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']);
        User::factory()->create(['email' => 'jane@example.com']);

        Livewire::actingAs($superAdmin)
            ->test(\App\Livewire\Admin\UsersManager::class)
            ->set('newName', 'Jane Doe')
            ->set('newEmail', 'jane@example.com')
            ->set('newPassword', 'password123')
            ->set('newRoleId', $adminRole->id)
            ->set('newOrganisationId', $organisation->id)
            ->call('createUser');

        $this->assertEquals(1, User::where('email', 'jane@example.com')->count());
    }
}