<?php

namespace Tests\Feature;

use App\Jobs\NotifyPlanPriceChange;
use App\Livewire\HardwarePrices\Show as HardwarePriceShow;
use App\Livewire\Subscriptions\Index as SubscriptionsIndex;
use App\Models\HardwarePrice;
use App\Models\Plan;
use App\Models\PlanPriceChange;
use App\Models\PriceHistory;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PriceChangeHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function subscribe(User $user, Plan $plan, string $status = 'active'): Subscription
    {
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill(['user_id' => $user->id, 'organisation_id' => $user->organisation_id, 'status' => $status])->save();

        return $subscription;
    }

    /* ---------------------------------------------------------------- Plans */

    public function test_creating_a_plan_records_its_initial_price(): void
    {
        $plan = Plan::factory()->create(['price' => 50000, 'currency' => 'UGX']);

        $this->assertSame(1, $plan->priceChanges()->count());
        $change = $plan->priceChanges()->first();
        $this->assertNull($change->old_price);
        $this->assertEquals(50000, (float) $change->new_price);
    }

    public function test_changing_a_plan_price_records_history_and_notifies_current_subscribers_once(): void
    {
        Notification::fake();
        $plan = Plan::factory()->create(['type' => 'monthly', 'price' => 50000, 'currency' => 'UGX']);
        $active = User::factory()->create();
        $expired = User::factory()->create();
        $this->subscribe($active, $plan, 'active');
        $this->subscribe($expired, $plan, 'expired');

        $plan->update(['price' => 60000]);

        $change = $plan->priceChanges()->first();
        $this->assertEquals(50000, (float) $change->old_price);
        $this->assertEquals(60000, (float) $change->new_price);
        $this->assertEquals(10000, $change->difference());
        $this->assertEqualsWithDelta(20.0, $change->percentChange(), 0.001);

        // The sync queue already ran the job once; running it again must not duplicate.
        (new NotifyPlanPriceChange($change->id))->handle();

        $this->assertSame(1, UserNotification::where('user_id', $active->id)->where('data->plan_price_change_id', $change->id)->count());
        $this->assertSame(0, UserNotification::where('user_id', $expired->id)->count());
    }

    public function test_saving_a_plan_without_a_price_change_records_nothing(): void
    {
        Queue::fake();
        $plan = Plan::factory()->create(['price' => 50000]);

        $plan->update(['name' => 'Renamed', 'price' => '50000.00']);

        $this->assertSame(1, $plan->priceChanges()->count());
        Queue::assertNotPushed(NotifyPlanPriceChange::class);
    }

    public function test_subscriber_sees_their_plan_price_history_tab(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $mine = Plan::factory()->create(['name' => 'Monthly Pro', 'price' => 50000, 'is_active' => true, 'is_archived' => false]);
        $other = Plan::factory()->create(['name' => 'Annual Other', 'price' => 400000, 'is_active' => true, 'is_archived' => false]);
        $this->subscribe($user, $mine);
        $mine->update(['price' => 55000]);
        $other->update(['price' => 450000]);

        $component = Livewire::actingAs($user)->test(SubscriptionsIndex::class)
            ->call('showPriceHistory')
            ->assertSet('activeTab', 'price-history');

        $mineIds = $component->viewData('priceChanges')->pluck('plan_id')->unique()->all();
        $this->assertSame([$mine->id], $mineIds);

        $component->set('priceHistoryScope', 'all');
        $allIds = $component->viewData('priceChanges')->pluck('plan_id')->unique()->sort()->values()->all();
        $this->assertEqualsCanonicalizing([$mine->id, $other->id], $allIds);
    }

    /* ------------------------------------------------------- Hardware prices */

    public function test_monthly_history_summarises_each_month_and_change_vs_previous_month(): void
    {
        Carbon::setTestNow('2026-11-15 10:00:00');
        $price = HardwarePrice::create([
            'organisation_id' => null, 'item_name' => 'Cement 50kg', 'category' => 'Cement', 'unit' => 'bag',
            'price' => 40000, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala', 'is_active' => true,
            'price_type' => HardwarePrice::TYPE_HARDWARE, 'fetched_at' => now(),
        ]);
        $record = fn (string $at, float $value) => PriceHistory::create([
            'organisation_id' => null, 'hardware_price_id' => $price->id, 'price' => $value,
            'currency' => 'UGX', 'supplier' => 'Hima', 'recorded_at' => $at,
        ]);
        $record('2026-09-03', 30000);
        $record('2026-09-20', 34000); // Sep avg 32,000
        $record('2026-11-02', 38000);
        $record('2026-11-10', 42000); // Nov avg 40,000 (October has no records)

        $rows = $price->monthlyHistory(3);

        $this->assertSame(['2026-11', '2026-10', '2026-09'], $rows->map(fn ($r) => $r['month']->format('Y-m'))->all());

        [$nov, $oct, $sep] = $rows->all();
        $this->assertSame(2, $sep['records']);
        $this->assertEquals(32000, $sep['average']);
        $this->assertEquals(30000, $sep['low']);
        $this->assertEquals(34000, $sep['closing']);
        $this->assertNull($sep['change']); // nothing recorded before the window

        $this->assertSame(0, $oct['records']);
        $this->assertNull($oct['average']);

        // November compares with the last month that had prices (September).
        $this->assertEquals(8000, $nov['change']);
        $this->assertEqualsWithDelta(25.0, $nov['change_percent'], 0.001);
        $this->assertEquals(42000, $nov['high']);
    }

    public function test_hardware_price_page_renders_monthly_summary_and_records_view(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => null])->save();
        $price = HardwarePrice::create([
            'organisation_id' => null, 'item_name' => 'Cement 50kg', 'category' => 'Cement', 'unit' => 'bag',
            'price' => 40000, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala', 'is_active' => true,
            'price_type' => HardwarePrice::TYPE_HARDWARE, 'fetched_at' => now(),
        ]);
        foreach ([[now()->subMonth(), 30000], [now(), 36000]] as [$at, $value]) {
            PriceHistory::create(['organisation_id' => null, 'hardware_price_id' => $price->id, 'price' => $value,
                'currency' => 'UGX', 'supplier' => 'Hima', 'recorded_at' => $at]);
        }

        Livewire::actingAs($user)->test(HardwarePriceShow::class, ['hardwarePrice' => $price])
            ->assertSee('Change vs previous month')
            ->assertSee('+20.0%')
            ->set('months', 999)
            ->assertSet('months', 12)
            ->set('historyView', 'records')
            ->assertSee('Recorded At');
    }
}
