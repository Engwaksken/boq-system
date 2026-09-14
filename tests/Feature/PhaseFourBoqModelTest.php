<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFourBoqModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_an_item_recalculates_its_amount_from_the_approved_rate(): void
    {
        $item = BoqItem::factory()->create([
            'quantity' => 3,
            'approved_rate' => 12.5,
            'amount' => 999,
        ]);

        $this->assertSame('37.50', $item->fresh()->amount);
    }

    public function test_saving_an_unapproved_imported_item_preserves_its_imported_amount(): void
    {
        $item = BoqItem::factory()->create([
            'quantity' => 3,
            'approved_rate' => null,
            'amount' => 47.25,
        ]);

        $this->assertSame('47.25', $item->fresh()->amount);
    }

    public function test_boq_exposes_its_summaries_relationship(): void
    {
        $boq = Boq::factory()->create();
        $summary = BoqSummary::factory()->create(['boq_id' => $boq->id]);

        $this->assertTrue($boq->summaries()->whereKey($summary)->exists());
    }
}
