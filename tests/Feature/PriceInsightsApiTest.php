<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\HardwarePrice;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\PriceHistory;
use App\Models\Project;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** API endpoints the mobile app uses for price history and comparisons. */
class PriceInsightsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(?int $organisationId = null): User
    {
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => $organisationId])->save();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'), ['organisation_id' => $organisationId]);
        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $organisationId,
            'feature_id' => Feature::firstOrCreate(['code' => 'boq.management'], ['name' => 'boq.management'])->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        return $user->fresh();
    }

    private function price(?int $organisationId, string $name, float $price): HardwarePrice
    {
        return HardwarePrice::create([
            'organisation_id' => $organisationId, 'item_name' => $name, 'category' => 'Cement', 'unit' => 'bag',
            'price' => $price, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala', 'is_active' => true,
            'price_type' => HardwarePrice::TYPE_HARDWARE, 'fetched_at' => now(),
        ]);
    }

    public function test_monthly_history_endpoint(): void
    {
        $user = $this->user();
        $price = $this->price(null, 'Cement', 40000);
        PriceHistory::create(['organisation_id' => null, 'hardware_price_id' => $price->id, 'price' => 36000,
            'currency' => 'UGX', 'supplier' => 'Hima', 'recorded_at' => now()->subMonth()]);
        PriceHistory::create(['organisation_id' => null, 'hardware_price_id' => $price->id, 'price' => 40000,
            'currency' => 'UGX', 'supplier' => 'Hima', 'recorded_at' => now()]);

        $months = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/hardware-prices/{$price->id}/monthly?months=6")
            ->assertOk()
            ->assertJsonPath('data.item.id', $price->id)
            ->json('data.months');

        $this->assertCount(6, $months);
        $this->assertSame(now()->format('Y-m'), $months[0]['month']);
        $this->assertEqualsWithDelta(11.11, $months[0]['change_percent'], 0.01);
    }

    public function test_monthly_history_of_another_organisations_price_is_forbidden(): void
    {
        $user = $this->user(Organisation::factory()->create()->id);
        $theirs = $this->price(Organisation::factory()->create()->id, 'Theirs', 1);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/hardware-prices/{$theirs->id}/monthly")->assertForbidden();
    }

    public function test_changes_endpoint_lists_filters_and_counts(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org->id);
        $this->price(null, 'Cement', 40000)->update(['price' => 44000]);
        $this->price($org->id, 'Our blocks', 2000)->update(['price' => 1500]);
        $this->price(Organisation::factory()->create()->id, 'Theirs', 1000)->update(['price' => 2000]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/hardware-prices/changes')->assertOk();
        $this->assertEqualsCanonicalizing(['Cement', 'Our blocks'], collect($response->json('data.data'))->pluck('item_name')->all());
        $response->assertJsonPath('meta.stats.up', 1)->assertJsonPath('meta.stats.down', 1);

        $names = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/hardware-prices/changes?source=mine')
            ->json('data.data'))->pluck('item_name')->all();
        $this->assertSame(['Our blocks'], $names);

        // Price list supports the same general / my organisation filter.
        $names = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/hardware-prices?source=general')
            ->json('data.data'))->pluck('item_name')->all();
        $this->assertSame(['Cement'], $names);
    }

    public function test_boq_price_check_endpoint(): void
    {
        $user = $this->user();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => null]);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => null, 'currency' => 'UGX']);
        $market = $this->price(null, 'Cement', 40000);
        BoqItem::factory()->create(['boq_id' => $boq->id, 'quantity' => 10, 'currency' => 'UGX',
            'approved_rate' => 36000, 'hardware_price_id' => $market->id]);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/boqs/{$boq->id}/price-check")
            ->assertOk()
            ->assertJsonPath('data.summary.matched', 1)
            ->assertJsonPath('data.summary.below', 1)
            ->assertJsonPath('data.rows.0.rate_field', 'approved_rate')
            ->assertJsonPath('data.rows.0.difference', 4000)
            ->assertJsonPath('data.rows.0.market.id', $market->id);

        $outsider = $this->user();
        $this->actingAs($outsider, 'sanctum')->getJson("/api/v1/boqs/{$boq->id}/price-check")->assertForbidden();
    }

    public function test_plan_price_history_is_not_exposed_to_clients(): void
    {
        $this->actingAs($this->user(), 'sanctum')->getJson('/api/v1/plans/price-history')->assertNotFound();
    }
}
