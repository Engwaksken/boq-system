<?php

namespace Tests\Feature;

use App\Livewire\Admin\PaymentGateways;
use App\Livewire\Admin\PlansManager;
use App\Livewire\Admin\SuppliersManager;
use App\Livewire\Admin\UsersManager;
use App\Livewire\HardwarePrices\Index as HardwarePricesIndex;
use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Models\Feature;
use App\Models\Organisation;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkSelectionAndFormsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_select_all_toggles_the_current_page(): void
    {
        $plans = Plan::factory()->count(3)->create();
        $ids = $plans->modelKeys();

        Livewire::actingAs($this->superAdmin())
            ->test(PlansManager::class)
            ->call('togglePageSelection', $ids)
            ->assertCount('selected', 3)
            ->call('togglePageSelection', $ids)
            ->assertCount('selected', 0);
    }

    public function test_plans_can_be_archived_in_bulk_and_show_statistics(): void
    {
        $admin = $this->superAdmin();
        $plans = Plan::factory()->count(2)->create(['is_active' => true, 'is_archived' => false]);
        $untouched = Plan::factory()->create(['is_active' => true, 'is_archived' => false]);

        $this->actingAs($admin)
            ->get(route('admin.plans'))
            ->assertOk()
            ->assertSee('Total Plans')
            ->assertSee('Active Subscribers');

        Livewire::actingAs($admin)
            ->test(PlansManager::class)
            ->set('selected', array_map('strval', $plans->modelKeys()))
            ->call('bulkArchive')
            ->assertCount('selected', 0);

        foreach ($plans as $plan) {
            $this->assertTrue($plan->fresh()->is_archived);
            $this->assertFalse($plan->fresh()->is_active);
        }

        $this->assertFalse($untouched->fresh()->is_archived);
    }

    public function test_super_admin_can_create_a_plan(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(PlansManager::class)
            ->call('create')
            ->set('form.name', 'Starter Plan')
            ->set('form.code', '')
            ->set('form.type', 'monthly')
            ->set('form.duration_days', 30)
            ->set('form.price', 25000)
            ->set('form.currency', 'UGX')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('plans', [
            'name' => 'Starter Plan',
            'code' => 'starter-plan',
            'price' => 25000,
            'currency' => 'UGX',
        ]);
    }

    public function test_super_admin_can_update_a_plan(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::factory()->create(['name' => 'Original Plan', 'price' => 50000]);

        Livewire::actingAs($admin)
            ->test(PlansManager::class)
            ->call('edit', $plan->id)
            ->set('form.name', 'Updated Plan')
            ->set('form.price', 75000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Updated Plan',
            'price' => 75000,
        ]);
    }

    public function test_plan_feature_selection_saves_active_submitted_ids_and_retains_attached_inactive_ids(): void
    {
        $admin = $this->superAdmin();
        $active = Feature::factory()->create(['is_active' => true]);
        $inactiveAttached = Feature::factory()->create(['is_active' => false]);
        $inactiveUnattached = Feature::factory()->create(['is_active' => false]);
        $plan = Plan::factory()->create();
        $plan->features()->attach($inactiveAttached->id);

        Livewire::actingAs($admin)
            ->test(PlansManager::class)
            ->call('edit', $plan->id)
            ->set('featureIds', [(string) $active->id, (string) $inactiveUnattached->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$active->id, $inactiveAttached->id],
            $plan->fresh()->features()->pluck('features.id')->all()
        );
    }

    public function test_suppliers_can_be_deactivated_in_bulk(): void
    {
        $suppliers = Supplier::factory()->count(2)->create(['is_active' => true]);

        Livewire::actingAs($this->superAdmin())
            ->test(SuppliersManager::class)
            ->set('selected', array_map('strval', $suppliers->modelKeys()))
            ->call('bulkSetActive', false);

        $this->assertSame(0, Supplier::where('is_active', true)->count());
    }

    public function test_bulk_disable_never_disables_the_acting_admin(): void
    {
        $admin = $this->superAdmin();
        $other = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(UsersManager::class)
            ->set('selected', [(string) $admin->id, (string) $other->id])
            ->call('bulkSetActive', false);

        $this->assertTrue((bool) $admin->fresh()->is_active);
        $this->assertFalse((bool) $other->fresh()->is_active);
    }

    public function test_payment_gateway_page_uses_driver_select_and_bulk_toggle(): void
    {
        $admin = $this->superAdmin();
        $gateway = PaymentGateway::create([
            'name' => 'Old Gateway',
            'code' => 'old_gateway',
            'driver' => 'stripe',
            'is_active' => true,
            'is_default' => false,
            'is_test_mode' => true,
            'payment_timeout_seconds' => 900,
            'config' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payment-gateways'))
            ->assertOk()
            ->assertSee('Default Gateway');

        Livewire::actingAs($admin)
            ->test(PaymentGateways::class)
            ->call('create')
            ->assertSee('Driver Configuration')
            ->set('selected', [(string) $gateway->id])
            ->call('bulkSetActive', false);

        $this->assertFalse($gateway->fresh()->is_active);
    }

    public function test_hardware_price_form_offers_categories_and_other_option(): void
    {
        $admin = $this->superAdmin();
        HardwareCategory::forceCreate(['name' => 'Cement', 'slug' => 'cement', 'is_active' => true, 'sort_order' => 1, 'default_items' => ['Portland Cement 50kg']]);

        $component = Livewire::actingAs($admin)
            ->test(HardwarePricesIndex::class)
            ->call('createPrice')
            ->assertSee('Cement')
            ->set('categoryChoice', 'Cement')
            ->assertSet('form.category', 'Cement')
            ->assertSee('Portland Cement 50kg')
            ->set('itemChoice', 'Portland Cement 50kg')
            ->assertSet('form.item_name', 'Portland Cement 50kg');

        $component->set('categoryChoice', '__other__')
            ->assertSet('form.category', '')
            ->assertSet('form.item_name', '');
    }

    public function test_hardware_prices_can_be_deactivated_in_bulk(): void
    {
        $admin = $this->superAdmin();
        $price = HardwarePrice::create([
            'organisation_id' => $admin->organisation_id,
            'item_name' => 'Nails',
            'brand' => 'Local',
            'category' => 'Fasteners',
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'specification' => '4 inch',
            'unit' => 'kg',
            'price' => 8000,
            'currency' => 'UGX',
            'supplier' => 'Hardware Ltd',
            'location' => 'Kampala',
            'source_reference' => 'test',
            'fetched_at' => now(),
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(HardwarePricesIndex::class)
            ->set('selected', [(string) $price->id])
            ->call('bulkSetActive', false);

        $this->assertFalse($price->fresh()->is_active);
    }

    public function test_sidebar_shows_uploaded_logo_and_system_name(): void
    {
        SiteSetting::set('logo', 'site/logo-test.png');
        SiteSetting::set('system_name', 'Kemmy BOQ');

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(asset('storage/site/logo-test.png'), false)
            ->assertSee('Kemmy BOQ');
    }

    public function test_settings_page_renders_tabs(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Branding')
            ->assertSee('Mobile App')
            ->assertSee('Legal');
    }
}
