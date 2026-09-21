<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\BoqItemPriceSuggestion;
use App\Models\BoqPricingJob;
use Illuminate\Support\Facades\Log;

class BoqPricingService
{
    public function __construct(private GeminiPricingService $pricing) {}

    /**
     * Process a single BOQ item pricing.
     */
    public function processItem(BoqPricingJob $job, BoqItem $item): void
    {
        try {
            $result = $this->pricing->suggest(
                ['description' => $item->description, 'unit' => $item->unit],
                $job->location,
                $item->currency
            );

            $this->updateItemWithPricing($item, $job, $result);
            $this->createPriceSuggestion($item, $job, $result);

            $item->markAsPriced();
            $job->increment('processed_items');
        } catch (\Throwable $e) {
            $item->markAsFailed($e->getMessage());
            $job->increment('failed_items');
            Log::error('BOQ pricing item failed', [
                'job_id' => $job->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $fresh = $job->fresh();
        if ($fresh->processed_items + $fresh->failed_items >= $fresh->total_items) {
            $fresh->complete();
        }
    }

    /**
     * Update the BOQ item with pricing results.
     */
    private function updateItemWithPricing(BoqItem $item, BoqPricingJob $job, array $result): void
    {
        $item->update([
            'ai_suggested_rate' => $result['suggested_rate'],
            'reviewed_rate' => null,
            'approved_rate' => null,
            'location' => $job->location,
            'ai_confidence' => $result['confidence'] ?? null,
            'pricing_source' => config('services.ai_provider'),
            'pricing_date' => now(),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        $item->boq()->update(['status' => 'under_review']);
    }

    /**
     * Create a price suggestion record.
     */
    private function createPriceSuggestion(BoqItem $item, BoqPricingJob $job, array $result): void
    {
        BoqItemPriceSuggestion::create([
            'boq_item_id' => $item->id,
            'location' => $job->location,
            'suggested_rate' => $result['suggested_rate'],
            'confidence' => $result['confidence'] ?? null,
            'explanation' => $result['explanation'] ?? null,
            'currency' => $item->currency,
            'provider' => config('services.ai_provider'),
        ]);
    }
}