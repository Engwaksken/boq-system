<?php

namespace App\Livewire\Boqs;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqPricingBatch;
use App\Models\HardwarePrice;
use App\Services\BoqProcessingService;
use App\Services\PriceMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Boq $boq;

    public string $pdfUrl = '';

    /** @var array{parsed: int, matched: int, unmatched: int, location: string}|array{} */
    public array $generationSummary = [];

    public ?int $reviewingItemId = null;

    public ?int $rejectingItemId = null;

    public ?int $matchingItemId = null;

    public string $matchSearch = '';

    public array $automaticCandidates = [];

    public array $matchResults = [];

    public ?string $manualRate = null;

    public string $reviewNotes = '';

    public string $rejectionReason = '';

    public function mount(Boq $boq): void
    {
        $user = auth()->user();

        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);

        $this->boq = $boq;
        $this->refreshBoq();
        $this->pdfUrl = route('boqs.pdf', $boq);
    }

    public function generateBoq(BoqProcessingService $processor): void
    {
        $user = auth()->user()->fresh();
        $boq = $this->authorisedBoq('boq.edit');

        $batch = $processor->start($boq, $user->id, $user->organisation_id);

        // If queue is sync, job already completed; refresh immediately
        if ($batch->status === 'completed' || $batch->status === 'completed_with_errors') {
            $this->generationSummary = [
                'parsed' => 0,
                'matched' => $batch->processed_items - $batch->failed_items,
                'unmatched' => $batch->total_items - ($batch->processed_items - $batch->failed_items),
                'location' => $batch->location,
            ];
        }

        $this->refreshBoq();
    }

    public function refreshProcessingStatus(): void
    {
        $this->refreshBoq();
    }

    public function getProcessingBatchProperty(): ?BoqPricingBatch
    {
        return BoqPricingBatch::where('boq_id', $this->boq->getKey())->latest()->first();
    }

    public function getIsProcessingProperty(): bool
    {
        $batch = $this->processingBatch;

        return $batch && in_array($batch->status, ['queued', 'running'], true);
    }

    public function startReview(int $itemId): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $item = $this->itemForBoq($boq, $itemId);

        abort_if($item->status === 'approved', 422, 'Approved items cannot be reviewed again.');

        $this->reviewingItemId = $item->id;
        $this->rejectingItemId = null;
        $this->matchingItemId = null;
        $this->manualRate = $item->reviewed_rate ?? $item->ai_suggested_rate;
        $this->reviewNotes = $item->notes ?? '';
        $this->resetValidation();
    }

    public function startMatching(int $itemId, PriceMatchingService $matcher): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $item = $this->itemForBoq($boq, $itemId)->load('boq.project');

        abort_if($item->status === 'approved', 422, 'Approved items cannot be rematched.');

        $location = $this->projectLocation($boq);
        $this->matchingItemId = $item->id;
        $this->reviewingItemId = null;
        $this->rejectingItemId = null;
        $this->matchSearch = '';
        $this->automaticCandidates = $matcher->findMatches($item, 5, $location)
            ->map(fn (array $match) => $this->candidateData(
                $match['hardware_price'],
                (int) round($match['similarity_score'] * 100)
            ))->all();
        $this->matchResults = $matcher->searchForManualMatch($item, '', 12, $location)
            ->map(fn (HardwarePrice $price) => $this->candidateData($price))->all();
        $this->resetValidation();
    }

    public function updatedMatchSearch(): void
    {
        if ($this->matchingItemId === null) {
            return;
        }

        $boq = $this->authorisedBoq('boq.edit');
        $item = $this->itemForBoq($boq, $this->matchingItemId)->load('boq.project');
        $this->matchSearch = mb_substr($this->matchSearch, 0, 100);
        $this->matchResults = app(PriceMatchingService::class)
            ->searchForManualMatch($item, $this->matchSearch, 12, $this->projectLocation($boq))
            ->map(fn (HardwarePrice $price) => $this->candidateData($price))->all();
    }

    public function selectHardwarePrice(int $itemId, int $priceId, PriceMatchingService $matcher): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $userId = (int) auth()->id();

        DB::transaction(function () use ($boq, $itemId, $priceId, $matcher, $userId): void {
            $item = $this->lockedItemForBoq($boq, $itemId)->load('boq.project');
            abort_if($item->status === 'approved', 422, 'Approved items cannot be rematched.');

            $price = HardwarePrice::query()->findOrFail($priceId);
            $matcher->applyManualPrice($item, $price, $userId);
            $this->syncBoqStatus($boq);
        });

        $this->closeEditor();
        $this->refreshBoq();
    }

    public function reviewUsingSuggested(int $itemId): void
    {
        $boq = $this->authorisedBoq('boq.edit');

        DB::transaction(function () use ($boq, $itemId): void {
            $item = $this->lockedItemForBoq($boq, $itemId);

            abort_if($item->status === 'approved', 422, 'Approved items cannot be reviewed again.');

            if ($item->ai_suggested_rate === null) {
                throw ValidationException::withMessages([
                    'review' => 'This item does not have a suggested rate to review.',
                ]);
            }

            $this->markReviewed($item, (float) $item->ai_suggested_rate, trim($this->reviewNotes));
            $this->syncBoqStatus($boq);
        });

        $this->closeEditor();
        $this->refreshBoq();
    }

    public function reviewItem(int $itemId): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $validated = $this->validate([
            'manualRate' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'reviewNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($boq, $itemId, $validated): void {
            $item = $this->lockedItemForBoq($boq, $itemId);

            abort_if($item->status === 'approved', 422, 'Approved items cannot be reviewed again.');

            $this->markReviewed($item, (float) $validated['manualRate'], trim($validated['reviewNotes'] ?? ''));
            $this->syncBoqStatus($boq);
        });

        $this->closeEditor();
        $this->refreshBoq();
    }

    public function approveItem(int $itemId): void
    {
        $boq = $this->authorisedBoq('boq.approve');

        DB::transaction(function () use ($boq, $itemId): void {
            $item = $this->lockedItemForBoq($boq, $itemId);
            $this->markApproved($item);
            $this->syncBoqStatus($boq);
        });

        $this->refreshBoq();
    }

    public function startReject(int $itemId): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $item = $this->itemForBoq($boq, $itemId);

        abort_if($item->status === 'approved', 422, 'Approved items cannot be rejected.');

        $this->rejectingItemId = $item->id;
        $this->reviewingItemId = null;
        $this->matchingItemId = null;
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    public function rejectItem(int $itemId): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $validated = $this->validate([
            'rejectionReason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($boq, $itemId, $validated): void {
            $item = $this->lockedItemForBoq($boq, $itemId);

            abort_if($item->status === 'approved', 422, 'Approved items cannot be rejected.');

            $item->update([
                'approved_rate' => null,
                'approved_by' => null,
                'approved_at' => null,
                'status' => 'rejected',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => trim($validated['rejectionReason']),
            ]);
            $this->syncBoqStatus($boq);
        });

        $this->closeEditor();
        $this->refreshBoq();
    }

    public function approveAllReviewed(): void
    {
        $boq = $this->authorisedBoq('boq.approve');

        DB::transaction(function () use ($boq): void {
            $items = $boq->items()->where('status', 'reviewed')->lockForUpdate()->get();

            foreach ($items as $item) {
                $this->markApproved($item);
            }

            $this->syncBoqStatus($boq);
        });

        $this->refreshBoq();
    }

    public function closeEditor(): void
    {
        $this->reviewingItemId = null;
        $this->rejectingItemId = null;
        $this->matchingItemId = null;
        $this->matchSearch = '';
        $this->automaticCandidates = [];
        $this->matchResults = [];
        $this->manualRate = null;
        $this->reviewNotes = '';
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    private function canAccess(Boq $boq, int $userId, ?int $organisationId): bool
    {
        if ($organisationId !== null) {
            return $boq->organisation_id === $organisationId
                && $boq->project->organisation_id === $organisationId;
        }

        return $boq->organisation_id === null
            && $boq->project->organisation_id === null
            && $boq->project->user_id === $userId;
    }

    private function authorisedBoq(string $permission): Boq
    {
        $user = auth()->user()?->fresh();
        abort_unless($user && $user->hasPermission($permission), 403);

        $boq = Boq::query()->with('project')->findOrFail($this->boq->getKey());
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);

        return $boq;
    }

    private function itemForBoq(Boq $boq, int $itemId): BoqItem
    {
        return $boq->items()->findOrFail($itemId);
    }

    private function lockedItemForBoq(Boq $boq, int $itemId): BoqItem
    {
        return $boq->items()->lockForUpdate()->findOrFail($itemId);
    }

    private function markReviewed(BoqItem $item, float $rate, string $notes): void
    {
        $item->update([
            'reviewed_rate' => $rate,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'approved_rate' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'notes' => $notes !== '' ? $notes : null,
            'status' => 'reviewed',
        ]);
    }

    private function projectLocation(Boq $boq): string
    {
        return trim((string) ($boq->project->location ?: $boq->project->district ?: $boq->project->country));
    }

    private function candidateData(HardwarePrice $price, ?int $similarity = null): array
    {
        return [
            'id' => $price->id,
            'item_name' => $price->item_name,
            'brand' => $price->brand,
            'specification' => $price->specification,
            'category' => $price->category,
            'unit' => $price->unit,
            'supplier' => $price->supplier,
            'location' => $price->location,
            'fetched_at' => $price->fetched_at?->format('M j, Y'),
            'price' => $price->price,
            'currency' => $price->currency,
            'similarity' => $similarity,
        ];
    }

    private function markApproved(BoqItem $item): void
    {
        if ($item->status !== 'reviewed' || $item->reviewed_rate === null) {
            throw ValidationException::withMessages([
                'approval' => 'Only reviewed items can be approved.',
            ]);
        }

        $item->fill([
            'approved_rate' => $item->reviewed_rate,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'status' => 'approved',
        ]);
        $item->recalculateAmount();
        $item->save();
    }

    private function syncBoqStatus(Boq $boq): void
    {
        $hasItems = $boq->items()->exists();
        $allApproved = $hasItems && ! $boq->items()->where('status', '!=', 'approved')->exists();

        $boq->update(['status' => $allApproved ? 'approved' : 'under_review']);
    }

    private function refreshBoq(): void
    {
        $this->boq = $this->boq->fresh([
            'project',
            'items' => fn ($query) => $query
                ->with(['hardwarePrice', 'matchedBy', 'reviewedBy', 'approvedBy', 'rejectedBy'])
                ->orderBy('id'),
        ]);
    }

    public function render()
    {
        return view('livewire.boqs.show');
    }
}
