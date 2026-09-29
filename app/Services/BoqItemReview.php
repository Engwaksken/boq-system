<?php

namespace App\Services;

use App\Models\Boq;
use App\Models\BoqItem;
use Illuminate\Support\Facades\DB;

/**
 * Reviewing, approving and rejecting suggested prices for many BOQ items at
 * once (web bulk actions and the API). Approved items are never changed.
 */
class BoqItemReview
{
    /**
     * Uses each item's suggested price as its reviewed price.
     *
     * @param  list<int>  $itemIds
     * @return array{done: int, skipped: int}
     */
    public function acceptSuggested(Boq $boq, array $itemIds, int $userId): array
    {
        return $this->each($boq, $itemIds, function (BoqItem $item) use ($userId): bool {
            if ($item->status === 'approved' || $item->ai_suggested_rate === null) {
                return false;
            }
            $this->review($item, (float) $item->ai_suggested_rate, $userId);

            return true;
        });
    }

    /**
     * Approves the items; an item that was not reviewed yet gets its suggested price first.
     *
     * @param  list<int>  $itemIds
     * @return array{done: int, skipped: int}
     */
    public function approve(Boq $boq, array $itemIds, int $userId): array
    {
        return $this->each($boq, $itemIds, function (BoqItem $item) use ($userId): bool {
            if ($item->status === 'approved') {
                return false;
            }
            if ($item->status !== 'reviewed' || $item->reviewed_rate === null) {
                if ($item->ai_suggested_rate === null) {
                    return false;
                }
                $this->review($item, (float) $item->ai_suggested_rate, $userId);
                $item->refresh();
            }

            $item->fill([
                'approved_rate' => $item->reviewed_rate,
                'approved_by' => $userId,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'status' => 'approved',
            ]);
            $item->recalculateAmount();
            $item->save();

            return true;
        });
    }

    /**
     * @param  list<int>  $itemIds
     * @return array{done: int, skipped: int}
     */
    public function reject(Boq $boq, array $itemIds, string $reason, int $userId): array
    {
        return $this->each($boq, $itemIds, function (BoqItem $item) use ($reason, $userId): bool {
            if ($item->status === 'approved') {
                return false;
            }
            $item->update([
                'approved_rate' => null,
                'approved_by' => null,
                'approved_at' => null,
                'status' => 'rejected',
                'rejected_by' => $userId,
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
            ]);

            return true;
        });
    }

    private function review(BoqItem $item, float $rate, int $userId): void
    {
        $item->update([
            'reviewed_rate' => $rate,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'approved_rate' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'status' => 'reviewed',
        ]);
    }

    /**
     * @param  list<int>  $itemIds
     * @param  callable(BoqItem): bool  $action  true when the item was changed
     * @return array{done: int, skipped: int}
     */
    private function each(Boq $boq, array $itemIds, callable $action): array
    {
        $done = 0;
        $skipped = 0;

        DB::transaction(function () use ($boq, $itemIds, $action, &$done, &$skipped): void {
            $items = $boq->items()->whereIn('id', array_map('intval', $itemIds))->orderBy('id')->lockForUpdate()->get();
            $skipped += count(array_unique($itemIds)) - $items->count();

            foreach ($items as $item) {
                $action($item) ? $done++ : $skipped++;
            }

            $this->syncStatus($boq);
        });

        return ['done' => $done, 'skipped' => max(0, $skipped)];
    }

    private function syncStatus(Boq $boq): void
    {
        $allApproved = $boq->items()->exists() && ! $boq->items()->where('status', '!=', 'approved')->exists();
        $boq->update(['status' => $allApproved ? 'approved' : 'under_review']);
    }
}
