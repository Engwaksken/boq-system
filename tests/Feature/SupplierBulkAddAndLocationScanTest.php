<?php

namespace Tests\Feature;

use App\Jobs\ScanSupplierPricesJob;
use App\Livewire\Admin\SuppliersManager;
use App\Models\AiProvider;
use App\Models\HardwarePrice;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierCsvImporter;
use App\Services\SupplierScanQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierBulkAddAndLocationScanTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]));

        return $user;
    }

    private function supplier(string $name, array $attributes = []): Supplier
    {
        return Supplier::create($attributes + [
            'code' => 'SUP-'.Str::upper(Str::random(6)),
            'name' => $name,
            'type' => Supplier::TYPE_SUPPLIER,
            'currency' => 'UGX',
            'is_active' => true,
        ]);
    }

    private function fakeAi(): void
    {
        AiProvider::create([
            'key' => 'deepseek', 'name' => 'DeepSeek', 'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1', 'default_model' => 'deepseek-chat',
            'api_key' => 'secret', 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['items' => [
                ['item_name' => 'Cement 50kg', 'category' => 'Cement', 'unit' => 'bag', 'price' => 36000, 'currency' => 'UGX'],
            ]])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ])]);
    }

    /* ------------------------------ add many ------------------------------ */

    public function test_admin_adds_many_suppliers_from_the_table_and_bad_rows_stay_for_fixing(): void
    {
        $admin = $this->superAdmin();
        $this->supplier('Existing Hardware');

        $component = Livewire::actingAs($admin)
            ->test(SuppliersManager::class)
            ->call('openBulkAdd')
            ->assertSet('showBulkAdd', true)
            ->assertCount('bulkRows', 5)
            ->set('bulkScan', false)
            ->set('bulkRows.0.name', 'Kampala Steel')
            ->set('bulkRows.0.phone', '+256 700 111 222')
            ->set('bulkRows.0.location', 'Kampala')
            ->set('bulkRows.1.name', 'Jinja Cement Works')
            ->set('bulkRows.1.type', 'factory')
            ->set('bulkRows.1.website_url', 'jinjacement.example')
            ->set('bulkRows.1.materials', 'cement, lime')
            ->set('bulkRows.2.name', 'existing hardware')
            ->set('bulkRows.3.name', 'Bad Email Ltd')
            ->set('bulkRows.3.email', 'not-an-email')
            ->call('saveBulk');

        $this->assertSame(1, Supplier::where('name', 'Kampala Steel')->where('location', 'Kampala')->count());
        $factory = Supplier::where('name', 'Jinja Cement Works')->firstOrFail();
        $this->assertSame(Supplier::TYPE_FACTORY, $factory->type);
        $this->assertStringStartsWith('FAC-', $factory->code);
        $this->assertSame('https://jinjacement.example', $factory->website_url);
        $this->assertSame(['cement', 'lime'], $factory->materials);
        $this->assertSame($admin->id, $factory->created_by);

        // The duplicate and the invalid row are kept with their reasons.
        $component->assertSet('showBulkAdd', true)->assertHasErrors('bulkRows');
        $this->assertSame(['existing hardware', 'Bad Email Ltd'], array_column($component->get('bulkRows'), 'name'));
        $this->assertStringContainsString('Already exists', implode(' ', $component->get('bulkErrors')[0]));
        $this->assertStringContainsString('Email', implode(' ', $component->get('bulkErrors')[1]));

        // Fixing the row saves it and closes the dialog.
        $component->call('removeBulkRow', 0)
            ->set('bulkRows.0.email', 'sales@bad-email.example')
            ->call('saveBulk')
            ->assertSet('showBulkAdd', false);
        $this->assertSame(1, Supplier::where('email', 'sales@bad-email.example')->count());
    }

    public function test_pasted_rows_from_a_spreadsheet_fill_the_table(): void
    {
        $admin = $this->superAdmin();
        $paste = "Supplier Name\tPhone\tWebsite\tDistrict/City\tSupplier Type\n"
            ."Mbarara Hardware\t+256 700 000 010\tmbarara-hw.example\tMbarara\tsupplier\n"
            ."Gulu Blocks\t+256 700 000 011\t\tGulu\tfactory\n";

        $component = Livewire::actingAs($admin)
            ->test(SuppliersManager::class)
            ->call('openBulkAdd')
            ->set('bulkPaste', $paste)
            ->call('fillFromPaste')
            ->assertHasNoErrors();

        $rows = $component->get('bulkRows');
        $this->assertCount(2, $rows);
        $this->assertSame('Mbarara Hardware', $rows[0]['name']);
        $this->assertSame('Mbarara', $rows[0]['location']);
        $this->assertSame('factory', $rows[1]['type']);

        $component->set('bulkScan', false)->call('saveBulk')->assertSet('showBulkAdd', false);
        $this->assertSame(2, Supplier::whereIn('name', ['Mbarara Hardware', 'Gulu Blocks'])->count());
    }

    public function test_a_plain_comma_list_uses_the_template_column_order(): void
    {
        $rows = app(SupplierCsvImporter::class)->parseList("Acme Ltd, Jane, +256 700 000 001, jane@acme.example, acme.example, UG, Kampala\nBeta Ltd");

        $this->assertCount(2, $rows);
        $this->assertSame(['line' => 1, 'name' => 'Acme Ltd', 'contact_name' => 'Jane', 'phone' => '+256 700 000 001', 'email' => 'jane@acme.example', 'website_url' => 'acme.example', 'country' => 'UG', 'location' => 'Kampala'], $rows[0]);
        $this->assertSame('Beta Ltd', $rows[1]['name']);
    }

    public function test_new_suppliers_with_websites_are_scanned_after_adding(): void
    {
        $admin = $this->superAdmin();
        $this->fakeAi();

        Livewire::actingAs($admin)
            ->test(SuppliersManager::class)
            ->call('openBulkAdd')
            ->set('bulkRows.0.name', 'Web Hardware')
            ->set('bulkRows.0.website_url', 'webhardware.example')
            ->set('bulkRows.1.name', 'No Website Ltd')
            ->set('bulkScan', true)
            ->call('saveBulk')
            ->assertSet('showBulkAdd', false);

        $supplier = Supplier::where('name', 'Web Hardware')->firstOrFail();
        $this->assertSame(1, HardwarePrice::where('supplier_id', $supplier->id)->whereNull('organisation_id')->count());
        $this->assertSame('done', data_get($supplier->metadata, 'price_scan.status'));
    }

    /* ---------------------------- location scans --------------------------- */

    public function test_location_filter_and_scan_cover_only_suppliers_in_the_location(): void
    {
        $admin = $this->superAdmin();
        Queue::fake();
        $kampala = $this->supplier('Kampala Hardware', ['location' => 'Kampala', 'website_url' => 'https://kla.example']);
        $region = $this->supplier('Central Blocks', ['region' => 'Kampala Metropolitan', 'website_url' => 'https://central.example', 'type' => Supplier::TYPE_FACTORY]);
        $this->supplier('Kampala No Site', ['location' => 'Kampala']);
        $this->supplier('Kampala Inactive', ['location' => 'Kampala', 'website_url' => 'https://off.example', 'is_active' => false]);
        $this->supplier('Gulu Hardware', ['location' => 'Gulu', 'website_url' => 'https://gulu.example']);

        Livewire::actingAs($admin)
            ->test(SuppliersManager::class)
            ->set('locationFilter', 'Kampala')
            ->assertSee('Kampala Hardware')
            ->assertSee('Central Blocks')
            ->assertDontSee('Gulu Hardware')
            ->call('openLocationScan')
            ->assertSet('scanLocation', 'Kampala')
            ->assertViewHas('scanCount', 2)
            ->call('scanLocationSuppliers')
            ->assertHasNoErrors()
            ->assertSet('showLocationScan', false)
            ->assertSee('Waiting to scan');

        Queue::assertPushed(ScanSupplierPricesJob::class, 2);
        Queue::assertPushed(ScanSupplierPricesJob::class, fn ($job) => $job->supplierId === $kampala->id);
        Queue::assertPushed(ScanSupplierPricesJob::class, fn ($job) => $job->supplierId === $region->id);
        $this->assertSame(2, app(SupplierScanQueue::class)->pendingCount());

        // Queuing the same location again does not scan twice.
        Livewire::actingAs($admin)->test(SuppliersManager::class)
            ->call('openLocationScan')->set('scanLocation', 'Kampala')->call('scanLocationSuppliers');
        Queue::assertPushed(ScanSupplierPricesJob::class, 2);

        // Factories only.
        $this->assertSame(1, SupplierScanQueue::scannable('Kampala', Supplier::TYPE_FACTORY)->count());
    }

    public function test_a_location_without_scannable_suppliers_shows_an_error(): void
    {
        Queue::fake();
        $this->supplier('Gulu No Site', ['location' => 'Gulu']);

        Livewire::actingAs($this->superAdmin())
            ->test(SuppliersManager::class)
            ->call('openLocationScan')
            ->set('scanLocation', 'Gulu')
            ->call('scanLocationSuppliers')
            ->assertHasErrors('scanLocation')
            ->assertSet('showLocationScan', true);

        Queue::assertNothingPushed();
    }

    public function test_selected_suppliers_are_scanned_and_the_page_runs_scans_the_worker_missed(): void
    {
        $admin = $this->superAdmin();
        $this->fakeAi();
        Queue::fake();
        $a = $this->supplier('Alpha Hardware', ['location' => 'Mbale', 'website_url' => 'https://alpha.example']);
        $b = $this->supplier('Beta Hardware', ['location' => 'Mbale']);

        Livewire::actingAs($admin)
            ->test(SuppliersManager::class)
            ->set('selected', [(string) $a->id, (string) $b->id])
            ->call('bulkScan')
            ->assertSet('selected', []);

        Queue::assertPushed(ScanSupplierPricesJob::class, 1);
        $this->assertSame('queued', data_get($a->fresh()->metadata, 'price_scan.status'));

        // Not picked up by a worker yet: the page leaves it alone until it stalls...
        Livewire::actingAs($admin)->test(SuppliersManager::class)->call('runQueuedScans');
        $this->assertSame('queued', data_get($a->fresh()->metadata, 'price_scan.status'));

        // ...then runs it itself.
        $this->travel(SupplierScanQueue::STALL_SECONDS + 5)->seconds();
        Livewire::actingAs($admin)->test(SuppliersManager::class)->call('runQueuedScans');

        $this->assertSame('done', data_get($a->fresh()->metadata, 'price_scan.status'));
        $this->assertSame(1, HardwarePrice::where('supplier_id', $a->id)->where('location', 'Mbale')->count());
        $this->assertSame(0, app(SupplierScanQueue::class)->pendingCount());

        // The job does nothing once the scan is done.
        (new ScanSupplierPricesJob($a->id))->handle(app(SupplierScanQueue::class));
        Http::assertSentCount(1);
    }

    public function test_only_super_admins_can_use_the_suppliers_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.suppliers'))->assertForbidden();
    }
}
