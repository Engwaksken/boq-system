<?php

namespace App\Services;

use App\Jobs\ProcessBoqJob;
use App\Models\Boq;
use App\Models\BoqPricingBatch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

class BoqProcessingService
{
    /**
     * @param  list<int>|null  $itemIds  price only these items (again), e.g. selected by an admin
     */
    public function start(Boq $boq, int $userId, ?int $organisationId, ?array $itemIds = null): BoqPricingBatch
    {
        $boq->loadMissing('project');

        $tenantAccess = $organisationId !== null
            && $boq->organisation_id !== null
            && $boq->organisation_id === $organisationId
            && $boq->project->organisation_id === $organisationId;
        $personalAccess = $boq->project->user_id === $userId
            && $boq->organisation_id === null
            && $boq->project->organisation_id === null;

        if (! $tenantAccess && ! $personalAccess) {
            throw new AuthorizationException;
        }

        $location = $boq->pricingLocation(\App\Models\User::find($userId));

        if ($location === '') {
            throw ValidationException::withMessages(['boq' => 'Add the project location (the town or district where it will be built) so prices can be looked up, then generate the BOQ again.']);
        }

        // Prevent concurrent running batches
        $existing = BoqPricingBatch::where('boq_id', $boq->id)->whereIn('status', ['queued', 'running'])->latest()->first();
        if ($existing) {
            return $existing;
        }

        $batch = BoqPricingBatch::create([
            'boq_id' => $boq->id,
            'organisation_id' => $organisationId ?? $boq->organisation_id,
            'user_id' => $userId,
            'location' => $location,
            'operation' => $itemIds === null ? 'generation' : 'selection',
            'item_ids' => $itemIds === null ? null : array_values(array_map('intval', $itemIds)),
            'provider' => config('services.ai_provider'),
            'status' => 'queued',
            'current_stage' => 'queued',
            'total_items' => $itemIds === null ? $boq->items()->count() : count($itemIds),
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        // Use Laravel Bus batch for tracking if needed, but dispatch single job for now
        $job = new ProcessBoqJob($batch->id);

        // If using sync queue in testing, it will run immediately
        // For async, dispatch to database queue
        if (config('queue.default') === 'sync') {
            dispatch($job);
        } else {
            $busBatch = Bus::batch([$job])->name('BOQ generation '.$boq->id)->dispatch();
            $batch->update(['job_batch_id' => $busBatch->id]);
        }

        return $batch->fresh();
    }

    public function findForBoq(Boq $boq): ?BoqPricingBatch
    {
        return BoqPricingBatch::where('boq_id', $boq->id)->latest()->first();
    }
}
