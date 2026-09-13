<?php

namespace App\Services;

use App\Models\Boq;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoqCurrentPriceGenerator
{
    public function __construct(
        private readonly BoqExtractionService $extractor,
        private readonly PriceMatchingService $matcher,
    ) {}

    /**
     * @return array{parsed: int, matched: int, unmatched: int, location: string}
     */
    public function generate(Boq $boq, int $userId, ?int $organisationId): array
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

        $location = trim((string) ($boq->project->location ?: $boq->project->district ?: $boq->project->country));

        if ($location === '') {
            throw ValidationException::withMessages([
                'boq' => 'Add a location to the project before generating this BOQ.',
            ]);
        }

        $parsed = 0;

        if (! $boq->items()->exists()) {
            $parsed = $this->extractor->extract($boq)['count'];
        }

        [$matched, $total] = DB::transaction(function () use ($boq, $location): array {
            $lockedBoq = Boq::query()->lockForUpdate()->findOrFail($boq->id);
            $items = $lockedBoq->items()->with('boq.project')->get();
            $matched = 0;

            foreach ($items as $item) {
                if ($item->match_type === 'manual') {
                    $matched++;

                    continue;
                }

                $match = $this->matcher->findMatches($item, 1, $location)->first();

                if (! $match) {
                    if (str_starts_with((string) $item->pricing_source, 'hardware_price:')) {
                        $item->fill([
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
                    }

                    continue;
                }

                $this->matcher->applyPriceToBoqItem(
                    $item,
                    $match['hardware_price'],
                    $match['similarity_score'],
                    $location
                );
                $matched++;
            }

            $allApproved = $items->isNotEmpty()
                && ! $lockedBoq->items()->where('status', '!=', 'approved')->exists();
            $lockedBoq->update(['status' => $allApproved ? 'approved' : 'under_review']);

            return [$matched, $items->count()];
        });

        return [
            'parsed' => $parsed,
            'matched' => $matched,
            'unmatched' => $total - $matched,
            'location' => $location,
        ];
    }
}
