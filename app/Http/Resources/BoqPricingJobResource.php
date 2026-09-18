<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BoqPricingJob */
class BoqPricingJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'boq_id' => $this->boq_id,
            'user_id' => $this->user_id,
            'organisation_id' => $this->organisation_id,
            'location' => $this->location,
            'status' => $this->status,
            'current_batch' => $this->current_batch,
            'total_batches' => $this->total_batches,
            'batch_size' => $this->batch_size,
            'total_items' => $this->total_items,
            'processed_items' => $this->processed_items,
            'failed_items' => $this->failed_items,
            'remaining_items' => $this->total_items - $this->processed_items - $this->failed_items,
            'progress_percentage' => $this->getProgressPercentage(),
            'locked_at' => $this->locked_at?->toISOString(),
            'locked_by' => $this->locked_by,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}