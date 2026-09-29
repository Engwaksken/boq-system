<?php

namespace Tests\Feature;

use App\Livewire\Admin\SuppliersManager;
use App\Models\AiProvider;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\HardwarePrice;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PriceMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GeneralPricesAndSupplierScanTest extends TestCase
{
    use RefreshDatabase;

    private function price(?int $organisationId, string $name, array $attributes = []): HardwarePrice
    {
        return HardwarePrice::create($attributes + [
            'organisation_id' => $organisationId,
            'item_name' => $name,
            'category' => 'Cement',
            'unit' => 'bag',
            'price' => 35000,
            'currency' => 'UGX',
            'supplier' => 'Hima',
            'location' => 'Kampala',
            'is_active' => true,
            'fetched_at' => now(),
        ]);
    }

    private function priceViewer(?int $organisationId): User
    {
        $permission = Permission::firstOrCreate(['slug' => 'hardware-prices.view'], ['name' => 'View prices', 'module' => 'rates']);
        $role = Role::firstOrCreate(['slug' => 'price-viewer-'.($organisationId ?? 'none')], ['name' => 'Viewer']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => $organisationId])->save();
        $user->roles()->attach($role);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]));

        return $user;
    }

    public function test_general_prices_are_visible_to_everyone_and_private_prices_only_to_their_organisation(): void
    {
        $other = Organisation::factory()->create();
        $mine = Organisation::factory()->create();
        $this->price(null, 'General cement');
        $this->price($mine->id, 'My private cement');
        $this->price($other->id, 'Other private cement');

        $names = fn (User $user) => collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/hardware-prices')->assertOk()->json('data.data'))->pluck('item_name')->sort()->values()->all();

        $this->assertSame(['General cement'], $names($this->priceViewer(null)));
        $this->assertSame(['General cement', 'My private cement'], $names($this->priceViewer($mine->id)));
    }

    public function test_boq_pricing_matches_general_prices_for_personal_boqs(): void
    {
        $this->price(null, 'Portland Cement 42.5N 50kg');
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => null])->save();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => null, 'location' => 'Kampala']);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => null, 'currency' => 'UGX']);
        $item = BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Portland Cement 42.5N 50kg', 'currency' => 'UGX', 'unit' => 'bag']);

        $matches = app(PriceMatchingService::class)->findMatches($item, 5, 'Kampala');

        $this->assertNotEmpty($matches);
        $this->assertSame('Portland Cement 42.5N 50kg', $matches->first()['hardware_price']->item_name);
    }

    public function test_migration_turns_super_admin_prices_into_general_prices(): void
    {
        $admin = $this->superAdmin();
        $customerOrg = Organisation::factory()->create();
        $adminPrice = $this->price($admin->organisation_id, 'Admin scanned cement');
        $privatePrice = $this->price($customerOrg->id, 'Customer cement');

        (require database_path('migrations/2026_09_29_000030_allow_general_market_prices.php'))->up();

        $this->assertNull($adminPrice->fresh()->organisation_id);
        $this->assertSame($customerOrg->id, $privatePrice->fresh()->organisation_id);
    }

    public function test_scanning_a_supplier_website_adds_its_items_to_the_general_prices(): void
    {
        $admin = $this->superAdmin();
        AiProvider::create([
            'key' => 'deepseek', 'name' => 'DeepSeek', 'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1', 'default_model' => 'deepseek-chat',
            'api_key' => 'secret', 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['items' => [
                ['item_name' => 'Deformed bar Y12', 'category' => 'Steel', 'unit' => 'piece', 'price' => 42000, 'currency' => 'UGX', 'source_url' => 'https://roofings.example/y12'],
                ['item_name' => 'Iron sheet G28', 'category' => 'Roofing', 'unit' => 'sheet', 'price' => 38000, 'currency' => 'UGX'],
                ['item_name' => 'No price item', 'category' => 'Steel', 'unit' => 'piece', 'price' => 0],
            ]])]]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 80],
        ])]);

        Livewire::actingAs($admin)
            ->test(SuppliersManager::class)
            ->call('create', Supplier::TYPE_FACTORY)
            ->set('form.name', 'Roofings Ltd')
            ->set('form.website_url', 'roofings.example')
            ->set('form.location', 'Kampala')
            ->set('form.currency', 'UGX')
            ->set('form.preferred_language', 'en')
            ->set('scanAfterSave', true)
            ->call('save')
            ->assertHasNoErrors();

        $supplier = Supplier::where('name', 'Roofings Ltd')->firstOrFail();
        $prices = HardwarePrice::where('supplier_id', $supplier->id)->orderBy('item_name')->get();

        $this->assertSame(['Deformed bar Y12', 'Iron sheet G28'], $prices->pluck('item_name')->all());
        $this->assertTrue($prices->every(fn ($p) => $p->organisation_id === null && $p->price_type === HardwarePrice::TYPE_FACTORY));
        $this->assertSame('https://roofings.example/y12', $prices->first()->source_url);
        $this->assertSame(2, data_get($supplier->fresh()->metadata, 'last_price_scan.created'));

        // Customers without an organisation see the scanned prices.
        $viewer = $this->priceViewer(null);
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/hardware-prices?supplier=Roofings%20Ltd')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        // Scanning again updates instead of duplicating.
        Livewire::actingAs($admin)->test(SuppliersManager::class)->call('scanPrices', $supplier->id);
        $this->assertSame(2, HardwarePrice::where('supplier_id', $supplier->id)->count());
    }
}
