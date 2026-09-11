<?php

namespace App\Jobs;

use App\Models\BoqItem;
use App\Models\BoqPricingBatch;
use App\Models\BoqItemPriceSuggestion;
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
        $result = $pricing->suggest(['description' => $item->description, 'unit' => $item->unit], $batch->location, $item->currency);
        $item->update(['ai_suggested_rate' => $result['suggested_rate'], 'approved_rate' => $result['suggested_rate'], 'amount' => $item->quantity * $result['suggested_rate'], 'ai_confidence' => $result['confidence'] ?? null, 'pricing_source' => config('services.ai_provider'), 'pricing_date' => now()]);
        BoqItemPriceSuggestion::create(['boq_item_id' => $item->id, 'location' => $batch->location, 'suggested_rate' => $result['suggested_rate'], 'confidence' => $result['confidence'] ?? null, 'explanation' => $result['explanation'] ?? null, 'currency' => $item->currency]);
        $batch->increment('processed_items');
        if ($batch->processed_items + $batch->failed_items >= $batch->total_items) $batch->update(['status' => 'completed']);
    }
}
