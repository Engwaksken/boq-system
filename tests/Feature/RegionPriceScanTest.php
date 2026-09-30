<?php

namespace Tests\Feature;

use App\Jobs\ScanSupplierPricesJob;
use App\Livewire\Admin\HardwareScanner;
use App\Models\AiProvider;
use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RegionPriceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RegionPriceScanTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]));

        return $user;
    }

    private function supplier(string $name, string $location, string $region, array $attributes = []): Supplier
    {
        return Supplier::create($attributes + ['code' => 'SUP-'.Str::upper(Str::random(6)), 'name' => $name, 'type' => 'supplier',
            'location' => $location, 'region' => $region, 'currency' => 'UGX', 'is_active' => true]);
    }

    private function fakeAi(array $items): void
    {
        $this->provider();
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['items' => $items])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ])]);
    }

    private function provider(): void
    {
        AiProvider::create([
            'key' => 'deepseek', 'name' => 'DeepSeek', 'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1', 'default_model' => 'deepseek-chat',
            'api_key' => 'secret', 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        HardwareCategory::forceCreate(['name' => 'Cement', 'slug' => 'cement', 'default_items' => ['Portland cement 50kg'], 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_regions_and_their_towns_come_from_suppliers_and_prices(): void
    {
        $this->supplier('Gulu Hardware', 'Gulu', 'Northern');
        $this->supplier('Lira Hardware', 'Lira', 'Northern');
        $this->supplier('Mukono Hardware', 'Mukono', 'Central');
        HardwarePrice::create(['organisation_id' => null, 'item_name' => 'Cement', 'category' => 'Cement', 'unit' => 'bag', 'price' => 1,
            'currency' => 'UGX', 'supplier' => 'X', 'location' => 'Arua', 'region' => 'Northern', 'is_active' => true, 'fetched_at' => now()]);

        $this->assertSame(['Central', 'Northern'], RegionPriceScanner::regions());
        $this->assertSame(['Arua', 'Gulu', 'Lira'], RegionPriceScanner::towns('northern'));
        $this->assertSame([], RegionPriceScanner::towns('Nowhere'));
    }

    public function test_admin_scans_prices_of_different_hardwares_across_a_region(): void
    {
        $admin = $this->superAdmin();
        $gulu = $this->supplier('Gulu Hardware', 'Gulu', 'Northern');
        $this->supplier('Lira Hardware', 'Lira', 'Northern');
        $this->fakeAi([
            ['item_name' => 'Portland cement 50kg', 'unit' => 'bag', 'price' => 38000, 'currency' => 'UGX', 'supplier' => 'Gulu Hardware', 'town' => 'gulu'],
            ['item_name' => 'Portland cement 50kg', 'unit' => 'bag', 'price' => 39500, 'currency' => 'UGX', 'supplier' => 'Lira Traders', 'town' => 'Lira'],
            ['item_name' => 'Portland cement 50kg', 'unit' => 'bag', 'price' => 41000, 'currency' => 'UGX', 'supplier' => 'Kitgum Stores', 'town' => 'Kampala'],
            ['item_name' => 'No price', 'unit' => 'bag', 'price' => 0, 'supplier' => 'X', 'town' => 'Gulu'],
        ]);

        Livewire::actingAs($admin)->test(HardwareScanner::class)
            ->set('scanForm.scope', 'region')
            ->set('scanForm.category', 'Cement')
            ->set('scanForm.region', 'Northern')
            ->assertSee('Gulu')
            ->assertSee('Lira')
            ->set('scanForm.towns', ['Gulu', 'Lira'])
            ->set('scanForm.limit', 10)
            ->call('scanPrices')
            ->assertHasNoErrors()
            ->assertSee('Region scan completed: 3 prices from 3 locations in Northern.')
            ->assertSee('Lira Traders');

        $prices = HardwarePrice::where('item_name', 'Portland cement 50kg')->orderBy('price')->get();
        $this->assertCount(3, $prices);
        $this->assertSame(['Gulu', 'Lira', 'Northern'], $prices->pluck('location')->all(), 'A town outside the chosen towns is stored at the region.');
        $this->assertTrue($prices->every(fn ($p) => $p->region === 'Northern' && $p->organisation_id === null));
        $this->assertSame($gulu->id, $prices->first()->supplier_id);
        $this->assertSame('region_scan', $prices->first()->ai_metadata['source']);

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Northern region')
            && str_contains(json_encode($request->data()), 'Gulu, Lira')
            && str_contains(json_encode($request->data()), 'Gulu Hardware'));

        // Scanning again updates the same prices.
        Livewire::actingAs($admin)->test(HardwareScanner::class)
            ->set('scanForm.scope', 'region')->set('scanForm.category', 'Cement')->set('scanForm.region', 'Northern')
            ->set('scanForm.towns', ['Gulu', 'Lira'])->call('scanPrices');
        $this->assertSame(3, HardwarePrice::where('item_name', 'Portland cement 50kg')->count());

        // They can be filtered by region on Get Prices.
        $this->assertSame(3, HardwarePrice::inRegion('Northern')->count());
    }

    public function test_region_scan_can_also_queue_the_regions_supplier_websites(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        $this->supplier('Gulu Hardware', 'Gulu', 'Northern', ['website_url' => 'https://gulu.example']);
        $this->supplier('Lira Hardware', 'Lira', 'Northern', ['website_url' => 'https://lira.example']);
        $this->supplier('No Site', 'Gulu', 'Northern');
        $this->supplier('Gulu Factory', 'Gulu', 'Northern', ['website_url' => 'https://factory.example', 'type' => 'factory']);
        $this->supplier('Mukono Hardware', 'Mukono', 'Central', ['website_url' => 'https://mukono.example']);
        $this->fakeAi([['item_name' => 'Cement', 'unit' => 'bag', 'price' => 38000, 'supplier' => 'Gulu Hardware', 'town' => 'Gulu']]);

        Livewire::actingAs($admin)->test(HardwareScanner::class)
            ->set('scanForm.scope', 'region')
            ->set('scanForm.category', 'Cement')
            ->set('scanForm.region', 'Northern')
            ->assertViewHas('regionWebsites', 2)
            ->set('scanForm.scan_websites', true)
            ->call('scanPrices')
            ->assertSet('queuedWebsites', 2)
            ->assertSee('The websites of 2 suppliers in the region are being scanned too.');

        Queue::assertPushed(ScanSupplierPricesJob::class, 2);
    }

    public function test_region_scan_needs_a_region_and_changing_it_clears_the_towns(): void
    {
        $this->supplier('Gulu Hardware', 'Gulu', 'Northern');

        Livewire::actingAs($this->superAdmin())->test(HardwareScanner::class)
            ->set('scanForm.scope', 'region')
            ->set('scanForm.category', 'Cement')
            ->set('scanForm.region', 'Northern')
            ->call('selectAllTowns')
            ->assertSet('scanForm.towns', ['Gulu'])
            ->set('scanForm.region', 'Central')
            ->assertSet('scanForm.towns', [])
            ->set('scanForm.region', '')
            ->call('scanPrices')
            ->assertHasErrors('scanForm.region');
    }

    public function test_location_scans_still_work_and_show_the_location(): void
    {
        $this->provider();
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['item_name' => 'Portland cement 50kg', 'unit' => 'bag', 'price' => 36000, 'currency' => 'UGX', 'supplier' => 'Hima'])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ])]);

        Livewire::actingAs($this->superAdmin())->test(HardwareScanner::class)
            ->set('scanForm.category', 'Cement')
            ->set('scanForm.location', 'Mbarara')
            ->set('scanForm.limit', 1)
            ->call('scanPrices')
            ->assertHasNoErrors()
            ->assertSee('Mbarara');

        $this->assertSame(1, HardwarePrice::where('location', 'Mbarara')->count());
    }
}
