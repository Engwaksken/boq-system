<?php

namespace App\Services;

use App\Jobs\ProcessBoqPricingItem;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqPricingJob;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

class BoqPricingJobService
{
    /**
     * Create a pricing job for a BOQ, dispatch its first batch, and let the
     * dispatch chain drive every remaining batch until every item is priced.
     */
    public function start(Boq $boq, User $user, string $location, int $batchSize = 20): BoqPricingJob
    {
        $existing = BoqPricingJob::forBoq($boq->id)->active()->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'boq' => 'An active pricing job already exists for this BOQ.',
            ]);
        }

        $totalItems = $boq->items()->unpriced()->count();
        if ($totalItems === 0) {
            throw ValidationException::withMessages([
                'boq' => 'No unpriced items found in this BOQ.',
            ]);
        }

        $job = BoqPricingJob::create([
            'boq_id' => $boq->id,
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'location' => $location,
            'status' => 'processing',
            'current_batch' => 0,
            'total_batches' => (int) ceil($totalItems / $batchSize),
            'batch_size' => $batchSize,
            'total_items' => $totalItems,
            'processed_items' => 0,
            'failed_items' => 0,
            'started_at' => now(),
        ]);

        self::dispatchBatch($job, 1);

        return $job->fresh();
    }

    /**
     * Dispatch the next chunk of unpriced items as one Bus batch. A completion
     * callback advances the job and chains the next chunk, so no client-side
     * polling loop is required and no item is skipped.
     *
     * Items already marked as processing/priced/failed are never re-selected,
     * so taking the next N unpriced items (without an offset) is the correct
     * cursor once dispatched items drop out of the unpriced scope.
     */
    public static function dispatchBatch(BoqPricingJob $job, int $batchNumber): int
    {
        $items = BoqItem::query()
            ->where('boq_id', $job->boq_id)
            ->unpriced()
            ->orderBy('id')
            ->take($job->batch_size)
            ->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $jobs = [];
        foreach ($items as $item) {
            $item->markAsPricing($job->id, $batchNumber);
            $jobs[] = new ProcessBoqPricingItem($job->id, $item->id);
        }

        $jobId = $job->id;
        $nextBatch = $batchNumber + 1;

        Bus::batch($jobs)
            ->name('boq-pricing-'.$jobId.'-batch-'.$batchNumber)
            ->then(function () use ($jobId, $nextBatch): void {
                \App\Services\BoqPricingJobService::onBatchFinished($jobId, $nextBatch);
            })
            ->catch(function () use ($jobId, $nextBatch): void {
                \App\Services\BoqPricingJobService::onBatchFinished($jobId, $nextBatch);
            })
            ->dispatch();

        return $items->count();
    }

    /**
     * Called when a dispatched chunk finishes: move the batch pointer forward,
     * then dispatch the next chunk or complete the job. A no-op for jobs that
     * were cancelled, failed, or paused (pause/resume drive progression).
     */
    public static function onBatchFinished(int $jobId, int $nextBatch): void
    {
        $job = BoqPricingJob::find($jobId);
        if (! $job || ! in_array($job->status, ['queued', 'processing'], true)) {
            return;
        }

        if ($job->current_batch < $nextBatch - 1) {
            $job->update(['current_batch' => $nextBatch - 1]);
        }
        $job->refresh();

        if ($job->processed_items + $job->failed_items >= $job->total_items) {
            self::complete($job);

            return;
        }

        $dispatched = self::dispatchBatch($job, $nextBatch);
        if ($dispatched === 0) {
            $job->refresh();
            if ($job->processed_items + $job->failed_items >= $job->total_items) {
                self::complete($job);
            } elseif ($job->status === 'processing') {
                $job->fail('Some BOQ items could not be priced.');
            }
        }
    }

    /**
     * Reset failed items to pending and dispatch them again through the
     * regular chain so progress and completion stay consistent.
     */
    public static function retryFailed(BoqPricingJob $job): int
    {
        $resetCount = $job->items()
            ->where('pricing_status', 'failed')
            ->update([
                'pricing_status' => 'pending',
                'pricing_error' => null,
                'batch_number' => null,
            ]);

        if ($resetCount === 0) {
            return 0;
        }

        $job->decrement('failed_items', $resetCount);

        if (in_array($job->status, ['completed', 'failed', 'cancelled'], true)) {
            $job->update(['status' => 'processing']);
        }

        $fresh = $job->fresh();
        self::dispatchBatch($fresh, $fresh->current_batch + 1);

        return $resetCount;
    }

    private static function complete(BoqPricingJob $job): void
    {
        $job->complete();
        if ($job->locked_by !== null) {
            $job->unlock();
        }
    }
}