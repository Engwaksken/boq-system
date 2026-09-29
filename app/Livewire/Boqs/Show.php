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

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;
    use WithPagination;

    /** File of estimated rates (one per item). */
    public $estimatesFile = null;

    /** Asked for when no pricing location is known (saved to the project). */
    public string $projectLocation = '';

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

    public function generateBoq(
        BoqProcessingService $processor
    ): void {
        $user = auth()->user()->fresh();

        $boq = $this->authorisedBoq(
            'boq.edit'
        );

        // No location anywhere yet: use the one typed on this page, or ask for it.
        if ($boq->pricingLocation($user) === '') {
            if (trim($this->projectLocation) === '') {
                $this->addError('projectLocation', __('Enter the project location so prices can be looked up.'));

                return;
            }

            $this->saveProjectLocation();
            $boq = $this->authorisedBoq('boq.edit');
        }

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
        $this->refreshBoq();
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

                'needsLocation' =>
                    $this->boq->pricingLocation(auth()->user()) === '',
            ]
        );
    }
}
