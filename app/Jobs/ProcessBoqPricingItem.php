<?php

namespace App\Jobs;

use App\Models\BoqPricingJob;
use App\Services\BoqPricingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBoqPricingItem implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $jobId, public int $itemId) {}

    public function handle(BoqPricingService $service): void
    {
        $job = BoqPricingJob::findOrFail($this->jobId);
        $item = $job->items()->findOrFail($this->itemId);

        $service->processItem($job, $item);
    }

    public function failed(\Throwable $exception): void
    {
        $job = BoqPricingJob::find($this->jobId);
        if ($job) {
            // Don't increment failed_items here - handle() already did it
            $fresh = $job->fresh();
            if ($fresh->processed_items + $fresh->failed_items >= $fresh->total_items) {
                $fresh->fail($exception->getMessage());
            }
        }
    }
}
