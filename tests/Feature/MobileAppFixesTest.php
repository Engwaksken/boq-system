<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\HardwarePrice;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileAppFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_can_be_filtered_by_status_and_boq_status(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'));
        $make = fn (string $name, string $status) => Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'name' => $name,
            'status' => $status,
        ]);

        $active = $make('Active school', 'active');
        $make('Draft clinic', 'draft');
        $reviewed = $make('Draft road', 'draft');
        Boq::factory()->create(['project_id' => $reviewed->id, 'organisation_id' => $user->organisation_id, 'status' => 'under_review']);

        $names = fn (string $query) => collect($this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects'.$query)
            ->assertOk()
            ->json('data.data'))->pluck('name')->all();

        $this->assertSame([$active->name], $names('?status=active'));
        $this->assertSame([$reviewed->name], $names('?boq_status=under_review'));
        $this->assertCount(3, $names('?per_page=50'));
    }

    public function test_price_filters_list_distinct_suppliers_and_locations(): void
    {
        $permission = Permission::factory()->create(['slug' => 'hardware-prices.view']);
        $role = Role::factory()->create(['slug' => 'price-viewer']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $price = fn (array $attributes) => HardwarePrice::create($attributes + [
            'organisation_id' => $user->organisation_id,
            'item_name' => 'Cement 50kg',
            'category' => 'Cement',
            'unit' => 'bag',
            'price' => 35000,
            'currency' => 'UGX',
            'is_active' => true,
            'fetched_at' => now(),
        ]);

        $price(['supplier' => 'Hima', 'location' => 'Kampala']);
        $price(['supplier' => 'Tororo Cement', 'location' => 'Kampala']);
        $price(['supplier' => 'Hima', 'location' => 'Mbarara']);
        $price(['supplier' => 'Hidden', 'location' => 'Gulu', 'is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hardware-prices/filters')
            ->assertOk()
            ->assertJsonPath('data.suppliers', ['Hima', 'Tororo Cement'])
            ->assertJsonPath('data.locations', ['Kampala', 'Mbarara']);
    }

    public function test_user_can_upload_and_remove_a_profile_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk();

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($response->json('data.user.avatar_url'));

        // Replacing deletes the old picture.
        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/avatar', ['avatar' => UploadedFile::fake()->image('new.jpg')], ['Accept' => 'application/json'])
            ->assertOk();
        Storage::disk('public')->assertMissing($path);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/auth/avatar')->assertOk()
            ->assertJsonPath('data.user.avatar_url', null);
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_profile_picture_must_be_an_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/avatar', ['avatar' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }
}
