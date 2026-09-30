<?php

namespace Tests\Feature;

use App\Jobs\ScanSupplierPricesJob;
use App\Livewire\Admin\SuppliersManager;
use App\Models\AiProvider;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]));

        return $user;
    }

    private function fakeSearch(array $suppliers, int $status = 200): void
    {
        AiProvider::create([
            'key' => 'deepseek', 'name' => 'DeepSeek', 'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1', 'default_model' => 'deepseek-chat',
            'api_key' => 'secret', 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['suppliers' => $suppliers])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ], $status)]);
    }

    private function results(): array
    {
        return [
            ['name' => 'Mbarara Hardware Centre', 'type' => 'supplier', 'phone' => '+256 700 123 456', 'website_url' => 'mbararahardware.example', 'location' => 'Mbarara', 'address' => 'High Street', 'country' => 'UG', 'materials' => 'cement, iron sheets'],
            ['name' => 'Western Steel Mills', 'type' => 'manufacturer', 'email' => 'sales@westernsteel.example', 'location' => 'Mbarara'],
            // Already in the list, by website.
            ['name' => 'Ankole Builders Mart', 'type' => 'supplier', 'website_url' => 'https://www.ankole-mart.example/shop'],
            // A malformed phone from the AI: the business is kept, the phone dropped.
            ['name' => 'Rwizi Blocks', 'type' => 'factory', 'phone' => 'call us'],
            ['name' => '', 'type' => 'supplier'],
        ];
    }

    public function test_admin_finds_suppliers_online_and_adds_the_selected_new_ones(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        Supplier::create(['code' => 'SUP-'.Str::upper(Str::random(6)), 'name' => 'Ankole Mart', 'type' => 'supplier', 'website_url' => 'https://ankole-mart.example', 'currency' => 'UGX', 'is_active' => true]);
        $this->fakeSearch($this->results());

        $component = Livewire::actingAs($admin)
            ->test(SuppliersManager::class)
            ->call('openDiscover')
            ->set('discoverLocation', 'Mbarara')
            ->call('discover')
            ->assertHasNoErrors()
            ->assertSee('Mbarara Hardware Centre')
            ->assertSee('Already added');

        $results = collect($component->get('discoverResults'));
        $this->assertCount(4, $results);
        $this->assertNotNull($results->firstWhere('name', 'Ankole Builders Mart')['duplicate']);
        $this->assertSame('factory', $results->firstWhere('name', 'Western Steel Mills')['type']);
        // New ones are ticked by default, duplicates are not.
        $this->assertCount(3, $component->get('discoverSelected'));

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Mbarara'));

        // Untick one and add the rest.
        $keep = $results->whereIn('name', ['Mbarara Hardware Centre', 'Rwizi Blocks'])->pluck('key')->values()->all();
        $component->set('discoverSelected', $keep)
            ->set('discoverScan', true)
            ->call('addDiscovered')
            ->assertHasNoErrors()
            ->assertSet('showDiscover', false);

        $hardware = Supplier::where('name', 'Mbarara Hardware Centre')->firstOrFail();
        $this->assertSame('https://mbararahardware.example', $hardware->website_url);
        $this->assertSame('Mbarara', $hardware->location);
        $this->assertSame('UG', $hardware->country);
        $this->assertSame(['cement', 'iron sheets'], $hardware->materials);
        $this->assertSame($admin->id, $hardware->created_by);

        $blocks = Supplier::where('name', 'Rwizi Blocks')->firstOrFail();
        $this->assertSame(Supplier::TYPE_FACTORY, $blocks->type);
        $this->assertNull($blocks->phone);

        $this->assertFalse(Supplier::where('name', 'Western Steel Mills')->exists());
        $this->assertFalse(Supplier::where('name', 'Ankole Builders Mart')->exists());
        $this->assertSame(3, Supplier::count());

        // Only the one with a website is scanned for prices.
        Queue::assertPushed(ScanSupplierPricesJob::class, 1);
    }

    public function test_duplicates_cannot_be_added_even_if_forced(): void
    {
        $admin = $this->superAdmin();
        Supplier::create(['code' => 'SUP-'.Str::upper(Str::random(6)), 'name' => 'Ankole Mart', 'type' => 'supplier', 'website_url' => 'https://ankole-mart.example', 'currency' => 'UGX', 'is_active' => true]);
        $this->fakeSearch($this->results());

        $component = Livewire::actingAs($admin)->test(SuppliersManager::class)
            ->call('openDiscover')->set('discoverLocation', 'Mbarara')->call('discover');
        $all = collect($component->get('discoverResults'))->pluck('key')->all();

        $component->set('discoverSelected', [collect($component->get('discoverResults'))->firstWhere('name', 'Ankole Builders Mart')['key']])
            ->call('addDiscovered')
            ->assertHasErrors('discoverSelected');
        $this->assertSame(1, Supplier::count());

        // Adding everything twice creates each business once.
        $component->set('discoverSelected', $all)->set('discoverScan', false)->call('addDiscovered');
        $this->assertSame(4, Supplier::count());

        $again = Livewire::actingAs($admin)->test(SuppliersManager::class)
            ->call('openDiscover')->set('discoverLocation', 'Mbarara')->call('discover');
        $this->assertSame([], $again->get('discoverSelected'));
        $this->assertTrue(collect($again->get('discoverResults'))->every(fn ($r) => $r['duplicate'] !== null));
    }

    public function test_search_needs_a_location_and_reports_ai_failures(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(SuppliersManager::class)
            ->call('openDiscover')->set('discoverLocation', '')->call('discover')
            ->assertHasErrors('discoverLocation');

        $this->fakeSearch([], 402);
        Livewire::actingAs($admin)->test(SuppliersManager::class)
            ->call('openDiscover')->set('discoverLocation', 'Gulu')->call('discover')
            ->assertHasErrors('discoverLocation')
            ->assertSet('discoverResults', null);
    }

    public function test_an_empty_search_shows_a_hint(): void
    {
        $this->fakeSearch([]);

        Livewire::actingAs($this->superAdmin())->test(SuppliersManager::class)
            ->call('openDiscover')->set('discoverLocation', 'Nowhere')->call('discover')
            ->assertHasNoErrors()
            ->assertSet('discoverResults', [])
            ->assertSee('No suppliers were found in this location.');
    }
}
