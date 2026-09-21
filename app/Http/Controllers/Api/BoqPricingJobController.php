<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBoqPricingJobRequest;
use App\Http\Resources\BoqPricingJobResource;
use App\Models\Boq;
use App\Models\BoqPricingJob;
use App\Services\BoqPricingJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BoqPricingJobController extends Controller
{
    public function __construct(private BoqPricingJobService $service) {}

    /**
     * Create a new pricing job for a BOQ.
     */
    public function store(StoreBoqPricingJobRequest $request, Boq $boq): JsonResponse
    {
        $this->authorize('create', BoqPricingJob::class);

        $user = $request->user();

        try {
            return DB::transaction(function () use ($boq, $user, $request) {
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

                $validated = $request->validated();
                $job = $this->service->start($boq, $user, $validated['location'], $validated['batch_size']);

                return (new BoqPricingJobResource($job))
                    ->additional(['success' => true, 'message' => 'Pricing job created and first batch dispatched.'])
                    ->response()
                    ->setStatusCode(201);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'error_code' => 'NO_UNPRICED_ITEMS',
            ], 422);
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
                $dispatched = BoqPricingJobService::dispatchBatch($job->fresh(), $nextBatchNumber);

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
                $dispatched = BoqPricingJobService::dispatchBatch($job->fresh(), $nextBatchNumber);

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
                $resetCount = BoqPricingJobService::retryFailed($job);

                if ($resetCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No failed items to retry.',
                        'error_code' => 'NO_FAILED_ITEMS',
                    ], 422);
                }

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
}