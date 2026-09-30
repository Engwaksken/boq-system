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
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Livewire\Concerns\WithBulkSelection;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithBulkSelection;
    use WithFileUploads;
    use WithPagination;

    /** File of estimated rates (one per item). */
    public $estimatesFile = null;

    /** Location confirmed in the Generate BOQ popup (saved to the project). */
    public string $projectLocation = '';

    public bool $showGenerateModal = false;

    /** Bulk rejection of the selected items asks for one reason. */
    public bool $showBulkReject = false;

    public string $bulkRejectionReason = '';

    public Boq $boq;

    public string $pdfUrl = '';

    public bool $showPdfPreview = false;

    public bool $showEmailShare = false;

    public string $shareEmail = '';

    public string $shareSubject = '';

    public string $shareMessage = '';

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

    public string $itemSearch = '';
    public string $itemStatus = 'all';
    public int $itemsPerPage = 25;

    public array $itemsPerPageOptions = [
        10,
        25,
        50,
        100,
    ];

    public function mount(Boq $boq): void
    {
        $user = auth()->user();

        abort_unless(
            $this->canAccess(
                $boq,
                $user->id,
                $user->organisation_id
            ),
            403
        );

        $this->boq = $boq;
        $this->refreshBoq();

        $this->pdfUrl = route(
            'boqs.pdf',
            $boq
        );
    }

    public function updatedItemSearch(): void
    {
        $this->closeEditor();
        $this->resetPage('itemsPage');
    }

    public function updatedItemStatus(): void
    {
        $this->closeEditor();
        $this->resetPage('itemsPage');
    }

    public function updatedItemsPerPage(): void
    {
        if (
            ! in_array(
                $this->itemsPerPage,
                $this->itemsPerPageOptions,
                true
            )
        ) {
            $this->itemsPerPage = 25;
        }

        $this->closeEditor();
        $this->resetPage('itemsPage');
    }

    public function clearItemFilters(): void
    {
        $this->itemSearch = '';
        $this->itemStatus = 'all';

        $this->closeEditor();
        $this->resetPage('itemsPage');
    }

    /** Saves the location typed on the BOQ page to the project. */
    public function saveProjectLocation(): bool
    {
        $boq = $this->authorisedBoq('boq.edit');
        $this->projectLocation = trim($this->projectLocation);

        $this->validate(['projectLocation' => ['required', 'string', 'max:255']], [], ['projectLocation' => __('project location')]);

        $boq->project()->update(['location' => $this->projectLocation]);
        $this->boq->refresh();
        $this->boq->load('project');
        $this->projectLocation = '';

        return true;
    }

    /** Generate BOQ opens a popup to confirm where the project is. */
    public function openGenerate(): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $this->projectLocation = $boq->pricingLocation(auth()->user());
        $this->resetValidation();
        $this->showGenerateModal = true;
    }

    /** @return list<string> places offered in the popup (project fields and recent projects). */
    private function locationSuggestions(): array
    {
        $user = auth()->user();
        $project = $this->boq->project;

        return collect([
            $project?->location,
            $project?->district,
            $project?->country,
            $user?->location,
            \App\Support\Regional::marketLocation(),
        ])
            ->merge(
                \App\Models\Project::query()
                    ->where(fn ($q) => $q->where('user_id', $user?->id)
                        ->when($user?->organisation_id, fn ($inner) => $inner->orWhere('organisation_id', $user->organisation_id)))
                    ->whereNotNull('location')
                    ->latest('updated_at')
                    ->limit(20)
                    ->pluck('location')
            )
            ->map(fn ($place) => trim((string) $place))
            ->filter()
            ->unique(fn ($place) => mb_strtolower($place))
            ->take(12)
            ->values()
            ->all();
    }

    public function closeGenerate(): void
    {
        $this->showGenerateModal = false;
        $this->resetValidation();
    }

    public function generateBoq(
        BoqProcessingService $processor
    ): void {
        $user = auth()->user()->fresh();

        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        // Prices are looked up for the location confirmed in the popup.
        $this->resetErrorBag('projectLocation');
        $this->projectLocation = trim($this->projectLocation);
        if ($this->projectLocation === '') {
            $this->addError('projectLocation', __('Enter the project location so prices can be looked up.'));

            return;
        }

        if ($this->projectLocation !== trim((string) $boq->project?->location)) {
            $boq->project()->update(['location' => mb_substr($this->projectLocation, 0, 255)]);
            $this->boq->refresh();
            $boq = $this->authorisedBoq('boq.edit');
        }

        $this->showGenerateModal = false;

        $gate = app(\App\Services\EntitlementGate::class);
        // Items already imported (e.g. a spreadsheet on upload) cost no further import.
        $needsExtraction = ! $boq->items()->exists();
        $allowance = $needsExtraction ? $gate->find($user, 'boq.import.excel', 'boq_imports') : null;

        if ($needsExtraction && ! $user->isSuperAdmin() && ! $allowance) {
            session()->flash('message', 'Your plan has no BOQ imports left. Upgrade or buy a top-up to generate this BOQ.');

            $this->redirectRoute('subscriptions.index');

            return;
        }

        $batch = $processor->start(
            $boq,
            $user->id,
            $user->organisation_id
        );

        if (
            $batch->status === 'completed'
            || $batch->status === 'completed_with_errors'
        ) {
            $this->generationSummary = [
                'parsed' => 0,

                'matched' =>
                    $batch->processed_items
                    - $batch->failed_items,

                'unmatched' =>
                    $batch->total_items
                    - (
                        $batch->processed_items
                        - $batch->failed_items
                    ),

                'location' =>
                    $batch->location,
            ];
        }

        if ($allowance && $batch->status !== 'failed') {
            $gate->consume($allowance, 'boq_imports');
        }

        $this->refreshBoq();
        $this->resetPage('itemsPage');
    }

    public function refreshProcessingStatus(): void
    {
        $this->runStalledGeneration();
        $this->refreshBoq();
    }

    /**
     * Safety net when no queue worker is running on the server: a Generate BOQ
     * batch still waiting after 45 seconds is processed by this page instead.
     */
    private function runStalledGeneration(): void
    {
        $batch = $this->processingBatch;

        if (! $batch) {
            return;
        }

        // Waiting with no worker to pick it up, or a chunk finished and nobody continued.
        $stalledQueued = $batch->status === 'queued' && $batch->created_at?->lt(now()->subSeconds(45));
        $stalledRunning = $batch->status === 'running' && $batch->updated_at?->lt(now()->subSeconds(15));

        if ($stalledQueued || $stalledRunning) {
            $this->runChunkNow($batch->id);
        }
    }

    /** Prices a short chunk of the batch in this request (well inside any time limit). */
    private function runChunkNow(int $batchId): void
    {
        set_time_limit(120);
        ignore_user_abort(true);

        try {
            \App\Jobs\ProcessBoqJob::dispatchSync($batchId, \App\Jobs\ProcessBoqJob::PAGE_BUDGET_SECONDS);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Admins: get prices for the selected items now (market prices first, then
     * the AI provider). Priced items are priced again; approved ones are kept.
     */
    public function priceSelected(BoqProcessingService $processor): void
    {
        $boq = $this->authorisedBoq('boq.edit');

        $ids = $boq->items()
            ->whereIn('id', array_map('intval', $this->selected))
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'approved'))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            session()->flash('message', __('Select items that are not approved to get their prices.'));

            return;
        }

        $this->startPricing($processor, $boq, $ids);
    }

    /** Scan the price of one item that has no suggested price (or a new one). */
    public function scanItem(int $itemId, BoqProcessingService $processor): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $item = $boq->items()->whereKey($itemId)->firstOrFail();

        if ($item->status === 'approved') {
            session()->flash('message', __('Approved items keep their price.'));

            return;
        }

        $this->startPricing($processor, $boq, [$item->id]);
    }

    /** Scan prices for every item of this BOQ that has no suggested price. */
    public function scanUnpriced(BoqProcessingService $processor): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $ids = $this->unpricedQuery($boq->items())->orderBy('id')->pluck('id')->all();

        if ($ids === []) {
            session()->flash('message', __('Every item already has a suggested price.'));

            return;
        }

        $this->startPricing($processor, $boq, $ids);
    }

    /** Items without a suggested price that can still be priced (not approved). */
    private function unpricedQuery($query)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('ai_suggested_rate')->orWhere('ai_suggested_rate', '<=', 0))
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'approved'));
    }

    /** @param  list<int>  $ids */
    private function startPricing(BoqProcessingService $processor, Boq $boq, array $ids): void
    {
        $user = auth()->user()->fresh();

        if ($this->isProcessing) {
            session()->flash('message', __('Prices are already being fetched for this BOQ. Wait for it to finish, then scan again.'));

            return;
        }

        if ($boq->pricingLocation($user) === '') {
            $this->openGenerate();
            $this->addError('projectLocation', __('Enter the project location so prices can be looked up.'));

            return;
        }

        $batch = $processor->start($boq, $user->id, $user->organisation_id, $ids);

        if ($batch->status === 'queued' || $batch->status === 'running') {
            $this->runChunkNow($batch->id);
        }

        $this->clearSelection();
        $this->refreshBoq();
        session()->flash('status', trans_choice('Getting prices for :count item. You can keep working; the list updates as prices arrive.|Getting prices for :count items. You can keep working; the list updates as prices arrive.', count($ids), ['count' => count($ids)]));
    }

    public function getProcessingBatchProperty(): ?BoqPricingBatch
    {
        return BoqPricingBatch::query()
            ->where(
                'boq_id',
                $this->boq->getKey()
            )
            ->latest()
            ->first();
    }

    public function getIsProcessingProperty(): bool
    {
        $batch = $this->processingBatch;

        return $batch
            && in_array(
                $batch->status,
                [
                    'queued',
                    'running',
                ],
                true
            );
    }

    public function startReview(
        int $itemId
    ): void {
        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        $item = $this->itemForBoq(
            $boq,
            $itemId
        );

        abort_if(
            $item->status === 'approved',
            422,
            'Approved items cannot be reviewed again.'
        );

        $this->reviewingItemId =
            $item->id;

        $this->rejectingItemId =
            null;

        $this->matchingItemId =
            null;

        $this->manualRate =
            $item->reviewed_rate
            ?? $item->ai_suggested_rate;

        $this->reviewNotes =
            $item->notes
            ?? '';

        $this->resetValidation();
    }

    public function startMatching(
        int $itemId,
        PriceMatchingService $matcher
    ): void {
        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        $item = $this
            ->itemForBoq(
                $boq,
                $itemId
            )
            ->load(
                'boq.project'
            );

        abort_if(
            $item->status === 'approved',
            422,
            'Approved items cannot be rematched.'
        );

        $location =
            $this->projectLocation(
                $boq
            );

        $this->matchingItemId =
            $item->id;

        $this->reviewingItemId =
            null;

        $this->rejectingItemId =
            null;

        $this->matchSearch =
            '';

        $this->automaticCandidates =
            $matcher
                ->findMatches(
                    $item,
                    5,
                    $location
                )
                ->map(
                    fn (array $match) =>
                        $this->candidateData(
                            $match['hardware_price'],
                            (int) round(
                                $match['similarity_score']
                                * 100
                            )
                        )
                )
                ->all();

        $this->matchResults =
            $matcher
                ->searchForManualMatch(
                    $item,
                    '',
                    12,
                    $location
                )
                ->map(
                    fn (HardwarePrice $price) =>
                        $this->candidateData(
                            $price
                        )
                )
                ->all();

        $this->resetValidation();
    }

    public function updatedMatchSearch(): void
    {
        if (
            $this->matchingItemId === null
        ) {
            return;
        }

        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        $item = $this
            ->itemForBoq(
                $boq,
                $this->matchingItemId
            )
            ->load(
                'boq.project'
            );

        $this->matchSearch =
            mb_substr(
                $this->matchSearch,
                0,
                100
            );

        $this->matchResults =
            app(
                PriceMatchingService::class
            )
                ->searchForManualMatch(
                    $item,
                    $this->matchSearch,
                    12,
                    $this->projectLocation(
                        $boq
                    )
                )
                ->map(
                    fn (HardwarePrice $price) =>
                        $this->candidateData(
                            $price
                        )
                )
                ->all();
    }

    public function selectHardwarePrice(
        int $itemId,
        int $priceId,
        PriceMatchingService $matcher
    ): void {
        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        $userId = (int) auth()->id();

        DB::transaction(
            function () use (
                $boq,
                $itemId,
                $priceId,
                $matcher,
                $userId
            ): void {
                $item = $this
                    ->lockedItemForBoq(
                        $boq,
                        $itemId
                    )
                    ->load(
                        'boq.project'
                    );

                abort_if(
                    $item->status === 'approved',
                    422,
                    'Approved items cannot be rematched.'
                );

                $price =
                    HardwarePrice::query()
                        ->findOrFail(
                            $priceId
                        );

                $matcher
                    ->applyManualPrice(
                        $item,
                        $price,
                        $userId
                    );

                $this->syncBoqStatus(
                    $boq
                );
            }
        );

        $this->closeEditor();
        $this->refreshBoq();
    }

    public function reviewUsingSuggested(
        int $itemId
    ): void {
        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        DB::transaction(
            function () use (
                $boq,
                $itemId
            ): void {
                $item =
                    $this->lockedItemForBoq(
                        $boq,
                        $itemId
                    );

                abort_if(
                    $item->status === 'approved',
                    422,
                    'Approved items cannot be reviewed again.'
                );

                if (
                    $item->ai_suggested_rate === null
                ) {
                    throw ValidationException::withMessages([
                        'review' =>
                            'This item does not have a suggested rate to review.',
                    ]);
                }

                $this->markReviewed(
                    $item,
                    (float) $item->ai_suggested_rate,
                    trim(
                        $this->reviewNotes
                    )
                );

                $this->syncBoqStatus(
                    $boq
                );
            }
        );

        $this->closeEditor();
        $this->refreshBoq();
    }

    public function reviewItem(
        int $itemId
    ): void {
        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        $validated =
            $this->validate([
                'manualRate' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:9999999999999.99',
                ],

                'reviewNotes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        DB::transaction(
            function () use (
                $boq,
                $itemId,
                $validated
            ): void {
                $item =
                    $this->lockedItemForBoq(
                        $boq,
                        $itemId
                    );

                abort_if(
                    $item->status === 'approved',
                    422,
                    'Approved items cannot be reviewed again.'
                );

                $this->markReviewed(
                    $item,
                    (float) $validated[
                        'manualRate'
                    ],
                    trim(
                        $validated[
                            'reviewNotes'
                        ]
                        ?? ''
                    )
                );

                $this->syncBoqStatus(
                    $boq
                );
            }
        );

        $this->closeEditor();
        $this->refreshBoq();
    }

    public function approveItem(
        int $itemId
    ): void {
        $boq = $this->authorisedBoq(
            'boq.approve'
        );

        DB::transaction(
            function () use (
                $boq,
                $itemId
            ): void {
                $item =
                    $this->lockedItemForBoq(
                        $boq,
                        $itemId
                    );

                $this->markApproved(
                    $item
                );

                $this->syncBoqStatus(
                    $boq
                );
            }
        );

        $this->refreshBoq();
    }

    public function startReject(
        int $itemId
    ): void {
        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        $item =
            $this->itemForBoq(
                $boq,
                $itemId
            );

        abort_if(
            $item->status === 'approved',
            422,
            'Approved items cannot be rejected.'
        );

        $this->rejectingItemId =
            $item->id;

        $this->reviewingItemId =
            null;

        $this->matchingItemId =
            null;

        $this->rejectionReason =
            '';

        $this->resetValidation();
    }

    public function rejectItem(
        int $itemId
    ): void {
        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        $validated =
            $this->validate([
                'rejectionReason' => [
                    'required',
                    'string',
                    'max:2000',
                ],
            ]);

        DB::transaction(
            function () use (
                $boq,
                $itemId,
                $validated
            ): void {
                $item =
                    $this->lockedItemForBoq(
                        $boq,
                        $itemId
                    );

                abort_if(
                    $item->status === 'approved',
                    422,
                    'Approved items cannot be rejected.'
                );

                $item->update([
                    'approved_rate' => null,
                    'approved_by' => null,
                    'approved_at' => null,

                    'status' =>
                        'rejected',

                    'rejected_by' =>
                        auth()->id(),

                    'rejected_at' =>
                        now(),

                    'rejection_reason' =>
                        trim(
                            $validated[
                                'rejectionReason'
                            ]
                        ),
                ]);

                $this->syncBoqStatus(
                    $boq
                );
            }
        );

        $this->closeEditor();
        $this->refreshBoq();
    }

    private function finishBulk(string $message): void
    {
        $this->clearSelection();
        $this->closeEditor();
        $this->refreshBoq();
        session()->flash('status', $message);
    }

    /** Selected items: accept the suggested price as the reviewed price. */
    public function bulkAcceptSuggested(\App\Services\BoqItemReview $review): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $result = $review->acceptSuggested($boq, array_map('intval', $this->selected), (int) auth()->id());

        $this->finishBulk(__(':done suggested price(s) accepted as reviewed.', ['done' => $result['done']])
            .($result['skipped'] ? ' '.__(':count skipped (approved or without a suggested price).', ['count' => $result['skipped']]) : ''));
    }

    /** Selected items: approve them (a suggested price is accepted first when needed). */
    public function bulkApprove(\App\Services\BoqItemReview $review): void
    {
        $boq = $this->authorisedBoq('boq.approve');
        $result = $review->approve($boq, array_map('intval', $this->selected), (int) auth()->id());

        $this->finishBulk(__(':done item(s) approved.', ['done' => $result['done']])
            .($result['skipped'] ? ' '.__(':count skipped (already approved or without a price).', ['count' => $result['skipped']]) : ''));
    }

    public function openBulkReject(): void
    {
        $this->authorisedBoq('boq.edit');
        $this->bulkRejectionReason = '';
        $this->resetValidation();
        $this->showBulkReject = $this->selected !== [];
    }

    /** Selected items: reject the price with one reason. */
    public function bulkReject(\App\Services\BoqItemReview $review): void
    {
        $boq = $this->authorisedBoq('boq.edit');
        $reason = trim($this->validate([
            'bulkRejectionReason' => ['required', 'string', 'max:2000'],
        ], [], ['bulkRejectionReason' => __('reason')])['bulkRejectionReason']);

        $result = $review->reject($boq, array_map('intval', $this->selected), $reason, (int) auth()->id());

        $this->showBulkReject = false;
        $this->finishBulk(__(':done item(s) rejected.', ['done' => $result['done']])
            .($result['skipped'] ? ' '.__(':count approved item(s) kept.', ['count' => $result['skipped']]) : ''));
    }

    public function approveAllReviewed(): void
    {
        $boq = $this->authorisedBoq(
            'boq.approve'
        );

        DB::transaction(
            function () use ($boq): void {
                $items =
                    $boq
                        ->items()
                        ->where(
                            'status',
                            'reviewed'
                        )
                        ->lockForUpdate()
                        ->get();

                foreach (
                    $items
                    as $item
                ) {
                    $this->markApproved(
                        $item
                    );
                }

                $this->syncBoqStatus(
                    $boq
                );
            }
        );

        $this->refreshBoq();
    }

    public function closeEditor(): void
    {
        $this->reviewingItemId =
            null;

        $this->rejectingItemId =
            null;

        $this->matchingItemId =
            null;

        $this->matchSearch =
            '';

        $this->automaticCandidates =
            [];

        $this->matchResults =
            [];

        $this->manualRate =
            null;

        $this->reviewNotes =
            '';

        $this->rejectionReason =
            '';

        $this->resetValidation();
    }

    private function canAccess(
        Boq $boq,
        int $userId,
        ?int $organisationId
    ): bool {
        if (
            $organisationId !== null
        ) {
            return
                $boq->organisation_id
                    === $organisationId

                && $boq
                    ->project
                    ->organisation_id
                    === $organisationId;
        }

        return
            $boq->organisation_id
                === null

            && $boq
                ->project
                ->organisation_id
                === null

            && $boq
                ->project
                ->user_id
                === $userId;
    }

    public function openPdfPreview(): void
    {
        $this->showPdfPreview = true;
    }

    public function closePdfPreview(): void
    {
        $this->showPdfPreview = false;
    }

    public function openEmailShare(): void
    {
        $this->shareEmail = '';
        $this->shareSubject = app(\App\Services\BoqShareService::class)->defaultSubject($this->boq);
        $this->shareMessage = '';
        $this->resetValidation();
        $this->showEmailShare = true;
    }

    public function closeEmailShare(): void
    {
        $this->showEmailShare = false;
    }

    public function sendShareEmail(\App\Services\BoqShareService $shares): void
    {
        $user = auth()->user();
        abort_unless($user->can('view', $this->boq), 403);

        $data = $this->validate([
            'shareEmail' => ['required', 'email', 'max:255'],
            'shareSubject' => ['required', 'string', 'max:200'],
            'shareMessage' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $shares->email($this->boq, $user, $data['shareEmail'], $data['shareSubject'], $data['shareMessage'] ?: null);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('shareEmail', __('The email could not be sent. Please try again later.'));

            return;
        }

        $this->showEmailShare = false;
        session()->flash('status', __('BOQ sent to :email.', ['email' => $data['shareEmail']]));
    }

    /**
     * Soft-delete this BOQ (owner or same organisation, with boq.edit) and return to the list.
     */
    public function deleteBoq(): void
    {
        $boq = Boq::with('project')->findOrFail($this->boq->id);

        abort_unless(auth()->user()->can('delete', $boq), 403);

        $boq->delete();

        session()->flash('status', __('BOQ deleted.'));
        $this->redirectRoute('boqs.index');
    }

    private function authorisedBoq(
        string $permission
    ): Boq {
        $user =
            auth()
                ->user()
                ?->fresh();

        abort_unless(
            $user
            && $user->hasPermission(
                $permission
            ),
            403
        );

        $boq =
            Boq::query()
                ->with(
                    'project'
                )
                ->findOrFail(
                    $this
                        ->boq
                        ->getKey()
                );

        abort_unless(
            $this->canAccess(
                $boq,
                $user->id,
                $user->organisation_id
            ),
            403
        );

        return $boq;
    }

    private function itemForBoq(
        Boq $boq,
        int $itemId
    ): BoqItem {
        return
            $boq
                ->items()
                ->findOrFail(
                    $itemId
                );
    }

    private function lockedItemForBoq(
        Boq $boq,
        int $itemId
    ): BoqItem {
        return
            $boq
                ->items()
                ->lockForUpdate()
                ->findOrFail(
                    $itemId
                );
    }

    private function markReviewed(
        BoqItem $item,
        float $rate,
        string $notes
    ): void {
        $item->update([
            'reviewed_rate' =>
                $rate,

            'reviewed_by' =>
                auth()->id(),

            'reviewed_at' =>
                now(),

            'approved_rate' =>
                null,

            'approved_by' =>
                null,

            'approved_at' =>
                null,

            'rejected_by' =>
                null,

            'rejected_at' =>
                null,

            'rejection_reason' =>
                null,

            'notes' =>
                $notes !== ''
                    ? $notes
                    : null,

            'status' =>
                'reviewed',
        ]);
    }

    private function projectLocation(
        Boq $boq
    ): string {
        return trim(
            (string) (
                $boq
                    ->project
                    ->location

                ?: $boq
                    ->project
                    ->district

                ?: $boq
                    ->project
                    ->country
            )
        );
    }

    private function candidateData(
        HardwarePrice $price,
        ?int $similarity = null
    ): array {
        return [
            'id' =>
                $price->id,

            'item_name' =>
                $price->item_name,

            'brand' =>
                $price->brand,

            'specification' =>
                $price->specification,

            'category' =>
                $price->category,

            'unit' =>
                $price->unit,

            'supplier' =>
                $price->supplier,

            'location' =>
                $price->location,

            'fetched_at' =>
                $price
                    ->fetched_at
                    ?->format(
                        'M j, Y'
                    ),

            'price' =>
                $price->price,

            'currency' =>
                $price->currency,

            'similarity' =>
                $similarity,
        ];
    }

    private function markApproved(
        BoqItem $item
    ): void {
        if (
            $item->status !== 'reviewed'
            || $item->reviewed_rate === null
        ) {
            throw ValidationException::withMessages([
                'approval' =>
                    'Only reviewed items can be approved.',
            ]);
        }

        $item->fill([
            'approved_rate' =>
                $item->reviewed_rate,

            'approved_by' =>
                auth()->id(),

            'approved_at' =>
                now(),

            'rejected_by' =>
                null,

            'rejected_at' =>
                null,

            'rejection_reason' =>
                null,

            'status' =>
                'approved',
        ]);

        $item->recalculateAmount();

        $item->save();
    }

    private function syncBoqStatus(
        Boq $boq
    ): void {
        $hasItems =
            $boq
                ->items()
                ->exists();

        $allApproved =
            $hasItems

            && ! $boq
                ->items()
                ->where(
                    'status',
                    '!=',
                    'approved'
                )
                ->exists();

        $boq->update([
            'status' =>
                $allApproved
                    ? 'approved'
                    : 'under_review',
        ]);
    }

    /** Location prices changed (priced for another place, or switched). */
    #[\Livewire\Attributes\On('boq-prices-updated')]
    public function onPricesUpdated(): void
    {
        $this->refreshBoq();
    }

    private function refreshBoq(): void
    {
        /*
         * Do not eager-load all BOQ items here.
         *
         * A large uploaded BOQ may contain hundreds or thousands
         * of rows. Items are loaded through the paginator in render().
         */
        $this->boq =
            $this
                ->boq
                ->fresh([
                    'project',
                ]);
    }

    public function uploadEstimates(\App\Services\BoqEstimateImporter $importer): void
    {
        abort_unless(auth()->user()?->can('update', $this->boq), 403);

        $this->validate(['estimatesFile' => \App\Services\BoqUploadNormalizer::rules()], [], ['estimatesFile' => __('estimated prices file')]);

        try {
            $result = $importer->import($this->boq, $this->estimatesFile);
        } catch (ValidationException $e) {
            $this->addError('estimatesFile', collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('estimatesFile');
        $message = trans_choice(':count estimated price updated.|:count estimated prices updated.', $result['updated'], ['count' => $result['updated']]);
        if ($result['unmatched'] !== []) {
            $message .= ' '.__(':count row(s) did not match a BOQ item.', ['count' => count($result['unmatched'])]);
        }
        session()->flash('status', $message);
        $this->boq->refresh();
    }

    public function render()
    {
        $itemsQuery =
            $this
                ->boq
                ->items()
                ->with([
                    'hardwarePrice',
                    'matchedBy',
                    'reviewedBy',
                    'approvedBy',
                    'rejectedBy',
                ])

                ->when(
                    trim(
                        $this->itemSearch
                    ) !== '',
                    function (
                        $query
                    ): void {
                        $term =
                            '%'
                            .trim(
                                $this->itemSearch
                            )
                            .'%';

                        $query->where(
                            function (
                                $inner
                            ) use ($term): void {
                                $inner
                                    ->where(
                                        'description',
                                        'like',
                                        $term
                                    )
                                    ->orWhere(
                                        'item_code',
                                        'like',
                                        $term
                                    )
                                    ->orWhere(
                                        'unit',
                                        'like',
                                        $term
                                    );
                            }
                        );
                    }
                )

                ->when(
                    $this->itemStatus
                        === 'matched',
                    fn ($query) =>
                        $query->whereNotNull(
                            'hardware_price_id'
                        )
                )

                ->when(
                    $this->itemStatus
                        === 'unmatched',
                    fn ($query) =>
                        $query->whereNull(
                            'hardware_price_id'
                        )
                )

                ->when(
                    $this->itemStatus === 'unpriced',
                    fn ($query) => $this->unpricedQuery($query)
                )

                ->when(
                    in_array(
                        $this->itemStatus,
                        [
                            'pending',
                            'reviewed',
                            'approved',
                            'rejected',
                        ],
                        true
                    ),
                    fn ($query) =>
                        $query->where(
                            'status',
                            $this->itemStatus
                        )
                )

                ->orderBy(
                    'id'
                );

        $items =
            $itemsQuery->paginate(
                $this->itemsPerPage,
                ['*'],
                'itemsPage'
            );

        /*
         * One aggregate query replaces several separate count
         * queries and avoids loading all items into memory.
         */
        $stats =
            $this
                ->boq
                ->items()
                ->selectRaw(
                    'COUNT(*) as total'
                )
                ->selectRaw(
                    'SUM(CASE WHEN hardware_price_id IS NOT NULL THEN 1 ELSE 0 END) as matched'
                )
                ->selectRaw(
                    "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"
                )
                ->selectRaw(
                    "SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed"
                )
                ->selectRaw(
                    "SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved"
                )
                ->selectRaw(
                    "SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected"
                )
                ->selectRaw(
                    "SUM(CASE WHEN (ai_suggested_rate IS NULL OR ai_suggested_rate <= 0) AND (status IS NULL OR status != 'approved') THEN 1 ELSE 0 END) as unpriced"
                )
                ->first();

        $itemStats = [
            'total' =>
                (int) (
                    $stats->total
                    ?? 0
                ),

            'matched' =>
                (int) (
                    $stats->matched
                    ?? 0
                ),

            'unmatched' =>
                max(
                    0,
                    (int) (
                        $stats->total
                        ?? 0
                    )
                    - (int) (
                        $stats->matched
                        ?? 0
                    )
                ),

            'pending' =>
                (int) (
                    $stats->pending
                    ?? 0
                ),

            'reviewed' =>
                (int) (
                    $stats->reviewed
                    ?? 0
                ),

            'approved' =>
                (int) (
                    $stats->approved
                    ?? 0
                ),

            'rejected' =>
                (int) (
                    $stats->rejected
                    ?? 0
                ),

            'unpriced' => (int) ($stats->unpriced ?? 0),
        ];

        return view(
            'livewire.boqs.show',
            [
                'items' =>
                    $items,

                'itemStats' =>
                    $itemStats,

                'totals' =>
                    app(\App\Services\BoqTotals::class)->forBoq($this->boq),

                'locationSuggestions' =>
                    $this->showGenerateModal ? $this->locationSuggestions() : [],
            ]
        );
    }
}
