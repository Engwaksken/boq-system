<?php

namespace Tests\Feature;

use App\Livewire\HardwarePrices\Index as HardwarePricesIndex;
use App\Livewire\Profile\Index as ProfileIndex;
use App\Models\HardwarePrice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UiRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function hardwarePrice(User $user, array $attributes = []): HardwarePrice
    {
        return HardwarePrice::create($attributes + [
            'organisation_id' => $user->organisation_id,
            'item_name' => 'Cement 50kg',
            'category' => 'Cement',
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'unit' => 'bag',
            'price' => 32000,
            'currency' => 'UGX',
            'location' => 'Kampala',
            'supplier' => 'Local Hardware',
            'brand' => 'Hima',
            'specification' => 'Portland',
            'source_reference' => 'test',
            'fetched_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_admin_pages_are_listed_in_the_sidebar_without_tab_bar(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee(route('admin.hardware-scanner'), false)
            ->assertSee('Roles &amp; Permissions', false)
            ->assertDontSee('boq-admin-tabs', false)
            ->assertSee('boq-stat-card', false);
    }

    public function test_regular_users_do_not_see_admin_navigation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.hardware-scanner'), false);
    }

    public function test_user_can_save_and_delete_a_hardware_bookmark_from_profile(): void
    {
        $user = User::factory()->create();
        $price = $this->hardwarePrice($user);

        $component = Livewire::actingAs($user)
            ->test(ProfileIndex::class)
            ->call('setActiveTab', 'hardware-bookmarks')
            ->call('bookmarkHardware')
            ->assertSet('showBookmarkModal', true)
            ->assertSee('Cement 50kg')
            ->set('hardwareBookmarkForm.hardware_price_id', $price->id)
            ->set('hardwareBookmarkForm.location', 'Kampala')
            ->call('saveBookmark')
            ->assertHasNoErrors()
            ->assertSet('showBookmarkModal', false);

        $bookmark = $user->hardwareBookmarks()->sole();
        $this->assertSame($price->id, (int) $bookmark->hardware_price_id);

        $component->call('bookmarkHardware')
            ->set('hardwareBookmarkForm.hardware_price_id', $price->id)
            ->set('hardwareBookmarkForm.location', 'Kampala')
            ->call('saveBookmark')
            ->assertHasErrors('hardwareBookmarkForm.location');

        $component->call('deleteBookmark', $bookmark->id);
        $this->assertSame(0, $user->hardwareBookmarks()->count());
    }

    public function test_user_can_toggle_a_bookmark_from_the_hardware_price_list(): void
    {
        $user = $this->superAdmin();
        $price = $this->hardwarePrice($user);

        $component = Livewire::actingAs($user)
            ->test(HardwarePricesIndex::class)
            ->call('toggleBookmark', $price->id);

        $this->assertDatabaseHas('hardware_bookmarks', [
            'user_id' => $user->id,
            'hardware_price_id' => $price->id,
            'location' => 'Kampala',
        ]);

        $component->call('toggleBookmark', $price->id);
        $this->assertDatabaseMissing('hardware_bookmarks', ['user_id' => $user->id]);
    }

    public function test_cannot_bookmark_another_organisations_price(): void
    {
        $user = $this->superAdmin();
        $other = User::factory()->create(['organisation_id' => \App\Models\Organisation::factory()->create()->id]);
        $price = $this->hardwarePrice($other);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($user)
            ->test(HardwarePricesIndex::class)
            ->call('toggleBookmark', $price->id);
    }
}
