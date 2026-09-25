<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardwareCategoriesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_get_hardware_price_categories(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/hardware-prices/categories');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertIsArray($response->json('data'));
    }

    public function test_unauthenticated_user_cannot_get_hardware_price_categories(): void
    {
        $response = $this->getJson('/api/v1/hardware-prices/categories');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_hardware_categories(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/hardware-categories');

        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_list_hardware_categories(): void
    {
        $permission = Permission::factory()->create(['slug' => 'hardware-prices.view']);
        $role = Role::factory()->create(['slug' => 'hardware-viewer']);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/hardware-categories');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}