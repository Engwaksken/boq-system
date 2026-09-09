<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_permission_via_role(): void
    {
        $permission = Permission::factory()->create(['slug' => 'projects.view']);
        $role = Role::factory()->create(['slug' => 'project-manager']);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('projects.view'));
        $this->assertTrue($user->hasAnyPermission(['projects.view', 'other']));
        $this->assertTrue($user->hasAllPermissions(['projects.view']));
    }

    public function test_user_has_direct_permission(): void
    {
        $permission = Permission::factory()->create(['slug' => 'boq.upload']);
        $user = User::factory()->create();
        $user->permissions()->attach($permission);

        $this->assertTrue($user->hasPermission('boq.upload'));
    }

    public function test_user_without_permission_does_not_have_it(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasPermission('projects.view'));
        $this->assertFalse($user->hasAnyPermission(['projects.view']));
        $this->assertFalse($user->hasAllPermissions(['projects.view', 'boq.view']));
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $superAdminRole = Role::factory()->create(['slug' => 'super-admin']);
        $permission = Permission::factory()->create(['slug' => 'anything.at.all']);
        $superAdminRole->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($superAdminRole);

        $this->assertTrue($user->hasPermission('anything.at.all'));
        $this->assertTrue($user->hasPermission('some.other.permission'));
    }

    public function test_get_all_permission_slugs(): void
    {
        $permission1 = Permission::factory()->create(['slug' => 'projects.view']);
        $permission2 = Permission::factory()->create(['slug' => 'boq.view']);
        $role = Role::factory()->create(['slug' => 'manager']);
        $role->permissions()->attach([$permission1->id, $permission2->id]);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $slugs = $user->getAllPermissionSlugs();
        $this->assertContains('projects.view', $slugs);
        $this->assertContains('boq.view', $slugs);
    }

    public function test_check_permission_middleware_denies_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/projects');

        // Without permission, should be 403
        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'error_code' => 'FORBIDDEN']);
    }

    public function test_check_permission_middleware_allows_with_permission(): void
    {
        $permission = Permission::factory()->create(['slug' => 'projects.view']);
        $role = Role::factory()->create(['slug' => 'project-manager']);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/projects');

        // With permission, should pass middleware (200 or 404 if no projects)
        $response->assertStatus(200);
    }

    public function test_user_without_role_is_denied_from_protected_route(): void
    {
        $user = User::factory()->create();

        // A user with no roles/permissions must be denied access to a permission-protected route.
        $response = $this->actingAs($user)
            ->getJson('/api/v1/projects');

        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'error_code' => 'FORBIDDEN']);
    }
}
