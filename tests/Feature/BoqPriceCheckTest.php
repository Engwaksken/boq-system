<?php

namespace Tests\Feature;

use App\Livewire\Boqs\PriceCheck;
use App\Livewire\HardwarePrices\Index as HardwarePricesIndex;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\HardwarePrice;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\PriceHistory;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoqPriceCheckTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organisation $org, array $permissions = ['boq.view']): User
    {
        $user = User::factory()->create(['organisation_id' => $org->id]);
        foreach ($permissions as $slug) {
            $user->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'boq']));
        }
        // Organisation administrators can open every project of their organisation.
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']), ['organisation_id' => $org->id]);

        return $user;
    }

    private function boqFor(User $user): Boq
    {
        $project = Project::factory()->create(['organisation_id' => $user->organisation_id, 'user_id' => $user->id]);

        return Boq::factory()->create(['organisation_id' => $user->organisation_id, 'project_id' => $project->id, 'currency' => 'UGX']);
    }

    private function market(?int $organisationId, float $price, array $attributes = []): HardwarePrice
    {
        return HardwarePrice::create($attributes + [
            'organisation_id' => $organisationId, 'item_name' => 'Cement 50kg', 'category' => 'Cement', 'unit' => 'bag',
            'price' => $price, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala', 'is_active' => true,
            'price_type' => HardwarePrice::TYPE_HARDWARE, 'fetched_at' => now(),
        ]);
    }

    public function test_hardware_price_remembers_its_last_change(): void
    {
        $price = $this->market(null, 40000);
        $this->assertNull($price->price_change);

        $price->update(['price' => 44000]);

        $price->refresh();
        $this->assertEquals(40000, (float) $price->previous_price);
        $this->assertEquals(4000, (float) $price->price_change);
        $this->assertEquals(10, (float) $price->price_change_percent);

        // Saving something else keeps the last change.
        $price->update(['supplier' => 'Tororo']);
        $this->assertEquals(10, (float) $price->fresh()->price_change_percent);
    }

    public function test_price_check_compares_our_rate_with_todays_market_and_totals(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->member($org);
        $boq = $this->boqFor($user);

        $cement = $this->market(null, 40000);
        $sand = $this->market(null, 90000, ['item_name' => 'Sand', 'unit' => 'trip']);

        // Market for cement was 36,000 when it was matched, then rose to 40,000.
        PriceHistory::create(['organisation_id' => null, 'hardware_price_id' => $cement->id, 'price' => 36000,
            'currency' => 'UGX', 'supplier' => 'Hima', 'recorded_at' => now()->subDays(10)]);

        BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Cement', 'quantity' => 10, 'currency' => 'UGX',
            'approved_rate' => 36000, 'hardware_price_id' => $cement->id, 'matched_at' => now()->subDays(5)]);
        BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Sand', 'quantity' => 2, 'currency' => 'UGX',
            'approved_rate' => null, 'reviewed_rate' => 100000, 'hardware_price_id' => $sand->id]);
        BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Labour', 'hardware_price_id' => null]);

        $component = Livewire::actingAs($user)->test(PriceCheck::class, ['boq' => $boq]);

        $summary = $component->viewData('summary');
        $this->assertSame(2, $summary['matched']);
        $this->assertSame(1, $summary['unmatched']);
        $this->assertSame(1, $summary['above']);   // sand: ours 100,000 > market 90,000
        $this->assertSame(1, $summary['below']);   // cement: ours 36,000 < market 40,000
        $this->assertSame(1, $summary['changed']); // cement moved since matching
        $this->assertEquals(10 * 36000 + 2 * 100000, $summary['total_ours']);
        $this->assertEquals(10 * 40000 + 2 * 90000, $summary['total_market']);

        $cementRow = $component->viewData('rows')->firstWhere('market.id', $cement->id);
        $this->assertSame('Approved', $cementRow['rate_label']);
        $this->assertEquals(4000, $cementRow['difference']);
        $this->assertEquals(4000, $cementRow['since_match']);

        $sandRow = $component->viewData('rows')->firstWhere('market.id', $sand->id);
        $this->assertSame('Reviewed', $sandRow['rate_label']);

        $component->set('filter', 'above');
        $this->assertSame([$sand->id], $component->viewData('rows')->pluck('market.id')->all());
    }

    public function test_rate_history_shows_only_items_of_this_boq(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->member($org);
        $boq = $this->boqFor($user);
        $otherBoq = $this->boqFor($user);
        $market = $this->market(null, 40000);

        $item = BoqItem::factory()->create(['boq_id' => $boq->id, 'approved_rate' => 30000, 'hardware_price_id' => $market->id, 'currency' => 'UGX']);
        $item->update(['approved_rate' => 35000]);
        $foreign = BoqItem::factory()->create(['boq_id' => $otherBoq->id, 'approved_rate' => 1]);

        $component = Livewire::actingAs($user)->test(PriceCheck::class, ['boq' => $boq])
            ->call('showHistory', $item->id)
            ->assertSet('historyItemId', $item->id)
            ->assertSee('Rate history');
        $this->assertSame(1, $component->viewData('history')->count());

        $component->call('showHistory', $foreign->id)->assertSet('historyItemId', null);
    }

    public function test_price_check_is_forbidden_for_another_organisations_boq(): void
    {
        $owner = $this->member(Organisation::factory()->create());
        $boq = $this->boqFor($owner);
        $outsider = $this->member(Organisation::factory()->create());

        Livewire::actingAs($outsider)->test(PriceCheck::class, ['boq' => $boq])->assertForbidden();
    }

    public function test_get_prices_can_show_general_or_my_organisation_prices(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->member($org, ['hardware-prices.view']);
        $this->market(null, 40000, ['item_name' => 'General cement']);
        $this->market($org->id, 41000, ['item_name' => 'Our cement']);
        $this->market(Organisation::factory()->create()->id, 42000, ['item_name' => 'Their cement']);

        $names = fn ($component) => $component->viewData('prices')->pluck('item_name')->sort()->values()->all();

        $component = Livewire::actingAs($user)->test(HardwarePricesIndex::class);
        $this->assertSame(['General cement', 'Our cement'], $names($component));

        $component->set('source', 'general');
        $this->assertSame(['General cement'], $names($component));

        $component->set('source', 'mine');
        $this->assertSame(['Our cement'], $names($component));

        $component->set('source', 'bogus')->assertSet('source', '');
    }
}
