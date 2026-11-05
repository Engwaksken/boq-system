<?php

namespace Tests\Feature;

use App\Livewire\HardwarePrices\Changes;
use App\Models\HardwarePrice;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class HardwarePriceChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function viewer(?int $organisationId): User
    {
        $user = User::factory()->create(['organisation_id' => $organisationId]);
        $user->permissions()->attach(Permission::firstOrCreate(['slug' => 'hardware-prices.view'], ['name' => 'View prices', 'module' => 'rates']));

        return $user;
    }

    /** A price changed from $from to $to, $daysAgo days ago. */
    private function changed(string $name, float $from, float $to, int $daysAgo, ?int $organisationId = null): HardwarePrice
    {
        Carbon::setTestNow(now()->subDays($daysAgo));
        $price = HardwarePrice::create([
            'organisation_id' => $organisationId, 'item_name' => $name, 'category' => 'Cement', 'unit' => 'bag',
            'price' => $from, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala', 'is_active' => true,
            'price_type' => HardwarePrice::TYPE_HARDWARE, 'fetched_at' => now(),
        ]);
        $price->update(['price' => $to]);
        Carbon::setTestNow();

        return $price;
    }

    public function test_lists_recent_changes_with_period_direction_and_source_filters(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->viewer($org->id);

        $this->changed('Cement up', 40000, 44000, 3);              // +10%
        $this->changed('Sand down', 100000, 90000, 10);            // -10%
        $this->changed('Old nails', 5000, 6000, 60);               // outside 30 days
        $this->changed('Our blocks', 2000, 2500, 1, $org->id);     // +25%, my organisation
        $this->changed('Their paint', 1000, 2000, 1, Organisation::factory()->create()->id); // not visible
        HardwarePrice::create([
            'organisation_id' => null, 'item_name' => 'Unchanged', 'category' => 'Cement', 'unit' => 'bag', 'price' => 1,
            'currency' => 'UGX', 'supplier' => 'X', 'is_active' => true, 'price_type' => 'hardware', 'fetched_at' => now(),
        ]);

        $names = fn ($c) => $c->viewData('changes')->pluck('item_name')->all();

        $component = Livewire::actingAs($user)->test(Changes::class);
        // Default: last 30 days, newest change first.
        $this->assertSame(['Our blocks', 'Cement up', 'Sand down'], $names($component));
        $stats = $component->viewData('stats');
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['up']);
        $this->assertSame(1, $stats['down']);

        $component->set('direction', 'down');
        $this->assertSame(['Sand down'], $names($component));

        $component->set('direction', '')->set('sort', 'up');
        $this->assertSame(['Our blocks', 'Cement up', 'Sand down'], $names($component));

        $component->set('source', 'general');
        $this->assertSame(['Cement up', 'Sand down'], $names($component));

        $component->set('source', '')->set('period', 'all')->set('sort', 'recent');
        $this->assertContains('Old nails', $names($component));
        $this->assertNotContains('Their paint', $names($component));

        $component->set('period', 'bogus')->assertSet('period', '30');
    }

    public function test_sidebar_links_to_price_changes_page(): void
    {
        $user = $this->viewer(null);

        $this->actingAs($user)->get(route('hardware-prices.changes'))
            ->assertOk()
            ->assertSee('Price Changes')
            ->assertSee('href="'.route('hardware-prices.changes').'"', false);
    }

    public function test_price_changes_page_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('hardware-prices.changes'))->assertForbidden();
    }
}
