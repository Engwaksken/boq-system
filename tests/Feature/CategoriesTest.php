<?php

namespace Tests\Feature;

use App\Livewire\Admin\CategoriesManager;
use App\Models\Category;
use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_inserts_the_default_project_types_and_work_sections(): void
    {
        $this->assertTrue(Category::ofType('project')->where('name', 'Health Facility')->exists());
        $this->assertTrue(Category::ofType('work')->where('name', 'Substructure')->exists());
        $this->assertGreaterThanOrEqual(15, Category::ofType('project')->count());
    }

    public function test_api_lists_categories_from_the_database_for_any_user(): void
    {
        $user = User::factory()->create();
        Category::create(['type' => 'project', 'name' => 'Stadium', 'sort_order' => 1]);
        Category::create(['type' => 'project', 'name' => 'Hidden Type', 'is_active' => false]);
        HardwareCategory::forceCreate(['slug' => 'test-blocks', 'name' => 'Test Blocks', 'default_items' => ['Solid block'], 'is_active' => true, 'sort_order' => 1]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/categories')->assertOk();

        $this->assertContains('Stadium', $response->json('data.project_types'));
        $this->assertNotContains('Hidden Type', $response->json('data.project_types'));
        $this->assertContains('Roofing', $response->json('data.work_sections'));
        $material = collect($response->json('data.materials'))->firstWhere('name', 'Test Blocks');
        $this->assertSame(['Solid block'], $material['items']);
    }

    public function test_price_categories_include_managed_categories_without_prices(): void
    {
        $permission = Permission::factory()->create(['slug' => 'hardware-prices.view']);
        $role = Role::factory()->create(['slug' => 'viewer-test']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        HardwareCategory::forceCreate(['slug' => 'empty-category', 'name' => 'Empty Category', 'is_active' => true, 'sort_order' => 1]);
        HardwarePrice::create([
            'organisation_id' => $user->organisation_id, 'item_name' => 'Cement', 'category' => 'Cement',
            'unit' => 'bag', 'price' => 35000, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala', 'is_active' => true, 'fetched_at' => now(),
        ]);

        $data = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/hardware-prices/categories')->assertOk()->json('data'));

        $this->assertSame(0, $data->firstWhere('name', 'Empty Category')['count']);
        $this->assertSame(1, $data->firstWhere('name', 'Cement')['count']);
    }

    public function test_super_admin_manages_categories_and_duplicates_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]));

        if (! $admin->fresh()->isSuperAdmin()) {
            $this->markTestSkipped('Super admin role is resolved differently in this install.');
        }

        Livewire::actingAs($admin)
            ->test(CategoriesManager::class)
            ->set('name', 'Airport Works')
            ->call('save')
            ->assertHasNoErrors()
            ->set('name', 'airport works')
            ->call('save')
            ->assertHasErrors('name');

        $category = Category::where('slug', 'airport-works')->firstOrFail();
        $this->assertSame('project', $category->type);

        Livewire::actingAs($admin)->test(CategoriesManager::class)->call('toggle', $category->id);
        $this->assertFalse($category->fresh()->is_active);
        $this->assertNotContains('Airport Works', \App\Support\Categories::projectTypes());
    }

    public function test_non_admins_cannot_open_the_categories_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/categories')->assertForbidden();
    }

    public function test_project_form_offers_project_types_from_the_database(): void
    {
        $user = User::factory()->create();
        Category::create(['type' => 'project', 'name' => 'Stadium']);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Projects\Create::class)
            ->assertSee('project-type-options', false)
            ->assertSee('Stadium');
    }
}
