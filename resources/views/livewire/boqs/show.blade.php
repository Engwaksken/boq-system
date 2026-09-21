<div @if($this->isProcessing) wire:poll.2s="refreshProcessingStatus" @endif>

    @php
        $canEdit = Auth::user()->hasPermission('boq.edit');
        $canApprove = Auth::user()->hasPermission('boq.approve');
        $reviewedCount = $itemStats['reviewed'] ?? 0;
        $processingBatch = $this->processingBatch;
        $isProcessing = $this->isProcessing;
    @endphp

    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">
                {{ $boq->name }}
            </h1>

            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                {{ $boq->status === 'approved' || $boq->status === 'analysed'
                    ? 'bg-green-100 text-green-800'
                    : ($boq->status === 'under_review'
                        ? 'bg-purple-100 text-purple-800'
                        : ($boq->status === 'uploaded'
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-amber-100 text-amber-800')) }}">
                {{ $boq->status }}
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">

            <a
                href="{{ url('/boqs') }}"
                class="boq-btn-secondary"
            >
                <i class="fas fa-arrow-left"></i>
                Back
            </a>

            @if(isset($pdfUrl) && $pdfUrl)
                <a
                    href="{{ $pdfUrl }}"
                    target="_blank"
                    class="boq-btn-secondary"
                >
                    <i class="fas fa-file-pdf"></i>
                    Download PDF
                </a>
            @endif

            @if($canApprove && $reviewedCount > 0)
                <button
                    wire:click="approveAllReviewed"
                    wire:loading.attr="disabled"
                    wire:target="approveAllReviewed"
                    type="button"
                    class="boq-btn-primary"
                >
                    <i class="fas fa-circle-check"></i>
                    Approve reviewed ({{ $reviewedCount }})
                </button>
            @endif

            @if($canEdit)
                <button
                    wire:click="generateBoq"
                    wire:loading.attr="disabled"
                    wire:target="generateBoq"
                    type="button"
                    class="boq-btn-primary"
                >
                    <i
                        wire:loading.remove
                        wire:target="generateBoq"
                        class="fas fa-wand-magic-sparkles"
                    ></i>

                    <i
                        wire:loading
                        wire:target="generateBoq"
                        class="fas fa-spinner fa-spin"
                    ></i>

                    <span
                        wire:loading.remove
                        wire:target="generateBoq"
                    >
                        Generate BOQ
                    </span>

                    <span
                        wire:loading
                        wire:target="generateBoq"
                    >
                        Generating...
                    </span>
                </button>
            @endif

        </div>
    </div>


    @error('boq')
        <div class="boq-flash boq-flash-error mb-4">
            {{ $message }}
        </div>
    @enderror

    @error('approval')
        <div class="boq-flash boq-flash-error mb-4">
            {{ $message }}
        </div>
    @enderror

    @error('review')
        <div class="boq-flash boq-flash-error mb-4">
            {{ $message }}
        </div>
    @enderror


    @if($isProcessing && $processingBatch)

        <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4">

            <div class="flex items-center justify-between gap-3">

                <div>

                    <p class="text-sm font-semibold text-indigo-900">
                        Processing BOQ —
                        {{ ucfirst($processingBatch->current_stage ?? $processingBatch->status) }}
                    </p>

                    <p class="mt-1 text-xs text-indigo-700">
                        {{ $processingBatch->message ?: 'Working on your BOQ...' }}
                    </p>

                </div>

                <span class="text-xs font-medium text-indigo-700">
                    {{ $processingBatch->processed_items }}/{{ $processingBatch->total_items ?: '?' }}
                </span>

            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-indigo-100">

                @php
                    $pct = $processingBatch->total_items > 0
                        ? min(
                            100,
                            (int) round(
                                $processingBatch->processed_items
                                / max(1, $processingBatch->total_items)
                                * 100
                            )
                        )
                        : 25;
                @endphp

                <div
                    class="h-2 bg-indigo-600 transition-all"
                    style="width: {{ $pct }}%"
                ></div>

            </div>

            @if($processingBatch->location)

                <p class="mt-2 text-xs text-indigo-600">
                    Location:
                    {{ $processingBatch->location }}
                </p>

            @endif

        </div>

    @elseif($processingBatch && $processingBatch->status === 'failed')

        <div class="boq-flash boq-flash-error mb-4">
            Processing failed:
            {{ $processingBatch->error_message ?: 'Unknown error.' }}
        </div>

    @elseif($processingBatch && $processingBatch->status === 'completed_with_errors')

        <div class="boq-flash boq-flash-warning mb-4">
            Completed with
            {{ $processingBatch->failed_items }}
            error(s):
            {{ $processingBatch->message }}
        </div>

    @elseif($processingBatch && $processingBatch->status === 'completed')

        <div class="boq-flash mb-4">
            BOQ processed:
            {{ $processingBatch->message }}
            (Location:
            {{ $processingBatch->location }})
        </div>

    @elseif($generationSummary)

        <div class="boq-flash mb-4">

            BOQ generated using current prices for

            <strong>
                {{ $generationSummary['location'] }}
            </strong>.

            {{ $generationSummary['matched'] }}
            item(s) matched and

            {{ $generationSummary['unmatched'] }}
            require review.

        </div>

    @endif


    {{-- BOQ Information --}}
    <div class="boq-panel p-6 mb-6">

        <dl class="grid grid-cols-1 md:grid-cols-4 gap-6">

            <div>
                <dt class="text-sm font-medium text-gray-500">
                    Project
                </dt>

                <dd class="mt-1 text-sm text-gray-900">
                    {{ $boq->project?->name ?? '?' }}
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">
                    Currency
                </dt>

                <dd class="mt-1 text-sm text-gray-900">
                    {{ $boq->currency }}
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">
                    Version
                </dt>

                <dd class="mt-1 text-sm text-gray-900">
                    {{ $boq->version }}
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">
                    Source Type
                </dt>

                <dd class="mt-1 text-sm text-gray-900">
                    {{ $boq->source_type ?? '?' }}
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">
                    Pricing Location
                </dt>

                <dd class="mt-1 text-sm text-gray-900">
                    {{
                        $boq->project?->location
                        ?: $boq->project?->district
                        ?: $boq->project?->country
                        ?: 'Not set'
                    }}
                </dd>
            </div>

            @if($boq->description)

                <div class="md:col-span-4">

                    <dt class="text-sm font-medium text-gray-500">
                        Description
                    </dt>

                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $boq->description }}
                    </dd>

                </div>

            @endif

        </dl>

    </div>


    {{-- Item statistics --}}
    <div
        style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(130px,1fr));
            gap:.65rem;
            margin-bottom:1rem;
        "
    >

        @foreach([
            ['label' => 'Total Items', 'value' => $itemStats['total'], 'icon' => 'fa-list'],
            ['label' => 'Matched', 'value' => $itemStats['matched'], 'icon' => 'fa-link'],
            ['label' => 'Unmatched', 'value' => $itemStats['unmatched'], 'icon' => 'fa-link-slash'],
            ['label' => 'Pending', 'value' => $itemStats['pending'], 'icon' => 'fa-hourglass-half'],
            ['label' => 'Reviewed', 'value' => $itemStats['reviewed'], 'icon' => 'fa-eye'],
            ['label' => 'Approved', 'value' => $itemStats['approved'], 'icon' => 'fa-circle-check'],
            ['label' => 'Rejected', 'value' => $itemStats['rejected'], 'icon' => 'fa-circle-xmark'],
        ] as $stat)

            <div class="boq-stat-card boq-stat-green">

                <div>

                    <p class="boq-stat-label">
                        {{ $stat['label'] }}
                    </p>

                    <p class="boq-stat-value">
                        {{ $stat['value'] }}
                    </p>

                </div>

                <span class="boq-stat-icon">
                    <i class="fas {{ $stat['icon'] }}"></i>
                </span>

            </div>

        @endforeach

    </div>


    {{-- Items --}}
    <div class="boq-panel overflow-hidden">

        <div
            class="flex flex-col gap-1 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >

            <div>

                <h2 class="text-lg font-semibold text-gray-900">
                    Price review
                    ({{ $itemStats['total'] }} items)
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Only {{ $items->count() }}
                    row(s) are loaded on this page.
                </p>

            </div>

            <p class="text-xs text-gray-500">
                Suggested rates require review before approval.
            </p>

        </div>


        {{-- Pagination/search controls --}}
        <div
            style="
                display:grid;
                grid-template-columns:minmax(320px,1fr) 190px 100px auto;
                align-items:end;
                gap:.75rem;
                padding:1rem;
                border-bottom:1px solid var(--boq-border);
                background:#fff;
            "
        >

            <div>

                <label class="boq-field-label">
                    Search Items
                </label>

                <div class="boq-input-icon-wrap">

                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="itemSearch"
                        class="boq-field boq-field-with-icon"
                        placeholder="Search item code, description or unit..."
                    >

                </div>

            </div>


            <div>

                <label class="boq-field-label">
                    Status
                </label>

                <select
                    wire:model.live="itemStatus"
                    class="boq-field"
                >

                    <option value="all">
                        All Items
                    </option>

                    <option value="matched">
                        Matched
                    </option>

                    <option value="unmatched">
                        Unmatched
                    </option>

                    <option value="pending">
                        Pending
                    </option>

                    <option value="reviewed">
                        Reviewed
                    </option>

                    <option value="approved">
                        Approved
                    </option>

                    <option value="rejected">
                        Rejected
                    </option>

                </select>

            </div>


            <div>

                <label class="boq-field-label">
                    Rows
                </label>

                <select
                    wire:model.live="itemsPerPage"
                    class="boq-field"
                >

                    @foreach(
                        $itemsPerPageOptions
                        as $option
                    )

                        <option value="{{ $option }}">
                            {{ $option }}
                        </option>

                    @endforeach

                </select>

            </div>


            <div>

                @if(
                    $itemSearch !== ''
                    || $itemStatus !== 'all'
                )

                    <button
                        type="button"
                        wire:click="clearItemFilters"
                        class="boq-btn-secondary"
                    >
                        <i class="fas fa-filter-circle-xmark"></i>
                        Clear
                    </button>

                @endif

            </div>

        </div>


        @if($items->count() > 0)

            <div class="boq-table-wrapper">

                <table class="boq-table">

                    <thead>

                        <tr>

                            <th>
                                Item Code
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Unit
                            </th>

                            <th class="text-right">
                                Qty
                            </th>

                            <th class="text-right">
                                Original
                            </th>

                            <th class="text-right">
                                Suggested
                            </th>

                            <th class="text-right">
                                Reviewed
                            </th>

                            <th class="text-right">
                                Approved
                            </th>

                            <th class="text-right">
                                Amount
                            </th>

                            <th>
                                Status
                            </th>

                            <th class="text-right">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @foreach($items as $item)

                            <tr
                                wire:key="boq-item-{{ $item->id }}"
                            >

                                <td>
                                    {{ $item->item_code }}
                                </td>

                                <td style="max-width:320px">
                                    {{ $item->description }}
                                </td>

                                <td>
                                    {{ $item->unit }}
                                </td>

                                <td class="text-right">
                                    {{ number_format((float) $item->quantity, 2) }}
                                </td>

                                <td class="text-right">
                                    {{
                                        $item->original_rate !== null
                                            ? number_format(
                                                (float) $item->original_rate,
                                                2
                                            )
                                            : '-'
                                    }}
                                </td>

                                <td class="text-right">

                                    <div style="color:#4338ca">
                                        {{
                                            $item->ai_suggested_rate !== null
                                                ? number_format(
                                                    (float) $item->ai_suggested_rate,
                                                    2
                                                )
                                                : '-'
                                        }}
                                    </div>

                                    @if($item->ai_suggested_rate !== null)

                                        <div class="boq-table-subtitle">

                                            {{
                                                $item->ai_confidence !== null
                                                    ? number_format(
                                                        (float) $item->ai_confidence,
                                                        0
                                                    ).'%'
                                                    : 'No confidence'
                                            }}

                                            ·

                                            {{
                                                $item->location
                                                ?: 'No location'
                                            }}

                                        </div>

                                        @if($item->match_type)

                                            <div class="boq-table-subtitle">

                                                {{ ucfirst($item->match_type) }}
                                                match

                                                @if($item->matchedBy)
                                                    by
                                                    {{ $item->matchedBy->name }}
                                                @endif

                                            </div>

                                        @endif

                                    @endif

                                </td>

                                <td class="text-right">
                                    {{
                                        $item->reviewed_rate !== null
                                            ? number_format(
                                                (float) $item->reviewed_rate,
                                                2
                                            )
                                            : '-'
                                    }}
                                </td>

                                <td class="text-right">
                                    {{
                                        $item->approved_rate !== null
                                            ? number_format(
                                                (float) $item->approved_rate,
                                                2
                                            )
                                            : '-'
                                    }}
                                </td>

                                <td class="text-right">
                                    {{ number_format((float) $item->amount, 2) }}
                                </td>

                                <td>

                                    <span class="boq-badge
                                        {{
                                            $item->status === 'approved'
                                                ? 'boq-badge-success'
                                                : (
                                                    $item->status === 'rejected'
                                                        ? 'boq-badge-danger'
                                                        : (
                                                            $item->status === 'reviewed'
                                                                ? 'boq-badge-info'
                                                                : 'boq-badge-warning'
                                                        )
                                                )
                                        }}"
                                    >
                                        {{ $item->status }}
                                    </span>

                                    @if(
                                        $item->status === 'approved'
                                        && $item->approvedBy
                                    )

                                        <div class="boq-table-subtitle">
                                            Approved by
                                            {{ $item->approvedBy->name }}
                                            <br>
                                            {{ $item->approved_at?->format('M j, Y H:i') }}
                                        </div>

                                    @elseif(
                                        $item->status === 'reviewed'
                                        && $item->reviewedBy
                                    )

                                        <div class="boq-table-subtitle">
                                            Reviewed by
                                            {{ $item->reviewedBy->name }}
                                            <br>
                                            {{ $item->reviewed_at?->format('M j, Y H:i') }}
                                        </div>

                                    @elseif($item->status === 'rejected')

                                        <div
                                            class="boq-table-subtitle"
                                            style="color:#dc2626"
                                        >
                                            {{ $item->rejection_reason }}
                                        </div>

                                    @endif

                                </td>

                                <td class="text-right">

                                    <div class="boq-table-actions">

                                        @if(
                                            $canEdit
                                            && $item->status !== 'approved'
                                        )

                                            <button
                                                wire:click="startMatching({{ $item->id }})"
                                                type="button"
                                                class="boq-icon-btn"
                                                title="{{ $item->hardware_price_id ? 'Change Match' : 'Match' }}"
                                            >
                                                <i class="fas fa-link"></i>
                                            </button>

                                            <button
                                                wire:click="startReview({{ $item->id }})"
                                                type="button"
                                                class="boq-icon-btn"
                                                title="Review"
                                            >
                                                <i class="fas fa-pen"></i>
                                            </button>

                                            <button
                                                wire:click="startReject({{ $item->id }})"
                                                type="button"
                                                class="boq-icon-btn boq-icon-danger"
                                                title="Reject"
                                            >
                                                <i class="fas fa-xmark"></i>
                                            </button>

                                        @endif


                                        @if(
                                            $canApprove
                                            && $item->status === 'reviewed'
                                        )

                                            <button
                                                wire:click="approveItem({{ $item->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="approveItem({{ $item->id }})"
                                                type="button"
                                                class="boq-icon-btn"
                                                title="Approve"
                                            >
                                                <i class="fas fa-check"></i>
                                            </button>

                                        @endif

                                    </div>

                                </td>

                            </tr>


                            @if($matchingItemId === $item->id)

                                <tr
                                    wire:key="match-item-{{ $item->id }}"
                                    style="background:#faf5ff"
                                >

                                    <td colspan="11">

                                        <div style="padding:1rem">

                                            <div
                                                style="
                                                    display:flex;
                                                    justify-content:space-between;
                                                    gap:1rem;
                                                    align-items:flex-start;
                                                "
                                            >

                                                <div>

                                                    <h3
                                                        style="
                                                            margin:0;
                                                            font-weight:800;
                                                            color:#0f172a;
                                                        "
                                                    >
                                                        Match a current price
                                                    </h3>

                                                    <p class="boq-table-subtitle">
                                                        {{ $item->description }}
                                                        ·
                                                        {{ $item->unit ?: 'No unit' }}
                                                    </p>

                                                </div>

                                                <button
                                                    wire:click="closeEditor"
                                                    type="button"
                                                    class="boq-btn-secondary"
                                                >
                                                    Close
                                                </button>

                                            </div>


                                            @if($automaticCandidates)

                                                <div style="margin-top:1rem">

                                                    <div class="boq-field-label">
                                                        Automatic Candidates
                                                    </div>

                                                    <div
                                                        style="
                                                            display:grid;
                                                            grid-template-columns:repeat(3,minmax(0,1fr));
                                                            gap:.65rem;
                                                        "
                                                    >

                                                        @foreach($automaticCandidates as $candidate)

                                                            <div
                                                                wire:key="automatic-candidate-{{ $candidate['id'] }}"
                                                                class="boq-panel"
                                                                style="padding:.8rem"
                                                            >

                                                                <div class="boq-table-title">
                                                                    {{ $candidate['item_name'] }}
                                                                </div>

                                                                <div class="boq-table-subtitle">
                                                                    {{
                                                                        collect([
                                                                            $candidate['brand'],
                                                                            $candidate['specification'],
                                                                            $candidate['category']
                                                                        ])
                                                                            ->filter()
                                                                            ->join(' · ')
                                                                    }}
                                                                </div>

                                                                <div
                                                                    style="
                                                                        margin-top:.6rem;
                                                                        font-size:.72rem;
                                                                        color:#64748b;
                                                                    "
                                                                >
                                                                    {{ $candidate['unit'] }}
                                                                    ·
                                                                    {{ $candidate['supplier'] }}
                                                                    ·
                                                                    {{ $candidate['location'] ?: 'No location' }}
                                                                </div>

                                                                <div
                                                                    style="
                                                                        display:flex;
                                                                        align-items:center;
                                                                        justify-content:space-between;
                                                                        gap:.5rem;
                                                                        margin-top:.7rem;
                                                                    "
                                                                >

                                                                    <strong>
                                                                        {{ $candidate['currency'] }}
                                                                        {{ number_format((float) $candidate['price'], 2) }}
                                                                    </strong>

                                                                    <button
                                                                        wire:click="selectHardwarePrice({{ $item->id }}, {{ $candidate['id'] }})"
                                                                        wire:loading.attr="disabled"
                                                                        type="button"
                                                                        class="boq-btn-primary"
                                                                    >
                                                                        Select
                                                                    </button>

                                                                </div>

                                                            </div>

                                                        @endforeach

                                                    </div>

                                                </div>

                                            @endif


                                            <div
                                                style="
                                                    margin-top:1rem;
                                                    border-top:1px solid #e2e8f0;
                                                    padding-top:1rem;
                                                "
                                            >

                                                <label
                                                    for="price-search-{{ $item->id }}"
                                                    class="boq-field-label"
                                                >
                                                    Search Active Prices
                                                </label>

                                                <input
                                                    id="price-search-{{ $item->id }}"
                                                    wire:model.live.debounce.350ms="matchSearch"
                                                    type="search"
                                                    maxlength="100"
                                                    class="boq-field"
                                                    placeholder="Search item, brand, specification, category or supplier"
                                                >

                                            </div>


                                            <div
                                                wire:loading.class="opacity-50"
                                                wire:target="matchSearch"
                                                style="
                                                    display:grid;
                                                    grid-template-columns:repeat(3,minmax(0,1fr));
                                                    gap:.65rem;
                                                    margin-top:.75rem;
                                                "
                                            >

                                                @forelse($matchResults as $candidate)

                                                    <div
                                                        wire:key="search-candidate-{{ $candidate['id'] }}"
                                                        class="boq-panel"
                                                        style="padding:.8rem"
                                                    >

                                                        <div class="boq-table-title">
                                                            {{ $candidate['item_name'] }}
                                                        </div>

                                                        <div class="boq-table-subtitle">
                                                            {{
                                                                collect([
                                                                    $candidate['brand'],
                                                                    $candidate['specification'],
                                                                    $candidate['category']
                                                                ])
                                                                    ->filter()
                                                                    ->join(' · ')
                                                            }}
                                                        </div>

                                                        <div
                                                            style="
                                                                margin-top:.6rem;
                                                                font-size:.72rem;
                                                                color:#64748b;
                                                            "
                                                        >
                                                            {{ $candidate['unit'] }}
                                                            ·
                                                            {{ $candidate['supplier'] }}
                                                            ·
                                                            {{ $candidate['location'] ?: 'No location' }}
                                                        </div>

                                                        <div
                                                            style="
                                                                display:flex;
                                                                align-items:center;
                                                                justify-content:space-between;
                                                                gap:.5rem;
                                                                margin-top:.7rem;
                                                            "
                                                        >

                                                            <strong>
                                                                {{ $candidate['currency'] }}
                                                                {{ number_format((float) $candidate['price'], 2) }}
                                                            </strong>

                                                            <button
                                                                wire:click="selectHardwarePrice({{ $item->id }}, {{ $candidate['id'] }})"
                                                                wire:loading.attr="disabled"
                                                                type="button"
                                                                class="boq-btn-primary"
                                                            >
                                                                Select
                                                            </button>

                                                        </div>

                                                    </div>

                                                @empty

                                                    <div
                                                        style="
                                                            grid-column:1/-1;
                                                            padding:1.2rem;
                                                            text-align:center;
                                                            color:#64748b;
                                                        "
                                                    >
                                                        No active prices match this search.
                                                    </div>

                                                @endforelse

                                            </div>

                                        </div>

                                    </td>

                                </tr>


                            @elseif($reviewingItemId === $item->id)

                                <tr
                                    wire:key="review-item-{{ $item->id }}"
                                    style="background:#eef2ff"
                                >

                                    <td colspan="11">

                                        <div
                                            style="
                                                display:grid;
                                                grid-template-columns:minmax(150px,1fr) minmax(250px,2fr) auto;
                                                gap:.75rem;
                                                align-items:end;
                                                padding:1rem;
                                            "
                                        >

                                            <div>

                                                <label
                                                    for="manual-rate-{{ $item->id }}"
                                                    class="boq-field-label"
                                                >
                                                    Reviewed Rate
                                                </label>

                                                <input
                                                    id="manual-rate-{{ $item->id }}"
                                                    wire:model="manualRate"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="boq-field"
                                                >

                                                @error('manualRate')
                                                    <div class="boq-field-error">
                                                        {{ $message }}
                                                    </div>
                                                @enderror

                                            </div>


                                            <div>

                                                <label
                                                    for="review-notes-{{ $item->id }}"
                                                    class="boq-field-label"
                                                >
                                                    Review Notes
                                                </label>

                                                <input
                                                    id="review-notes-{{ $item->id }}"
                                                    wire:model="reviewNotes"
                                                    type="text"
                                                    maxlength="2000"
                                                    class="boq-field"
                                                    placeholder="Reason or supporting context"
                                                >

                                            </div>


                                            <div
                                                style="
                                                    display:flex;
                                                    gap:.4rem;
                                                "
                                            >

                                                @if($item->ai_suggested_rate !== null)

                                                    <button
                                                        wire:click="reviewUsingSuggested({{ $item->id }})"
                                                        type="button"
                                                        class="boq-btn-secondary"
                                                    >
                                                        Use Suggested
                                                    </button>

                                                @endif

                                                <button
                                                    wire:click="reviewItem({{ $item->id }})"
                                                    type="button"
                                                    class="boq-btn-primary"
                                                >
                                                    Save Review
                                                </button>

                                                <button
                                                    wire:click="closeEditor"
                                                    type="button"
                                                    class="boq-btn-secondary"
                                                >
                                                    Cancel
                                                </button>

                                            </div>

                                        </div>

                                    </td>

                                </tr>


                            @elseif($rejectingItemId === $item->id)

                                <tr
                                    wire:key="reject-item-{{ $item->id }}"
                                    style="background:#fef2f2"
                                >

                                    <td colspan="11">

                                        <div style="padding:1rem">

                                            <label
                                                for="rejection-reason-{{ $item->id }}"
                                                class="boq-field-label"
                                            >
                                                Rejection Reason
                                            </label>

                                            <textarea
                                                id="rejection-reason-{{ $item->id }}"
                                                wire:model="rejectionReason"
                                                rows="2"
                                                maxlength="2000"
                                                class="boq-field boq-textarea"
                                                placeholder="Explain why this item is rejected"
                                            ></textarea>

                                            @error('rejectionReason')

                                                <div class="boq-field-error">
                                                    {{ $message }}
                                                </div>

                                            @enderror

                                            <div
                                                style="
                                                    display:flex;
                                                    gap:.5rem;
                                                    justify-content:flex-end;
                                                    margin-top:.75rem;
                                                "
                                            >

                                                <button
                                                    wire:click="closeEditor"
                                                    type="button"
                                                    class="boq-btn-secondary"
                                                >
                                                    Cancel
                                                </button>

                                                <button
                                                    wire:click="rejectItem({{ $item->id }})"
                                                    type="button"
                                                    class="boq-btn-danger"
                                                >
                                                    Reject Item
                                                </button>

                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            @endif

                        @endforeach

                    </tbody>

                </table>

            </div>


            @if($items->hasPages())

                <div class="boq-pagination">
                    {{ $items->links() }}
                </div>

            @endif

        @else

            <div class="boq-empty-state">

                <div class="boq-empty-icon">
                    <i class="fas fa-list"></i>
                </div>

                <p class="boq-empty-title">
                    No BOQ items found
                </p>

                <p class="boq-empty-description">
                    Try changing the search or status filter.
                </p>

                @if(
                    $itemSearch !== ''
                    || $itemStatus !== 'all'
                )

                    <button
                        type="button"
                        wire:click="clearItemFilters"
                        class="boq-btn-secondary"
                        style="margin-top:.8rem"
                    >
                        Clear Filters
                    </button>

                @endif

            </div>

        @endif

    </div>

</div>
