<?php

namespace App\Jobs;

use App\Models\BoqItem;
use App\Models\BoqItemPriceSuggestion;
use App\Models\BoqPricingBatch;
use App\Services\GeminiPricingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBoqPricingItem implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $batchId, public int $itemId) {}

    public function handle(GeminiPricingService $pricing): void
    {
        $batch = BoqPricingBatch::findOrFail($this->batchId);
        $item = BoqItem::findOrFail($this->itemId);
        try {
            $result = $pricing->suggest(['description' => $item->description, 'unit' => $item->unit], $batch->location, $item->currency);
            $item->update(['ai_suggested_rate' => $result['suggested_rate'], 'reviewed_rate' => null, 'approved_rate' => null, 'location' => $batch->location, 'ai_confidence' => $result['confidence'] ?? null, 'pricing_source' => config('services.ai_provider'), 'pricing_date' => now(), 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null, 'rejected_by' => null, 'rejected_at' => null, 'rejection_reason' => null]);
            $item->boq()->update(['status' => 'under_review']);
            BoqItemPriceSuggestion::create(['boq_item_id' => $item->id, 'location' => $batch->location, 'suggested_rate' => $result['suggested_rate'], 'confidence' => $result['confidence'] ?? null, 'explanation' => $result['explanation'] ?? null, 'currency' => $item->currency, 'provider' => config('services.ai_provider')]);
            $batch->increment('processed_items');
        } catch (\Throwable $e) {
            $batch->increment('failed_items');
            report($e);
            throw $e;
        }

        $fresh = $batch->fresh();
        if ($fresh->processed_items + $fresh->failed_items >= $fresh->total_items) {
            $fresh->update([
                'status' => $fresh->failed_items > 0 ? 'completed_with_errors' : 'completed',
                'current_stage' => $fresh->failed_items > 0 ? 'completed_with_errors' : 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $batch = BoqPricingBatch::find($this->batchId);
        if ($batch) {
            $batch->increment('failed_items');
            $fresh = $batch->fresh();
            if ($fresh->processed_items + $fresh->failed_items >= $fresh->total_items) {
                $fresh->update([
                    'status' => 'completed_with_errors',
                    'current_stage' => 'completed_with_errors',
                    'error_message' => $exception->getMessage(),
                    'completed_at' => now(),
                ]);
            }
        }
    }
}
