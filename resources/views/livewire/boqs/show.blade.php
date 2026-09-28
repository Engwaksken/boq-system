<div @if($this->isProcessing) wire:poll.2s="refreshProcessingStatus" @endif class="boq-page-stack">

    @php
        $canEdit = Auth::user()->hasPermission('boq.edit');
        $canApprove = Auth::user()->hasPermission('boq.approve');
        $reviewedCount = $itemStats['reviewed'] ?? 0;
        $processingBatch = $this->processingBatch;
        $isProcessing = $this->isProcessing;
        $pricingLocation = $boq->project?->location ?: $boq->project?->district ?: $boq->project?->country;
        $rate = fn ($value) => $value !== null ? \App\Support\Format::number((float) $value, 2) : '—';
        $statFilters = [
            ['key' => 'all', 'label' => __('Total Items'), 'value' => $itemStats['total'], 'icon' => 'fa-list', 'color' => 'green'],
            ['key' => 'matched', 'label' => __('Matched'), 'value' => $itemStats['matched'], 'icon' => 'fa-link', 'color' => 'blue'],
            ['key' => 'unmatched', 'label' => __('Unmatched'), 'value' => $itemStats['unmatched'], 'icon' => 'fa-link-slash', 'color' => 'amber'],
            ['key' => 'pending', 'label' => __('Pending'), 'value' => $itemStats['pending'], 'icon' => 'fa-hourglass-half', 'color' => 'amber'],
            ['key' => 'reviewed', 'label' => __('Reviewed'), 'value' => $itemStats['reviewed'], 'icon' => 'fa-eye', 'color' => 'purple'],
            ['key' => 'approved', 'label' => __('Approved'), 'value' => $itemStats['approved'], 'icon' => 'fa-circle-check', 'color' => 'green'],
            ['key' => 'rejected', 'label' => __('Rejected'), 'value' => $itemStats['rejected'], 'icon' => 'fa-circle-xmark', 'color' => 'red'],
        ];
    @endphp

    {{-- ============================ HEADER ============================ --}}
    <x-ui.page-header :title="$boq->name" icon="fa-file-invoice-dollar">
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <x-ui.status :status="$boq->status" />
            <span class="boq-version-badge"><i class="fas fa-code-branch" aria-hidden="true"></i> v{{ $boq->version ?? 1 }}</span>
            @if($boq->currency)
                <span class="boq-currency-badge">{{ $boq->currency }}</span>
            @endif
            @if($boq->project)
                <a href="{{ route('projects.show', $boq->project->id) }}" class="boq-cell-with-icon hover:text-brand-700">
                    <i class="fas fa-folder-open" aria-hidden="true"></i> {{ $boq->project->name }}
                </a>
            @endif
        </div>

        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="route('boqs.index')">{{ __('Back') }}</x-ui.button>

            @if(isset($pdfUrl) && $pdfUrl)
                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.stop="open = false">
                    <button type="button" class="boq-btn-secondary" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="menu">
                        <i class="fas fa-share-nodes" aria-hidden="true"></i> {{ __('PDF & Share') }}
                        <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                    </button>

                    <div x-show="open" x-cloak x-transition.origin.top.right class="boq-menu w-60" role="menu">
                        <button type="button" wire:click="openPdfPreview" @click="open = false" class="boq-menu-item" role="menuitem">
                            <i class="fas fa-eye" aria-hidden="true"></i> {{ __('Preview PDF') }}
                        </button>
                        <a href="{{ $pdfUrl }}" class="boq-menu-item" role="menuitem">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i> {{ __('Download PDF') }}
                        </a>
                        <div class="boq-menu-sep" role="separator"></div>
                        <button type="button" wire:click="openEmailShare" @click="open = false" class="boq-menu-item" role="menuitem">
                            <i class="fas fa-envelope" aria-hidden="true"></i> {{ __('Share by Email') }}
                        </button>
                        <a href="{{ app(\App\Services\BoqShareService::class)->whatsappUrl($boq) }}" target="_blank" rel="noopener noreferrer" class="boq-menu-item" role="menuitem">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i> {{ __('Share on WhatsApp') }}
                        </a>
                    </div>
                </div>
            @endif

            @can('delete', $boq)
                <button
                    type="button"
                    wire:click="deleteBoq"
                    wire:confirm="{{ __('Delete this BOQ? An administrator can restore it if needed.') }}"
                    class="boq-btn-secondary text-red-600 hover:text-red-700"
                >
                    <i class="fas fa-trash" aria-hidden="true"></i>
                    {{ __('Delete') }}
                </button>
            @endcan

            @if($canApprove && $reviewedCount > 0)
                <button
                    wire:click="approveAllReviewed"
                    wire:loading.attr="disabled"
                    wire:target="approveAllReviewed"
                    type="button"
                    class="boq-btn-secondary"
                >
                    <i class="fas fa-circle-check text-emerald-600" aria-hidden="true"></i>
                    {{ __('Approve reviewed (:count)', ['count' => $reviewedCount]) }}
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
                    <i wire:loading.remove wire:target="generateBoq" class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
                    <i wire:loading wire:target="generateBoq" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <span wire:loading.remove wire:target="generateBoq">{{ __('Generate BOQ') }}</span>
                    <span wire:loading wire:target="generateBoq">{{ __('Generating...') }}</span>
                </button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ============================ MESSAGES ============================ --}}
    @foreach(['boq', 'approval', 'review'] as $errorKey)
        @error($errorKey)
            <x-ui.alert type="error">{{ $message }}</x-ui.alert>
        @enderror
    @endforeach

    <x-ui.flash :keys="['status', 'message']" :types="['message' => 'warning']" />

    @if($isProcessing && $processingBatch && $processingBatch->status === 'queued' && $processingBatch->created_at?->lt(now()->subMinutes(2)))
        <x-ui.alert type="warning">
            {{ __('Processing has not started yet. The server processes queued work every minute; if this stays for more than a few minutes, ask your administrator to check the scheduler/queue (see /health).') }}
        </x-ui.alert>
    @endif

    @if($isProcessing && $processingBatch)
        @php
            $pct = $processingBatch->total_items > 0
                ? min(100, (int) round($processingBatch->processed_items / max(1, $processingBatch->total_items) * 100))
                : 25;
        @endphp

        <section class="boq-card border-brand-200 bg-brand-50/60" role="status" aria-live="polite">
            <div class="boq-card-body">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="boq-stat-icon bg-white"><i class="fas fa-gear fa-spin" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-brand-900">
                                {{ __('Processing BOQ') }} — {{ __(ucfirst(str_replace('_', ' ', $processingBatch->current_stage ?? $processingBatch->status))) }}
                            </p>
                            <p class="mt-0.5 text-xs text-brand-800">{{ $processingBatch->message ?: __('Working on your BOQ...') }}</p>
                            @if($processingBatch->location)
                                <p class="mt-1 text-xs text-brand-700"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $processingBatch->location }}</p>
                            @endif
                        </div>
                    </div>

                    <span class="shrink-0 text-xs font-semibold tabular-nums text-brand-800">
                        {{ \App\Support\Format::number($processingBatch->processed_items ?? 0, 0) }} / {{ $processingBatch->total_items ? \App\Support\Format::number($processingBatch->total_items, 0) : '…' }}
                    </span>
                </div>

                <div class="boq-progress mt-3 bg-white" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}" aria-label="{{ __('Processing progress') }}">
                    <span style="width: {{ $pct }}%"></span>
                </div>
            </div>
        </section>
    @elseif($processingBatch && $processingBatch->status === 'failed')
        <x-ui.alert type="error" :title="__('Processing failed')">
            {{ $processingBatch->error_message ?: __('Unknown error.') }}
        </x-ui.alert>
    @elseif($processingBatch && $processingBatch->status === 'completed_with_errors')
        <x-ui.alert type="warning" :title="trans_choice('Completed with :count error|Completed with :count errors', (int) $processingBatch->failed_items, ['count' => (int) $processingBatch->failed_items])">
            {{ $processingBatch->message }}
        </x-ui.alert>
    @elseif($processingBatch && $processingBatch->status === 'completed')
        <x-ui.alert type="success" :title="__('BOQ processed')">
            {{ $processingBatch->message }}
            @if($processingBatch->location)
                <span class="whitespace-nowrap">({{ __('Location') }}: {{ $processingBatch->location }})</span>
            @endif
        </x-ui.alert>
    @elseif($generationSummary)
        <x-ui.alert type="success">
            {{ __('BOQ generated using current prices for') }}
            <strong>{{ $generationSummary['location'] ?? '' }}</strong>.
            {{ __(':matched item(s) matched and :unmatched require review.', ['matched' => $generationSummary['matched'] ?? 0, 'unmatched' => $generationSummary['unmatched'] ?? 0]) }}
        </x-ui.alert>
    @endif

    {{-- ============================ DETAILS ============================ --}}
    <x-ui.card :padded="true">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 md:grid-cols-5">
            <div class="min-w-0">
                <dt class="text-xs font-semibold text-slate-500">{{ __('Project') }}</dt>
                <dd class="mt-0.5 truncate text-sm font-medium text-slate-900">{{ $boq->project?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-slate-500">{{ __('Currency') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $boq->currency ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-slate-500">{{ __('Version') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $boq->version ?? 1 }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-slate-500">{{ __('Source Type') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $boq->source_type ? __(ucfirst($boq->source_type)) : '—' }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-semibold text-slate-500">{{ __('Pricing Location') }}</dt>
                <dd class="mt-0.5 truncate text-sm font-medium text-slate-900">{{ $pricingLocation ?: __('Not set') }}</dd>
            </div>

            @if($boq->description)
                <div class="col-span-2 md:col-span-5">
                    <dt class="text-xs font-semibold text-slate-500">{{ __('Description') }}</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-700">{{ $boq->description }}</dd>
                </div>
            @endif
        </dl>
    </x-ui.card>

    {{-- Item statistics (click to filter the price review table) --}}
    <div class="boq-stats-compact grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
        @foreach($statFilters as $stat)
            <x-stat-card
                :label="$stat['label']"
                :value="\App\Support\Format::number($stat['value'] ?? 0, 0)"
                :icon="$stat['icon']"
                :color="$stat['color']"
                :active="$itemStatus === $stat['key'] && $stat['key'] !== 'all'"
                wire:click="$set('itemStatus', '{{ $stat['key'] }}')"
                wire:key="item-stat-{{ $stat['key'] }}"
            />
        @endforeach
    </div>

    {{-- Estimated vs generated totals --}}
    <x-boq-totals :totals="$totals" :currency="$boq->currency">
        @can('update', $boq)
            <x-ui.button variant="secondary" size="sm" icon="fa-download" :href="route('boqs.estimates-template', $boq)">{{ __('Template') }}</x-ui.button>

            <form wire:submit="uploadEstimates" class="flex flex-wrap items-center gap-2">
                <label for="estimates-file" class="sr-only">{{ __('Upload estimated prices') }}</label>
                <input id="estimates-file" type="file" wire:model="estimatesFile" accept=".xlsx,.xlsm,.ods,.csv,.tsv,.txt" class="max-w-[15rem] !py-1 text-xs">
                <button type="submit" class="boq-btn-primary boq-btn-sm" wire:loading.attr="disabled" wire:target="estimatesFile,uploadEstimates">
                    <i class="fas fa-upload" wire:loading.remove wire:target="estimatesFile,uploadEstimates" aria-hidden="true"></i>
                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="estimatesFile,uploadEstimates" aria-hidden="true"></i>
                    {{ __('Upload estimated prices') }}
                </button>
            </form>
        @endcan
    </x-boq-totals>

    @error('estimatesFile')
        <x-ui.alert type="error">{{ $message }}</x-ui.alert>
    @enderror

    {{-- ============================ PRICE REVIEW ============================ --}}
    <section class="boq-panel">
        <div class="boq-card-header">
            <div class="min-w-0">
                <h2 class="boq-card-title">
                    <i class="fas fa-scale-balanced" aria-hidden="true"></i>
                    {{ __('Price review') }}
                    <span class="boq-badge">{{ trans_choice(':count item|:count items', $itemStats['total'], ['count' => \App\Support\Format::number($itemStats['total'], 0)]) }}</span>
                </h2>
                <p class="boq-card-subtitle">{{ __('Suggested rates require review before approval.') }}</p>
            </div>
        </div>

        <div class="boq-toolbar border-b border-slate-200">
            <x-ui.field :label="__('Search Items')" for="item-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input
                        id="item-search"
                        type="search"
                        wire:model.live.debounce.300ms="itemSearch"
                        class="boq-field boq-field-with-icon"
                        placeholder="{{ __('Search item code, description or unit...') }}"
                    >
                </div>
            </x-ui.field>

            <x-ui.field :label="__('Status')" for="item-status" class="w-full sm:w-44">
                <select id="item-status" wire:model.live="itemStatus" class="boq-field">
                    <option value="all">{{ __('All Items') }}</option>
                    <option value="matched">{{ __('Matched') }}</option>
                    <option value="unmatched">{{ __('Unmatched') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="reviewed">{{ __('Reviewed') }}</option>
                    <option value="approved">{{ __('Approved') }}</option>
                    <option value="rejected">{{ __('Rejected') }}</option>
                </select>
            </x-ui.field>

            <x-ui.field :label="__('Rows')" for="item-rows" class="w-full sm:w-24">
                <select id="item-rows" wire:model.live="itemsPerPage" class="boq-field">
                    @foreach($itemsPerPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            @if($itemSearch !== '' || $itemStatus !== 'all')
                <button type="button" wire:click="clearItemFilters" class="boq-btn-ghost">
                    <i class="fas fa-filter-circle-xmark" aria-hidden="true"></i>
                    {{ __('Clear') }}
                </button>
            @endif
        </div>

        <div class="boq-loading-bar" wire:loading.delay wire:target="itemSearch, itemStatus, itemsPerPage, clearItemFilters, gotoPage, nextPage, previousPage"></div>

        @if($items->count() > 0)
            <x-ui.table>
                <thead>
                    <tr>
                        <th>{{ __('Item Code') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th class="text-right">{{ __('Qty') }}</th>
                        <th class="text-right">{{ __('Original') }}</th>
                        <th class="text-right">{{ __('Suggested') }}</th>
                        <th class="text-right">{{ __('Reviewed') }}</th>
                        <th class="text-right">{{ __('Approved') }}</th>
                        <th class="text-right">{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($items as $item)
                        <tr wire:key="boq-item-{{ $item->id }}" @class(['is-selected' => in_array($item->id, [$matchingItemId, $reviewingItemId, $rejectingItemId], true)])>
                            <td class="whitespace-nowrap"><span class="boq-code">{{ $item->item_code ?: '—' }}</span></td>

                            <td class="min-w-[13rem] max-w-[22rem]">
                                <span class="block text-slate-800">{{ $item->description }}</span>
                            </td>

                            <td class="whitespace-nowrap">{{ $item->unit ?: '—' }}</td>

                            <td class="is-numeric">{{ \App\Support\Format::number((float) $item->quantity, 2) }}</td>

                            <td class="is-numeric">{{ $rate($item->original_rate) }}</td>

                            <td class="is-numeric">
                                <div class="font-semibold text-brand-700">{{ $rate($item->ai_suggested_rate) }}</div>

                                @if($item->ai_suggested_rate !== null)
                                    <div class="boq-table-subtitle whitespace-normal">
                                        {{ $item->ai_confidence !== null ? \App\Support\Format::number((float) $item->ai_confidence, 0).'%' : __('No confidence') }}
                                        · {{ $item->location ?: __('No location') }}
                                    </div>

                                    @if($item->match_type)
                                        <div class="boq-table-subtitle whitespace-normal">
                                            {{ __(':type match', ['type' => __(ucfirst($item->match_type))]) }}
                                            @if($item->matchedBy)
                                                {{ __('by :name', ['name' => $item->matchedBy->name]) }}
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </td>

                            <td class="is-numeric">{{ $rate($item->reviewed_rate) }}</td>

                            <td class="is-numeric">{{ $rate($item->approved_rate) }}</td>

                            <td class="is-numeric font-semibold text-slate-900">{{ \App\Support\Format::number((float) $item->amount, 2) }}</td>

                            <td>
                                <x-ui.status :status="$item->status" />

                                @if($item->status === 'approved' && $item->approvedBy)
                                    <div class="boq-table-subtitle">
                                        {{ __('Approved by :name', ['name' => $item->approvedBy->name]) }}
                                        <br><x-date :value="$item->approved_at" time />
                                    </div>
                                @elseif($item->status === 'reviewed' && $item->reviewedBy)
                                    <div class="boq-table-subtitle">
                                        {{ __('Reviewed by :name', ['name' => $item->reviewedBy->name]) }}
                                        <br><x-date :value="$item->reviewed_at" time />
                                    </div>
                                @elseif($item->status === 'rejected' && $item->rejection_reason)
                                    <div class="boq-table-subtitle max-w-[12rem] text-red-600">{{ $item->rejection_reason }}</div>
                                @endif
                            </td>

                            <td class="text-right">
                                <div class="boq-table-actions">
                                    @if($canEdit && $item->status !== 'approved')
                                        <button
                                            wire:click="startMatching({{ $item->id }})"
                                            type="button"
                                            class="boq-icon-btn"
                                            title="{{ $item->hardware_price_id ? __('Change Match') : __('Match') }}"
                                            aria-label="{{ $item->hardware_price_id ? __('Change Match') : __('Match') }}"
                                        >
                                            <i class="fas fa-link" aria-hidden="true"></i>
                                        </button>

                                        <button wire:click="startReview({{ $item->id }})" type="button" class="boq-icon-btn" title="{{ __('Review') }}" aria-label="{{ __('Review') }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i>
                                        </button>

                                        <button wire:click="startReject({{ $item->id }})" type="button" class="boq-icon-btn boq-icon-danger" title="{{ __('Reject') }}" aria-label="{{ __('Reject') }}">
                                            <i class="fas fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    @endif

                                    @if($canApprove && $item->status === 'reviewed')
                                        <button
                                            wire:click="approveItem({{ $item->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="approveItem({{ $item->id }})"
                                            type="button"
                                            class="boq-icon-btn boq-icon-success"
                                            title="{{ __('Approve') }}"
                                            aria-label="{{ __('Approve') }}"
                                        >
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @if($matchingItemId === $item->id)
                            <tr wire:key="match-item-{{ $item->id }}" class="bg-brand-50/50 hover:bg-brand-50/50">
                                <td colspan="11" class="!p-0">
                                    <div class="sticky left-0 max-w-[calc(100vw-2.5rem)] p-4 sm:p-5 lg:max-w-none">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <h3 class="text-sm font-bold text-slate-900">{{ __('Match a current price') }}</h3>
                                                <p class="boq-table-subtitle">{{ $item->description }} · {{ $item->unit ?: __('No unit') }}</p>
                                            </div>

                                            <x-ui.button variant="secondary" size="sm" icon="fa-xmark" wire:click="closeEditor">{{ __('Close') }}</x-ui.button>
                                        </div>

                                        @if($automaticCandidates)
                                            <p class="boq-field-label mt-4">{{ __('Automatic Candidates') }}</p>

                                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                @foreach($automaticCandidates as $candidate)
                                                    @include('livewire.boqs.partials.price-candidate', ['candidate' => $candidate, 'item' => $item, 'keyPrefix' => 'automatic-candidate'])
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="mt-4 border-t border-slate-200 pt-4">
                                            <x-ui.field :label="__('Search Active Prices')" for="price-search-{{ $item->id }}">
                                                <div class="boq-input-icon-wrap">
                                                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                                                    <input
                                                        id="price-search-{{ $item->id }}"
                                                        wire:model.live.debounce.350ms="matchSearch"
                                                        type="search"
                                                        maxlength="100"
                                                        class="boq-field boq-field-with-icon"
                                                        placeholder="{{ __('Search item, brand, specification, category or supplier') }}"
                                                    >
                                                </div>
                                            </x-ui.field>
                                        </div>

                                        <div wire:loading.class="opacity-50" wire:target="matchSearch" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                            @forelse($matchResults as $candidate)
                                                @include('livewire.boqs.partials.price-candidate', ['candidate' => $candidate, 'item' => $item, 'keyPrefix' => 'search-candidate'])
                                            @empty
                                                <div class="col-span-full rounded-lg border border-dashed border-slate-300 bg-white p-5 text-center text-sm text-slate-500">
                                                    {{ __('No active prices match this search.') }}
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @elseif($reviewingItemId === $item->id)
                            <tr wire:key="review-item-{{ $item->id }}" class="bg-brand-50/50 hover:bg-brand-50/50">
                                <td colspan="11" class="!p-0">
                                    <div class="sticky left-0 grid max-w-[calc(100vw-2.5rem)] gap-3 p-4 sm:p-5 md:grid-cols-[minmax(10rem,1fr)_minmax(16rem,2fr)_auto] md:items-end lg:max-w-none">
                                        <x-ui.field :label="__('Reviewed Rate')" for="manual-rate-{{ $item->id }}" error="manualRate">
                                            <input
                                                placeholder="0.00"
                                                id="manual-rate-{{ $item->id }}"
                                                wire:model="manualRate"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                inputmode="decimal"
                                                class="boq-field @error('manualRate') has-error @enderror"
                                            >
                                        </x-ui.field>

                                        <x-ui.field :label="__('Review Notes')" for="review-notes-{{ $item->id }}">
                                            <input
                                                id="review-notes-{{ $item->id }}"
                                                wire:model="reviewNotes"
                                                type="text"
                                                maxlength="2000"
                                                class="boq-field"
                                                placeholder="{{ __('Reason or supporting context') }}"
                                            >
                                        </x-ui.field>

                                        <div class="flex flex-wrap gap-2">
                                            @if($item->ai_suggested_rate !== null)
                                                <x-ui.button variant="secondary" wire:click="reviewUsingSuggested({{ $item->id }})">{{ __('Use Suggested') }}</x-ui.button>
                                            @endif

                                            <x-ui.button icon="fa-floppy-disk" wire:click="reviewItem({{ $item->id }})" loading="reviewItem">{{ __('Save Review') }}</x-ui.button>
                                            <x-ui.button variant="ghost" wire:click="closeEditor">{{ __('Cancel') }}</x-ui.button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @elseif($rejectingItemId === $item->id)
                            <tr wire:key="reject-item-{{ $item->id }}" class="bg-red-50/60 hover:bg-red-50/60">
                                <td colspan="11" class="!p-0">
                                    <div class="sticky left-0 max-w-[calc(100vw-2.5rem)] p-4 sm:p-5 lg:max-w-none">
                                        <x-ui.field :label="__('Rejection Reason')" for="rejection-reason-{{ $item->id }}" error="rejectionReason">
                                            <textarea
                                                id="rejection-reason-{{ $item->id }}"
                                                wire:model="rejectionReason"
                                                rows="2"
                                                maxlength="2000"
                                                class="boq-field boq-textarea @error('rejectionReason') has-error @enderror"
                                                placeholder="{{ __('Explain why this item is rejected') }}"
                                            ></textarea>
                                        </x-ui.field>

                                        <div class="mt-3 flex flex-wrap justify-end gap-2">
                                            <x-ui.button variant="secondary" wire:click="closeEditor">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button variant="danger" icon="fa-xmark" wire:click="rejectItem({{ $item->id }})" loading="rejectItem">{{ __('Reject Item') }}</x-ui.button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </x-ui.table>

            @if($items->hasPages())
                <div class="boq-pagination">
                    {{ $items->links() }}
                </div>
            @endif
        @else
            @if($itemSearch !== '' || $itemStatus !== 'all')
                <x-ui.empty-state
                    icon="fa-filter"
                    :title="__('No BOQ items found')"
                    :description="__('Try changing the search or status filter.')"
                >
                    <x-ui.button variant="secondary" wire:click="clearItemFilters">{{ __('Clear Filters') }}</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="fa-list"
                    :title="__('No BOQ items found')"
                    :description="__('Items appear here once the BOQ file has been read. Press \'Generate BOQ\' to extract and price them.')"
                />
            @endif
        @endif
    </section>

    {{-- ============================ MODALS ============================ --}}
    @if($showPdfPreview)
        <x-ui.modal wire:key="pdf-preview" id="pdf-preview" :title="__('PDF Preview')" icon="fa-file-pdf" size="xl" close="closePdfPreview" body-class="!p-0">
            <div class="bg-slate-100">
                <iframe src="{{ $pdfUrl.(str_contains($pdfUrl, '?') ? '&' : '?').'inline=1' }}" title="{{ __('PDF Preview') }}" class="block h-[70vh] w-full border-0"></iframe>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closePdfPreview">{{ __('Close') }}</x-ui.button>
                <x-ui.button icon="fa-download" :href="$pdfUrl">{{ __('Download PDF') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if($showEmailShare)
        <x-ui.modal wire:key="email-share" id="email-share" :title="__('Share by Email')" icon="fa-envelope" size="sm" close="closeEmailShare" submit="sendShareEmail">
            <div class="space-y-4">
                <x-ui.field :label="__('Recipient email')" for="share-email" error="shareEmail" required>
                    <input id="share-email" type="email" wire:model="shareEmail" class="boq-field @error('shareEmail') has-error @enderror" placeholder="{{ __('e.g. name@example.com') }}" autocomplete="email">
                </x-ui.field>

                <x-ui.field :label="__('Subject')" for="share-subject" error="shareSubject" required>
                    <input id="share-subject" type="text" wire:model="shareSubject" class="boq-field @error('shareSubject') has-error @enderror" placeholder="{{ __('Subject') }}">
                </x-ui.field>

                <x-ui.field :label="__('Message (optional)')" for="share-message">
                    <textarea id="share-message" wire:model="shareMessage" rows="4" class="boq-field boq-textarea" placeholder="{{ __('Add a short note for the recipient...') }}"></textarea>
                </x-ui.field>

                <p class="boq-field-help"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ __('The BOQ PDF is attached, branded with the owner\'s company details.') }}</p>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeEmailShare">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-paper-plane" loading="sendShareEmail">{{ __('Send') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
