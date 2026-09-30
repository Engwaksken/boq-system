<?php

namespace Tests\Feature;

use App\Livewire\SupplierRatings\Index as SupplierRatingsPage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\User;
use App\Services\SupplierRatings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierRatingsTest extends TestCase
{
    use RefreshDatabase;

    private function rater(): User
    {
        $permission = Permission::firstOrCreate(['slug' => 'hardware-prices.view'], ['name' => 'View prices', 'module' => 'rates']);
        $role = Role::firstOrCreate(['slug' => 'price-viewer'], ['name' => 'Viewer']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]));

        return $user;
    }

    private function supplier(string $name, string $type = Supplier::TYPE_SUPPLIER, array $attributes = []): Supplier
    {
        return Supplier::create($attributes + [
            'code' => 'SUP-'.Str::upper(Str::random(6)), 'name' => $name, 'type' => $type,
            'location' => 'Kampala', 'currency' => 'UGX', 'is_active' => true,
        ]);
    }

    /** A rating made at a given time. */
    private function rateAt(Supplier $supplier, int $stars, string $when, ?User $user = null, array $extra = []): SupplierRating
    {
        $this->travelTo(now()->parse($when));
        $rating = app(SupplierRatings::class)->rate($supplier, $user ?? $this->rater(), ['rating' => $stars] + $extra);
        $this->travelBack();

        return $rating;
    }

    public function test_a_user_rates_a_supplier_and_rating_again_this_month_updates_it(): void
    {
        $user = $this->rater();
        $shop = $this->supplier('Kampala Hardware');

        Livewire::actingAs($user)->test(SupplierRatingsPage::class)
            ->call('openRate')
            ->set('rateSearch', 'Kampala')
            ->assertSee('Kampala Hardware')
            ->call('chooseSupplier', $shop->id)
            ->call('saveRating')
            ->assertHasErrors('rateForm.rating')
            ->set('rateForm.rating', 4)
            ->set('rateForm.price_rating', 5)
            ->set('rateForm.delivery_rating', 3)
            ->set('rateForm.comment', 'Good prices, slow delivery')
            ->call('saveRating')
            ->assertHasNoErrors()
            ->assertSet('showRate', false);

        $rating = SupplierRating::firstOrFail();
        $this->assertSame([4, 5, null, 3, null], [$rating->rating, $rating->price_rating, $rating->quality_rating, $rating->delivery_rating, $rating->service_rating]);
        $this->assertSame(now()->format('Y-m'), $rating->period);
        $this->assertSame('4.00', (string) $shop->fresh()->rating);
        $this->assertSame(1, $shop->fresh()->ratings_count);

        // Opening it again pre-fills the rating; saving replaces it.
        Livewire::actingAs($user)->test(SupplierRatingsPage::class)
            ->call('openRate', $shop->id)
            ->assertSet('rateForm.rating', 4)
            ->assertSet('rateForm.comment', 'Good prices, slow delivery')
            ->set('rateForm.rating', 2)
            ->call('saveRating');

        $this->assertSame(1, SupplierRating::count());
        $this->assertSame('2.00', (string) $shop->fresh()->rating);

        // Next month is a new rating; the average uses each user's latest one.
        $this->rateAt($shop, 5, now()->addMonthNoOverflow()->toDateTimeString(), $user);
        $this->assertSame(2, SupplierRating::count());
        $this->assertSame('5.00', (string) $shop->fresh()->rating);
        $this->assertSame(1, $shop->fresh()->ratings_count);
    }

    public function test_inactive_suppliers_cannot_be_rated(): void
    {
        $closed = $this->supplier('Closed Ltd', attributes: ['is_active' => false]);

        Livewire::actingAs($this->rater())->test(SupplierRatingsPage::class)
            ->call('openRate')
            ->set('rateSupplierId', $closed->id)
            ->set('rateForm.rating', 5)
            ->call('saveRating')
            ->assertHasErrors('rateSupplierId');

        $this->assertSame(0, SupplierRating::count());
    }

    public function test_top_10_rankings_per_period_and_type_use_a_weighted_score(): void
    {
        $steady = $this->supplier('Steady Hardware');
        $single = $this->supplier('One Review Shop');
        $old = $this->supplier('Old Favourite');
        $factory = $this->supplier('Cement Factory', Supplier::TYPE_FACTORY);
        $closed = $this->supplier('Closed Hardware', attributes: ['is_active' => false]);

        foreach ([5, 5, 4, 5, 5, 4] as $stars) {
            $this->rateAt($steady, $stars, now()->subDays(2)->toDateTimeString());
        }
        $this->rateAt($single, 5, now()->subDay()->toDateTimeString());
        $this->rateAt($single, 1, now()->subDays(3)->toDateTimeString());
        $this->rateAt($old, 5, now()->subMonths(3)->toDateTimeString());
        $this->rateAt($factory, 4, now()->subDays(20)->toDateTimeString());
        $this->rateAt($closed, 5, now()->subDay()->toDateTimeString());

        $ratings = app(SupplierRatings::class);
        $names = fn (string $period, string $type = Supplier::TYPE_SUPPLIER) => array_map(fn ($r) => $r['supplier']->name, $ratings->leaderboard($period, $type));

        $this->assertSame(['Steady Hardware', 'One Review Shop'], $names('week'));
        $this->assertSame([], $names('week', Supplier::TYPE_FACTORY));
        $this->assertSame(['Cement Factory'], $names('month', Supplier::TYPE_FACTORY));
        $this->assertSame(['Steady Hardware', 'Old Favourite', 'One Review Shop'], $names('year'));

        $top = $ratings->leaderboard('week', Supplier::TYPE_SUPPLIER)[0];
        $this->assertSame(1, $top['rank']);
        $this->assertSame(6, $top['count']);
        $this->assertEqualsWithDelta(4.67, $top['average'], 0.01);

        $this->assertSame(['rank' => 2, 'of' => 3], $ratings->rankOf($old, 'year'));
        $this->assertNull($ratings->rankOf($old, 'week'));
        $this->assertSame('One Review Shop', $ratings->leaderboard('year', Supplier::TYPE_SUPPLIER, 5, lowest: true)[0]['supplier']->name);
    }

    public function test_summary_has_distribution_types_areas_and_monthly_trend(): void
    {
        $shop = $this->supplier('Shop');
        $factory = $this->supplier('Factory', Supplier::TYPE_FACTORY);
        $this->rateAt($shop, 5, now()->subDays(1)->toDateTimeString(), extra: ['price_rating' => 4, 'service_rating' => 5]);
        $this->rateAt($shop, 3, now()->subDays(2)->toDateTimeString(), extra: ['price_rating' => 2]);
        $this->rateAt($factory, 4, now()->subDays(3)->toDateTimeString());
        $this->rateAt($factory, 2, now()->subMonthsNoOverflow(2)->toDateTimeString());

        $summary = app(SupplierRatings::class)->summary('month');

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['suppliers']);
        $this->assertEqualsWithDelta(4.0, $summary['average'], 0.01);
        $this->assertSame([5 => 1, 4 => 1, 3 => 1, 2 => 0, 1 => 0], $summary['distribution']);
        $this->assertSame(['count' => 2, 'average' => 4.0], $summary['by_type']['supplier']);
        $this->assertSame(['count' => 1, 'average' => 4.0], $summary['by_type']['factory']);
        $this->assertEqualsWithDelta(3.0, $summary['criteria']['price_rating'], 0.01);
        $this->assertNull($summary['criteria']['delivery_rating']);

        $trend = $summary['trend'];
        $this->assertCount(12, $trend);
        $this->assertSame(now()->format('Y-m'), end($trend)['period']);
        $this->assertSame(4, collect($trend)->sum('count'));
        $this->assertSame(1, collect($trend)->firstWhere('period', now()->subMonthsNoOverflow(2)->format('Y-m'))['count']);
    }

    public function test_page_shows_rankings_charts_and_supplier_performance(): void
    {
        $user = $this->rater();
        $shop = $this->supplier('Nakawa Hardware');
        $factory = $this->supplier('Jinja Steel', Supplier::TYPE_FACTORY);
        $this->rateAt($shop, 5, now()->subDay()->toDateTimeString(), $user, ['comment' => 'Best cement price in town']);
        $this->rateAt($factory, 4, now()->subDays(2)->toDateTimeString());

        $this->actingAs($user)->get(route('supplier-ratings.index'))
            ->assertOk()
            ->assertSee('Top 10 Hardware')
            ->assertSee('Top 10 Factories')
            ->assertSee('Nakawa Hardware')
            ->assertSee('Jinja Steel')
            ->assertSee('Rating distribution')
            ->assertSee('Monthly trend')
            ->assertSee('boq-chart-pie-svg', false)
            ->assertSee('boq-chart-line-svg', false)
            ->assertDontSee('needing improvement');

        Livewire::actingAs($user)->test(SupplierRatingsPage::class)
            ->call('showSupplier', $shop->id)
            ->assertSee('Best cement price in town')
            ->assertSee('Verified user')
            ->assertSee('#1');

        // Sidebar link for users who can see prices.
        $this->actingAs($user)->get(route('dashboard'))->assertSee(route('supplier-ratings.index'));
    }

    public function test_admins_see_who_needs_improvement_and_can_hide_ratings(): void
    {
        $admin = $this->superAdmin();
        $shop = $this->supplier('Rude Hardware');
        $bad = $this->rateAt($shop, 1, now()->subDay()->toDateTimeString(), extra: ['comment' => 'spam spam']);
        $this->rateAt($shop, 4, now()->subDays(2)->toDateTimeString());

        $component = Livewire::actingAs($admin)->test(SupplierRatingsPage::class)
            ->assertSee('Hardware needing improvement')
            ->call('showSupplier', $shop->id)
            ->assertSee('spam spam')
            ->call('toggleHidden', $bad->id)
            ->assertSee('Hidden');

        $this->assertTrue($bad->fresh()->is_hidden);
        $this->assertSame('4.00', (string) $shop->fresh()->rating);
        $this->assertSame(1, app(SupplierRatings::class)->summary('month')['total']);

        // Regular users cannot moderate or see who wrote a review.
        Livewire::actingAs($this->rater())->test(SupplierRatingsPage::class)
            ->call('toggleHidden', $bad->id)
            ->assertForbidden();
    }

    public function test_users_without_price_access_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('supplier-ratings.index'))->assertForbidden();
    }

    public function test_admin_supplier_list_shows_the_user_rating_and_keeps_it_on_edit(): void
    {
        $admin = $this->superAdmin();
        $shop = $this->supplier('Rated Hardware');
        $this->rateAt($shop, 4, now()->toDateTimeString());

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\SuppliersManager::class)
            ->assertSee('4.0')
            ->call('edit', $shop->id)
            ->set('form.rating', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('4.00', (string) $shop->fresh()->rating);
    }

    /* -------------------------------- API ------------------------------- */

    public function test_api_rates_and_returns_leaderboard_summary_and_supplier_details(): void
    {
        $user = $this->rater();
        $shop = $this->supplier('Api Hardware');
        $factory = $this->supplier('Api Factory', Supplier::TYPE_FACTORY);
        $this->rateAt($factory, 3, now()->subDay()->toDateTimeString());

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/supplier-ratings/suppliers/{$shop->id}", ['rating' => 6])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/supplier-ratings/suppliers/{$shop->id}", ['rating' => 5, 'quality_rating' => 4, 'comment' => 'Great'])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.supplier.ratings_count', 1);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/supplier-ratings/suppliers/{$shop->id}", ['rating' => 4])
            ->assertOk();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/supplier-ratings/leaderboard?period=week&type=supplier')
            ->assertOk()
            ->assertJsonPath('data.items.0.rank', 1)
            ->assertJsonPath('data.items.0.supplier.name', 'Api Hardware')
            ->assertJsonPath('data.items.0.average', 4)
            ->assertJsonPath('data.items.0.criteria.quality', null);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/supplier-ratings/leaderboard?period=month&type=factory')
            ->assertOk()
            ->assertJsonPath('data.items.0.supplier.name', 'Api Factory');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/supplier-ratings/summary?period=month')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.distribution.0', ['stars' => 5, 'count' => 0])
            ->assertJsonCount(12, 'data.trend')
            ->assertJsonCount(12, 'data.trend_by_type.factory');

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/supplier-ratings/suppliers/{$shop->id}")
            ->assertOk()
            ->assertJsonPath('data.rank.rank', 1)
            ->assertJsonPath('data.mine.rating', 4)
            ->assertJsonPath('data.reviews.0.author', null);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/supplier-ratings/suppliers?search=Api&type=factory')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Api Factory');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/supplier-ratings/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.supplier.name', 'Api Hardware');

        $rating = SupplierRating::where('supplier_id', $shop->id)->firstOrFail();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/supplier-ratings/{$rating->id}/visibility", ['hidden' => true])->assertForbidden();
        $this->actingAs($this->superAdmin(), 'sanctum')->patchJson("/api/v1/supplier-ratings/{$rating->id}/visibility", ['hidden' => true])->assertOk();
        $this->assertSame(0, $shop->fresh()->ratings_count);

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/supplier-ratings/leaderboard')->assertForbidden();
    }
}
