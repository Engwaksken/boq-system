<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rate;
use App\Models\Supplier;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\RateLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatesSuppliersQuotationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A quotation's totals are recalculated from its lines.
     */
    public function test_quotation_totals_are_recalculated_from_lines(): void
    {
        $supplier = Supplier::factory()->create();
        $quotation = Quotation::factory()->create(['supplier_id' => $supplier->id, 'tax_rate' => 18]);

        QuotationItem::factory()->count(2)->create([
            'quotation_id' => $quotation->id,
            'quantity' => 10,
            'unit_price' => 5000,
            'vat_rate' => 18,
            'line_total' => 50000,
        ]);

        $service = app(QuotationService::class);

        $this->assertEquals(100000, $service->recalculate($quotation)->subtotal);
        $this->assertEquals(18000, $quotation->fresh()->tax_amount);
        $this->assertEquals(118000, $quotation->fresh()->total_amount);
    }

    /**
     * Accepting a quotation promotes approved lines into the rate library.
     */
    public function test_accepting_quotation_promotes_approved_lines_to_rate_library(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $quotation = Quotation::factory()->create(['supplier_id' => $supplier->id, 'status' => 'received']);
        $approved = QuotationItem::factory()->approved()->create([
            'quotation_id' => $quotation->id,
            'product' => 'Cement 42.5N (50kg)',
            'quantity' => 10,
            'unit_price' => 39000,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product' => 'Sharp sand (per tonne)',
            'quantity' => 5,
            'unit_price' => 90000,
        ]);

        $accepted = app(QuotationService::class)->accept($quotation, $user);

        $this->assertEquals('accepted', $accepted->status);
        $this->assertNotNull($accepted->accepted_at);

        $this->assertDatabaseHas('rates', [
            'source_type' => 'supplier_quotation',
            'source_reference' => $quotation->quote_number,
            'item' => 'Cement 42.5N (50kg)',
            'rate' => 39000,
            'supplier_id' => $supplier->id,
        ]);

        $this->assertDatabaseMissing('rates', ['item' => 'Sharp sand (per tonne)']);
        $this->assertEquals(
            $approved->fresh()->matched_rate_id,
            Rate::query()->where('source_reference', $quotation->quote_number)->first()->id
        );
    }

    /**
     * Rejecting a quotation records the rejection without promoting rates.
     */
    public function test_rejecting_quotation_does_not_promote_rates(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $quotation = Quotation::factory()->create(['supplier_id' => $supplier->id, 'status' => 'reviewed']);
        QuotationItem::factory()->approved()->create(['quotation_id' => $quotation->id]);

        app(QuotationService::class)->reject($quotation, $user, 'Prices are above market rates.');

        $this->assertEquals('rejected', $quotation->fresh()->status);
        $this->assertEquals('Prices are above market rates.', $quotation->fresh()->metadata['rejection_reason']);
        $this->assertDatabaseCount('rates', 0);
    }

    /**
     * Approved effective rates are searchable; pending rates are not exposed.
     */
    public function test_search_only_exposes_approved_effective_rates(): void
    {
        $supplier = Supplier::factory()->create();
        $approved = Rate::factory()->create([
            'supplier_id' => $supplier->id,
            'item' => 'Shot blasting of steel (per m2)',
            'verification_status' => 'approved',
        ]);
        Rate::factory()->pending()->create([
            'supplier_id' => $supplier->id,
            'item' => 'Shot blasting of steel (per m2)',
        ]);

        $results = app(RateLibraryService::class)->search(['query' => 'shot blasting steel'])->get();

        $this->assertTrue($results->pluck('id')->contains($approved->id));
        $this->assertCount(1, $results);
    }

    /**
     * A rate must be approved to become effective (date + status rules).
     */
    public function test_rate_is_effective_only_when_approved_and_in_date(): void
    {
        $rate = Rate::factory()->create([
            'verification_status' => 'approved',
            'effective_from' => now()->subDays(5)->toDateString(),
            'effective_until' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertTrue($rate->isEffective());

        $rate->update(['effective_from' => now()->addDays(2)->toDateString()]);
        $this->assertFalse($rate->fresh()->isEffective());

        $rate->update(['effective_from' => now()->subDays(5)->toDateString(), 'verification_status' => 'pending']);
        $this->assertFalse($rate->fresh()->isEffective());
    }

    /**
     * The rate library service approves a pending rate.
     */
    public function test_rate_approval_makes_it_active(): void
    {
        $verifier = User::factory()->create();
        $rate = Rate::factory()->pending()->create(['effective_from' => null]);

        $approved = app(RateLibraryService::class)->approve($rate, $verifier);

        $this->assertEquals('approved', $approved->verification_status);
        $this->assertTrue((bool) $approved->is_active);
        $this->assertEquals($verifier->id, $approved->verified_by);
        $this->assertNotNull($approved->effective_from);
    }
}