<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBoqPricingJobRequest;
use App\Http\Resources\BoqPricingJobResource;
use App\Jobs\ProcessBoqPricingItem;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqPricingJob;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BoqPricingJobController extends Controller
{
    /**
     * Create a new pricing job for a BOQ.
     */
    public function store(StoreBoqPricingJobRequest $request, Boq $boq): JsonResponse
    {
        $this->authorize('create', BoqPricingJob::class);

        $user = $request->user();
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($boq, $user, $validated) {
                // Check for existing active job for this BOQ (locking mechanism)
                $existingJob = BoqPricingJob::forBoq($boq->id)
                    ->active()
                    ->first();

                if ($existingJob) {
                    return response()->json([
                        'success' => false,
                        'message' => 'An active pricing job already exists for this BOQ. Please wait for it to complete or cancel it first.',
                        'error_code' => 'JOB_ALREADY_ACTIVE',
                        'existing_job_id' => $existingJob->id,
                    ], 409);
                }

                // Count unpriced items for this BOQ
                $totalItems = $boq->items()->unpriced()->count();

                if ($totalItems === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No unpriced items found in this BOQ.',
                        'error_code' => 'NO_UNPRICED_ITEMS',
                    ], 422);
                }

                $batchSize = $validated['batch_size'];
                $totalBatches = (int) ceil($totalItems / $batchSize);

                // Create the pricing job
                $job = BoqPricingJob::create([
                    'boq_id' => $boq->id,
                    'user_id' => $user->id,
                    'organisation_id' => $user->organisation_id,
                    'location' => $validated['location'],
                    'status' => 'queued',
                    'current_batch' => 0,
                    'total_batches' => $totalBatches,
                    'batch_size' => $batchSize,
                    'total_items' => $totalItems,
                    'processed_items' => 0,
                    'failed_items' => 0,
                ]);

                // Dispatch first batch jobs
                $this->dispatchBatchJobs($job, 1);

                return (new BoqPricingJobResource($job->fresh()))
                    ->additional(['success' => true, 'message' => 'Pricing job created and first batch dispatched.'])
                    ->response()
                    ->setStatusCode(201);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create pricing job', [
                'boq_id' => $boq->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create pricing job. Please try again.',
                'error_code' => 'JOB_CREATION_FAILED',
            ], 500);
        }
    }

    /**
     * Display the specified pricing job with progress data.
     */
    public function show(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('view', $job);

        return (new BoqPricingJobResource($job->load(['user', 'lockedBy'])))
            ->additional(['success' => true])
            ->response();
    }

    /**
     * Start processing the pricing job.
     */
    public function start(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('start', $job);

        try {
            return DB::transaction(function () use ($job) {
                // Acquire lock
                if (! $job->lock($request->user())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Job is already locked by another user.',
                        'error_code' => 'JOB_LOCKED',
                    ], 409);
                }

                // Update job status
                $job->update([
                    'status' => 'processing',
                    'started_at' => now(),
                ]);

                // Dispatch first batch if not already dispatched
                if ($job->current_batch === 0) {
                    $this->dispatchBatchJobs($job, 1);
                }

                return (new BoqPricingJobResource($job->fresh()))
                    ->additional(['success' => true, 'message' => 'Pricing job started.'])
                    ->response();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to start pricing job', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start pricing job. Please try again.',
                'error_code' => 'JOB_START_FAILED',
            ], 500);
        }
    }

    /**
     * Process the next batch of unpriced items.
     */
    public function nextBatch(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('update', $job);

        try {
            return DB::transaction(function () use ($job) {
                // Check if job can process next batch
                if (! $job->canProcessNextBatch()) {
                    $message = match (true) {
                        $job->status !== 'processing' => 'Job is not in processing state.',
                        $job->current_batch >= $job->total_batches => 'All batches have been processed.',
                        $job->isLocked() => 'Job is locked by another user.',
                        default => 'Cannot process next batch.',
                    };

                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'error_code' => 'CANNOT_PROCESS_NEXT_BATCH',
                    ], 422);
                }

                // Acquire lock for this batch
                if (! $job->lock($request->user())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Job is already locked by another user.',
                        'error_code' => 'JOB_LOCKED',
                    ], 409);
                }

                $nextBatchNumber = $job->current_batch + 1;

                // Dispatch jobs for the next batch
                $dispatched = $this->dispatchBatchJobs($job, $nextBatchNumber);

                if ($dispatched === 0) {
                    // No items to process, mark batch as complete
                    $job->markBatchComplete($nextBatchNumber, 0, 0);
                    $job->unlock();

                    return (new BoqPricingJobResource($job->fresh()))
                        ->additional(['success' => true, 'message' => 'No items in this batch. Moving to next.'])
                        ->response();
                }

                return (new BoqPricingJobResource($job->fresh()))
                    ->additional(['success' => true, 'message' => "Batch {$nextBatchNumber} dispatched with {$dispatched} items."])
                    ->response();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to process next batch', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process next batch. Please try again.',
                'error_code' => 'NEXT_BATCH_FAILED',
            ], 500);
        }
    }

    /**
     * Pause the pricing job.
     */
    public function pause(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('pause', $job);

        try {
            return DB::transaction(function () use ($job) {
                $job->update(['status' => 'paused']);
                $job->unlock();

                return (new BoqPricingJobResource($job->fresh()))
                    ->additional(['success' => true, 'message' => 'Pricing job paused.'])
                    ->response();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to pause pricing job', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to pause pricing job. Please try again.',
                'error_code' => 'JOB_PAUSE_FAILED',
            ], 500);
        }
    }

    /**
     * Resume a paused pricing job.
     */
    public function resume(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('resume', $job);

        try {
            return DB::transaction(function () use ($job) {
                // Acquire lock
                if (! $job->lock($request->user())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Job is already locked by another user.',
                        'error_code' => 'JOB_LOCKED',
                    ], 409);
                }

                $job->update(['status' => 'processing']);

                // Continue from current batch
                $nextBatchNumber = $job->current_batch + 1;
                $dispatched = $this->dispatchBatchJobs($job, $nextBatchNumber);

                if ($dispatched === 0 && $job->current_batch < $job->total_batches) {
                    // No items in this batch, try next
                    $job->markBatchComplete($nextBatchNumber, 0, 0);
                }

                return (new BoqPricingJobResource($job->fresh()))
                    ->additional(['success' => true, 'message' => 'Pricing job resumed.'])
                    ->response();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to resume pricing job', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resume pricing job. Please try again.',
                'error_code' => 'JOB_RESUME_FAILED',
            ], 500);
        }
    }

    /**
     * Cancel the pricing job.
     */
    public function cancel(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('cancel', $job);

        try {
            return DB::transaction(function () use ($job) {
                // Mark remaining unpriced items as skipped
                $skippedCount = $job->items()
                    ->whereIn('pricing_status', ['pending', 'processing'])
                    ->update([
                        'pricing_status' => 'skipped',
                        'pricing_error' => null,
                    ]);

                $job->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'error_message' => 'Cancelled by user',
                ]);

                $job->unlock();

                return (new BoqPricingJobResource($job->fresh()))
                    ->additional([
                        'success' => true,
                        'message' => "Pricing job cancelled. {$skippedCount} items marked as skipped.",
                    ])
                    ->response();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to cancel pricing job', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel pricing job. Please try again.',
                'error_code' => 'JOB_CANCEL_FAILED',
            ], 500);
        }
    }

    /**
     * Retry failed items in the pricing job.
     */
    public function retryFailed(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('retryFailed', $job);

        try {
            return DB::transaction(function () use ($job) {
                // Reset failed items to unpriced
                $resetCount = $job->items()
                    ->where('pricing_status', 'failed')
                    ->update([
                        'pricing_status' => 'pending',
                        'pricing_error' => null,
                        'batch_number' => null,
                    ]);

                if ($resetCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No failed items to retry.',
                        'error_code' => 'NO_FAILED_ITEMS',
                    ], 422);
                }

                // Update job counters
                $job->decrement('failed_items', $resetCount);

                // Dispatch retry jobs for failed items
                $this->dispatchRetryJobs($job);

                return (new BoqPricingJobResource($job->fresh()))
                    ->additional(['success' => true, 'message' => "Retrying {$resetCount} failed items."])
                    ->response();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to retry failed items', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retry failed items. Please try again.',
                'error_code' => 'RETRY_FAILED',
            ], 500);
        }
    }

    /**
     * Acquire lock on the pricing job.
     */
    public function lock(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('lock', $job);

        try {
            if ($job->lock($request->user())) {
                return (new BoqPricingJobResource($job->fresh()))
                    ->additional(['success' => true, 'message' => 'Job locked successfully.'])
                    ->response();
            }

            return response()->json([
                'success' => false,
                'message' => 'Job is already locked by another user.',
                'error_code' => 'JOB_ALREADY_LOCKED',
            ], 409);
        } catch (\Throwable $e) {
            Log::error('Failed to lock pricing job', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to lock pricing job. Please try again.',
                'error_code' => 'LOCK_FAILED',
            ], 500);
        }
    }

    /**
     * Release lock on the pricing job.
     */
    public function unlock(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('unlock', $job);

        try {
            $job->unlock();

            return (new BoqPricingJobResource($job->fresh()))
                ->additional(['success' => true, 'message' => 'Job unlocked successfully.'])
                ->response();
        } catch (\Throwable $e) {
            Log::error('Failed to unlock pricing job', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unlock pricing job. Please try again.',
                'error_code' => 'UNLOCK_FAILED',
            ], 500);
        }
    }

    /**
     * Get lightweight progress data for polling.
     */
    public function progress(Request $request, BoqPricingJob $job): JsonResponse
    {
        $this->authorize('view', $job);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $job->id,
                'status' => $job->status,
                'current_batch' => $job->current_batch,
                'total_batches' => $job->total_batches,
                'processed_items' => $job->processed_items,
                'failed_items' => $job->failed_items,
                'remaining_items' => $job->total_items - $job->processed_items - $job->failed_items,
                'total_items' => $job->total_items,
                'progress_percentage' => $job->getProgressPercentage(),
                'locked' => $job->isLocked(),
                'locked_by' => $job->locked_by,
                'started_at' => $job->started_at?->toISOString(),
                'completed_at' => $job->completed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Dispatch pricing jobs for a specific batch.
     */
    private function dispatchBatchJobs(BoqPricingJob $job, int $batchNumber): int
    {
        $items = BoqItem::query()
            ->where('boq_id', $job->boq_id)
            ->unpriced()
            ->orderBy('id')
            ->skip(($batchNumber - 1) * $job->batch_size)
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

        $batch = Bus::batch($jobs)->dispatch();
        $job->update(['batch_id' => $batch->id]);

        return $items->count();
    }

    /**
     * Dispatch retry jobs for failed items.
     */
    private function dispatchRetryJobs(BoqPricingJob $job): void
    {
        $items = $job->items()
            ->where('pricing_status', 'pending')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $jobs = [];
        foreach ($items as $item) {
            $item->markAsPricing($job->id, $job->current_batch + 1);
            $jobs[] = new ProcessBoqPricingItem($job->id, $item->id);
        }

        $batch = Bus::batch($jobs)->dispatch();
        $job->update(['batch_id' => $batch->id]);
    }
}