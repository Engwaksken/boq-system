<?php

namespace App\Jobs;

use App\Models\Boq;
use App\Models\BoqPricingBatch;
use App\Services\BoqExtractionService;
use App\Services\PriceMatchingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessBoqJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $processingBatchId) {}

    public function handle(BoqExtractionService $extractor, PriceMatchingService $matcher): void
    {
        $batch = BoqPricingBatch::findOrFail($this->processingBatchId);

        if (in_array($batch->status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true)) {
            return;
        }

        $batch->update([
            'status' => 'running',
            'started_at' => $batch->started_at ?? now(),
            'current_stage' => 'extracting',
        ]);

        $boq = Boq::with('project')->findOrFail($batch->boq_id);
        $location = trim((string) ($boq->project->location ?: $boq->project->district ?: $boq->project->country));

        try {
            // Extraction stage if no items
            if (! $boq->items()->exists()) {
                $batch->update(['current_stage' => 'extracting', 'message' => 'Extracting BOQ items...']);
                $extractor->extract($boq);
                $boq->refresh();
            }

            // Matching stage
            $batch->update(['current_stage' => 'matching', 'message' => 'Matching current prices...']);

            $items = $boq->items()->with('boq.project')->get();
            $batch->update(['total_items' => $items->count()]);

            $matched = 0;
            $failed = 0;

            foreach ($items as $item) {
                if ($this->batch() && $this->batch()->cancelled()) {
                    $batch->update(['status' => 'cancelled', 'cancelled_at' => now(), 'message' => 'Cancelled']);

                    return;
                }

                try {
                    if ($item->match_type === 'manual') {
                        $matched++;
                    } else {
                        $match = $matcher->findMatches($item, 1, $location)->first();
                        if (! $match) {
                            if ($item->match_type === 'automatic' || str_starts_with((string) $item->pricing_source, 'hardware_price:')) {
                                DB::transaction(function () use ($item): void {
                                    $locked = $item->boq->items()->lockForUpdate()->findOrFail($item->id);
                                    $locked->fill([
                                        'ai_suggested_rate' => null,
                                        'hardware_price_id' => null,
                                        'match_type' => null,
                                        'matched_by' => null,
                                        'matched_at' => null,
                                        'reviewed_rate' => null,
                                        'approved_rate' => null,
                                        'location' => null,
                                        'pricing_source' => null,
                                        'pricing_date' => null,
                                        'ai_confidence' => null,
                                        'status' => 'pending',
                                        'reviewed_by' => null,
                                        'reviewed_at' => null,
                                        'approved_by' => null,
                                        'approved_at' => null,
                                        'rejected_by' => null,
                                        'rejected_at' => null,
                                        'rejection_reason' => null,
                                    ])->save();
                                });
                            } elseif ($item->match_type === null && $item->original_rate !== null) {
                                DB::transaction(function () use ($item): void {
                                    $locked = $item->boq->items()->lockForUpdate()->findOrFail($item->id);
                                    $locked->fill([
                                        'ai_suggested_rate' => $locked->original_rate,
                                        'pricing_source' => 'spreadsheet',
                                        'pricing_date' => now()->toDateString(),
                                        'status' => 'under_review',
                                        'reviewed_by' => null,
                                        'reviewed_at' => null,
                                        'approved_by' => null,
                                        'approved_at' => null,
                                        'rejected_by' => null,
                                        'rejected_at' => null,
                                        'rejection_reason' => null,
                                    ])->save();
                                });
                            }
                        } else {
                            $matcher->applyPriceToBoqItem($item, $match['hardware_price'], $match['similarity_score'], $location);
                            $matched++;
                        }
                    }
                } catch (Throwable $e) {
                    $failed++;
                    report($e);
                }

                $batch->increment('processed_items');
            }

            $batch->update(['failed_items' => $failed]);

            // Update BOQ status
            $boq->refresh();
            $allApproved = $boq->items()->exists() && ! $boq->items()->where('status', '!=', 'approved')->exists();
            $boq->update(['status' => $allApproved ? 'approved' : 'under_review']);

            $batch->update([
                'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
                'current_stage' => 'completed',
                'completed_at' => now(),
                'message' => $matched.' matched, '.($items->count() - $matched).' require review.',
                'provider' => config('services.ai_provider'),
            ]);
        } catch (Throwable $e) {
            report($e);
            $batch->update([
                'status' => 'failed',
                'current_stage' => 'failed',
                'error_message' => 'Processing failed. See logs.',
                'completed_at' => now(),
                'failed_items' => max(0, $batch->total_items - $batch->processed_items),
            ]);
            // Preserve approved items; only revert to under_review if not already approved
            if ($boq->status !== 'approved') {
                $boq->update(['status' => 'under_review']);
            }
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $batch = BoqPricingBatch::find($this->processingBatchId);
        if ($batch && ! in_array($batch->status, ['completed', 'completed_with_errors', 'cancelled'], true)) {
            report($exception);
            $batch->update([
                'status' => 'failed',
                'current_stage' => 'failed',
                'error_message' => 'Processing failed. See logs.',
                'completed_at' => now(),
            ]);
        }
    }
}
