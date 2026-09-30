<?php

namespace Tests\Feature;

use App\Livewire\HardwarePrices\Index as HardwarePricesIndex;
use App\Models\HardwarePrice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class HardwarePriceRegionTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $permission = Permission::firstOrCreate(['slug' => 'hardware-prices.view'], ['name' => 'View prices', 'module' => 'rates']);
        $role = Role::firstOrCreate(['slug' => 'price-viewer'], ['name' => 'Viewer']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => null])->save();
        $user->roles()->attach($role);

        return $user;
    }

    private function price(string $name, ?string $location, array $attributes = []): HardwarePrice
    {
        return HardwarePrice::create($attributes + [
            'organisation_id' => null, 'item_name' => $name, 'category' => 'Cement', 'unit' => 'bag',
            'price' => 35000, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => $location,
            'is_active' => true, 'fetched_at' => now(),
        ]);
    }

    private function supplier(string $name, string $location, string $region): Supplier
    {
        return Supplier::create(['code' => 'SUP-'.Str::upper(Str::random(6)), 'name' => $name, 'type' => 'supplier',
            'location' => $location, 'region' => $region, 'currency' => 'UGX', 'is_active' => true]);
    }

    public function test_prices_get_a_region_from_their_supplier_or_location(): void
    {
        $mukono = $this->supplier('Mukono Hardware', 'Mukono', 'Central');
        $this->supplier('Gulu Hardware', 'Gulu', 'Northern');

        $bySupplier = $this->price('Cement A', 'Seeta', ['supplier_id' => $mukono->id]);
        $byLocation = $this->price('Cement B', 'gulu');
        $byOtherPrice = $this->price('Cement C', 'Seeta');
        $isRegion = $this->price('Cement D', 'Northern');
        $unknown = $this->price('Cement E', 'Nowhere');
        $given = $this->price('Cement F', 'Mbale', ['region' => 'Eastern']);

        $this->assertSame('Central', $bySupplier->region);
        $this->assertSame('Northern', $byLocation->region);
        $this->assertSame('Central', $byOtherPrice->region);
        $this->assertSame('Northern', $isRegion->region);
        $this->assertNull($unknown->region);
        $this->assertSame('Eastern', $given->region);
    }

    public function test_price_list_filters_by_region_and_searches_location_or_region(): void
    {
        $this->supplier('Mukono Hardware', 'Mukono', 'Central');
        $this->supplier('Gulu Hardware', 'Gulu', 'Northern');
        $this->price('Mukono cement', 'Mukono');
        $this->price('Kampala cement', 'Kampala', ['region' => 'Central']);
        $this->price('Gulu cement', 'Gulu');

        Livewire::actingAs($this->viewer())->test(HardwarePricesIndex::class)
            ->assertViewHas('regions', ['Central', 'Northern'])
            ->set('region', 'Central')
            ->assertSee('Mukono cement')
            ->assertSee('Kampala cement')
            ->assertDontSee('Gulu cement')
            ->assertViewHas('locations', fn ($locations) => collect($locations)->sort()->values()->all() === ['Kampala', 'Mukono'])
            // Choosing a region clears a location from another region.
            ->set('location', 'Gulu')
            ->set('region', 'Northern')
            ->assertSet('location', null)
            ->set('region', '')
            // Location search matches part of a location or a region.
            ->set('location', 'orth')
            ->assertSee('Gulu cement')
            ->assertDontSee('Mukono cement')
            ->set('location', 'kono')
            ->assertSee('Mukono cement')
            ->assertDontSee('Gulu cement');
    }

    public function test_api_filters_by_region_and_location_search(): void
    {
        $viewer = $this->viewer();
        $this->supplier('Gulu Hardware', 'Gulu', 'Northern');
        $this->price('Gulu cement', 'Gulu');
        $this->price('Kampala cement', 'Kampala', ['region' => 'Central']);

        $names = fn (string $query) => collect($this->actingAs($viewer, 'sanctum')->getJson('/api/v1/hardware-prices?'.$query)->assertOk()->json('data.data'))->pluck('item_name')->all();

        $this->assertSame(['Gulu cement'], $names('region=Northern'));
        $this->assertSame(['Gulu cement'], $names('location=Northern'));
        $this->assertSame(['Kampala cement'], $names('location=Kampala'));
        $this->assertSame(['Kampala cement'], $names('location_search=entr'));

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/hardware-prices/filters')
            ->assertOk()
            ->assertJsonPath('data.regions', ['Central', 'Northern']);
    }

    public function test_admins_can_set_a_region_on_a_price(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]));
        $price = $this->price('Tiles', 'Jinja');

        Livewire::actingAs($admin)->test(HardwarePricesIndex::class)
            ->call('editPrice', $price->id)
            ->assertSet('form.region', null)
            ->set('form.region', 'Eastern')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Eastern', $price->fresh()->region);
    }
}
