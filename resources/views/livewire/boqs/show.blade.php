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
            ['key' => 'unpriced', 'label' => __('No price'), 'value' => $itemStats['unpriced'], 'icon' => 'fa-tag', 'color' => 'red'],
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
                    wire:click="openGenerate"
                    wire:loading.attr="disabled"
                    wire:target="generateBoq,openGenerate"
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
            @if($processingBatch->error_message)
                <p class="mt-1 font-medium">{{ $processingBatch->error_message }}</p>
            @endif
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

    <div x-data="{ boqTab: 'items' }" class="space-y-4">
    <x-ui.tabs :label="__('BOQ sections')">
        <x-ui.tab @click="boqTab = 'details'" :active="false" icon="fa-circle-info" x-bind:class="boqTab === 'details' ? 'is-active' : ''" x-bind:aria-pressed="boqTab === 'details' ? 'true' : 'false'">{{ __('Details & totals') }}</x-ui.tab>
        <x-ui.tab @click="boqTab = 'items'" :active="false" icon="fa-list-check" x-bind:class="boqTab === 'items' ? 'is-active' : ''" x-bind:aria-pressed="boqTab === 'items' ? 'true' : 'false'">{{ __('Items & pricing') }}</x-ui.tab>
        <x-ui.tab @click="boqTab = 'signatures'" :active="false" icon="fa-signature" x-bind:class="boqTab === 'signatures' ? 'is-active' : ''" x-bind:aria-pressed="boqTab === 'signatures' ? 'true' : 'false'">{{ __('Signatures') }}</x-ui.tab>
    </x-ui.tabs>

    <div x-show="boqTab === 'details'" x-cloak class="space-y-4">
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
    <div class="boq-stats-compact grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
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

    {{-- Prices of this BOQ for several locations, and comparing them. --}}
    <livewire:boqs.location-prices :boq="$boq" :key="'location-prices-'.$boq->id" />
    </div>

    <div x-show="boqTab === 'items'" x-cloak class="space-y-4">
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
                    <option value="unpriced">{{ __('No suggested price') }}</option>
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

            @if($canEdit && $itemStats['unpriced'] > 0)
                <button type="button" wire:click="scanUnpriced" wire:loading.attr="disabled" wire:target="scanUnpriced" @disabled($isProcessing) class="boq-btn-secondary"
                    title="{{ __('Scan prices for every item without a suggested price') }}">
                    <i class="fas fa-magnifying-glass-dollar" wire:loading.remove wire:target="scanUnpriced" aria-hidden="true"></i>
                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="scanUnpriced" aria-hidden="true"></i>
                    {{ trans_choice('Scan :count unpriced item|Scan all :count unpriced items', $itemStats['unpriced'], ['count' => \App\Support\Format::number($itemStats['unpriced'], 0)]) }}
                </button>
            @endif

            @if($itemSearch !== '' || $itemStatus !== 'all')
                <button type="button" wire:click="clearItemFilters" class="boq-btn-ghost">
                    <i class="fas fa-filter-circle-xmark" aria-hidden="true"></i>
                    {{ __('Clear') }}
                </button>
            @endif
        </div>

        <div class="boq-loading-bar" wire:loading.delay wire:target="itemSearch, itemStatus, itemsPerPage, clearItemFilters, gotoPage, nextPage, previousPage"></div>

        @if($items->count() > 0)
            {{-- Admins: select items and get their prices now. --}}
            @if($canEdit)
                <x-bulk-bar :count="count($selected)">
                    <button type="button" wire:click="priceSelected" wire:loading.attr="disabled" wire:target="priceSelected" @disabled($isProcessing) class="boq-btn-primary">
                        <i class="fas fa-magnifying-glass-dollar" wire:loading.remove wire:target="priceSelected" aria-hidden="true"></i>
                        <i class="fas fa-spinner fa-spin" wire:loading wire:target="priceSelected" aria-hidden="true"></i>
                        {{ __('Scan prices') }}
                    </button>
                    <button type="button" wire:click="bulkAcceptSuggested" wire:loading.attr="disabled" wire:target="bulkAcceptSuggested" class="boq-btn-secondary" title="{{ __('Use the suggested price as the reviewed price') }}">
                        <i class="fas fa-clipboard-check" aria-hidden="true"></i> {{ __('Review (use suggested)') }}
                    </button>
                    @if(Auth::user()->hasPermission('boq.approve'))
                        <button type="button" wire:click="bulkApprove" wire:loading.attr="disabled" wire:target="bulkApprove" wire:confirm="{{ __('Approve the selected items? Approved prices can no longer be changed.') }}" class="boq-btn-secondary">
                            <i class="fas fa-circle-check text-emerald-600" aria-hidden="true"></i> {{ __('Approve') }}
                        </button>
                    @endif
                    <button type="button" wire:click="openBulkReject" class="boq-btn-danger">
                        <i class="fas fa-circle-xmark" aria-hidden="true"></i> {{ __('Reject') }}
                    </button>
                </x-bulk-bar>
            @endif

            <x-ui.table>
                <thead>
                    <tr>
                        @if($canEdit)
                            <th class="boq-check-col"><x-select-all :ids="$items->pluck('id')" :selected="$selected" /></th>
                        @endif
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
                            @if($canEdit)
                                <td class="boq-check-col"><x-select-row :id="$item->id" /></td>
                            @endif
                            <td class="whitespace-nowrap"><span class="boq-code">{{ $item->item_code ?: '—' }}</span></td>

                            <td class="min-w-[13rem] max-w-[22rem]">
                                <span class="block text-slate-800">{{ $item->description }}</span>
                            </td>

                            <td class="whitespace-nowrap">{{ $item->unit ?: '—' }}</td>

                            <td class="is-numeric">{{ \App\Support\Format::number((float) $item->quantity, 2) }}</td>

                            <td class="is-numeric">{{ $rate($item->original_rate) }}</td>

                            <td class="is-numeric">
                                @if($item->ai_suggested_rate === null || (float) $item->ai_suggested_rate <= 0)
                                    @php
                                        $scanning = $isProcessing && in_array($item->pricing_status, ['pending', 'pricing'], true);
                                        $failed = $item->pricing_status === 'failed' || filled($item->pricing_error);
                                    @endphp
                                    <div class="flex flex-col items-end gap-1 whitespace-normal">
                                        @if($scanning)
                                            <span class="boq-badge boq-badge-info"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Scanning...') }}</span>
                                        @else
                                            <span class="boq-badge {{ $failed ? 'boq-badge-danger' : 'boq-badge-warning' }}">{{ $failed ? __('No price found') : __('No price yet') }}</span>
                                            @if($failed && $item->pricing_error)
                                                <span class="boq-table-subtitle max-w-[11rem] text-right" title="{{ $item->pricing_error }}">{{ \Illuminate\Support\Str::limit($item->pricing_error, 60) }}</span>
                                            @endif
                                            @if($canEdit && $item->status !== 'approved')
                                                <button type="button" wire:click="scanItem({{ $item->id }})" wire:loading.attr="disabled" wire:target="scanItem({{ $item->id }})" @disabled($isProcessing) class="boq-btn-secondary boq-btn-sm">
                                                    <i class="fas fa-magnifying-glass-dollar" wire:loading.remove wire:target="scanItem({{ $item->id }})" aria-hidden="true"></i>
                                                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="scanItem({{ $item->id }})" aria-hidden="true"></i>
                                                    {{ $failed ? __('Scan again') : __('Scan price') }}
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <div class="font-semibold text-brand-700">{{ $rate($item->ai_suggested_rate) }}</div>
                                @endif

                                @if($item->ai_suggested_rate !== null && (float) $item->ai_suggested_rate > 0)
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
                                <td colspan="{{ $canEdit ? 12 : 11 }}" class="!p-0">
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
                        @elseif($rejectingItemId === $item->id)
                            <tr wire:key="reject-item-{{ $item->id }}" class="bg-red-50/60 hover:bg-red-50/60">
                                <td colspan="{{ $canEdit ? 12 : 11 }}" class="!p-0">
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

    @if($reviewingItemId && $reviewModalItem)
        @php $reviewItem = $reviewModalItem; @endphp
            <x-ui.modal wire:key="review-item-{{ $reviewItem->id }}" id="review-item" :title="__('Review BOQ item')" :subtitle="__('Check the item and suggested rate before saving your review.')" icon="fa-scale-balanced" size="lg" close="closeEditor">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Item description') }}</p>
                            <h3 class="mt-1 text-base font-semibold text-slate-900">{{ $reviewItem->description }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Code: :code · Unit: :unit · Quantity: :quantity', ['code' => $reviewItem->item_code ?: '—', 'unit' => $reviewItem->unit ?: '—', 'quantity' => \App\Support\Format::number((float) $reviewItem->quantity, 2)]) }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field :label="__('Reviewed Rate')" for="manual-rate-{{ $reviewItem->id }}" error="manualRate" required>
                                <input placeholder="0.00" id="manual-rate-{{ $reviewItem->id }}" wire:model="manualRate" type="number" min="0" step="0.01" inputmode="decimal" class="boq-field @error('manualRate') has-error @enderror">
                            </x-ui.field>
                            <x-ui.field :label="__('Review Notes')" for="review-notes-{{ $reviewItem->id }}" error="reviewNotes">
                                <input id="review-notes-{{ $reviewItem->id }}" wire:model="reviewNotes" type="text" maxlength="2000" class="boq-field" placeholder="{{ __('Reason or supporting context') }}">
                            </x-ui.field>
                        </div>
                    </div>
                    <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4" aria-label="{{ __('Rate reference') }}">
                        <h4 class="text-sm font-semibold text-slate-900">{{ __('Rate reference') }}</h4>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Suggested') }}</dt><dd class="font-semibold">{{ $rate($reviewItem->ai_suggested_rate) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Original') }}</dt><dd class="font-semibold">{{ $rate($reviewItem->original_rate) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Location') }}</dt><dd class="text-right">{{ $reviewItem->location ?: $pricingLocation ?: '—' }}</dd></div>
                        </dl>
                        @if($reviewItem->ai_suggested_rate !== null)
                            <x-ui.button class="mt-4 w-full" variant="secondary" wire:click="reviewUsingSuggested({{ $reviewItem->id }})">{{ __('Use Suggested') }}</x-ui.button>
                        @endif
                    </aside>
                </div>
                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="closeEditor">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="fa-floppy-disk" wire:click="reviewItem({{ $reviewItem->id }})" loading="reviewItem">{{ __('Save Review') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
    @endif

    </div>
    <div x-show="boqTab === 'signatures'" x-cloak>
        <livewire:boqs.signatures :boq="$boq" wire:key="boq-signatures-{{ $boq->id }}" />
    </div>
    </div>

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

    @if($showBulkReject)
        <x-ui.modal wire:key="bulk-reject" id="bulk-reject" :title="__('Reject selected prices')" icon="fa-circle-xmark" size="sm" close="$set('showBulkReject', false)" submit="bulkReject">
            <p class="mb-3 text-sm text-slate-600">{{ trans_choice(':count item will be rejected. Approved items are kept.|:count items will be rejected. Approved items are kept.', count($selected), ['count' => count($selected)]) }}</p>
            <x-ui.field :label="__('Reason')" for="bulk-reject-reason" error="bulkRejectionReason" required>
                <textarea id="bulk-reject-reason" wire:model="bulkRejectionReason" rows="3" maxlength="2000" required class="boq-field boq-textarea @error('bulkRejectionReason') has-error @enderror" placeholder="{{ __('e.g. Rates too high for this location') }}"></textarea>
            </x-ui.field>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('showBulkReject', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="danger" icon="fa-circle-xmark" loading="bulkReject">{{ __('Reject') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if($showGenerateModal)
        <x-ui.modal wire:key="generate-boq" id="generate-boq" :title="__('Generate BOQ')" :subtitle="__('Prices are looked up for the project location.')" icon="fa-location-dot" size="sm" close="closeGenerate" submit="generateBoq">
            <x-ui.field :label="__('Project location')" for="generate-location" error="projectLocation" :hint="__('Town, district or market where the project will be built. It is saved to the project.')" required>
                <input id="generate-location" type="text" wire:model="projectLocation" list="generate-location-options" maxlength="255" required autofocus autocomplete="off" class="boq-field @error('projectLocation') has-error @enderror" placeholder="{{ __('e.g. Kampala, Wakiso') }}">
                <datalist id="generate-location-options">
                    @foreach($locationSuggestions as $place)
                        <option value="{{ $place }}"></option>
                    @endforeach
                </datalist>
            </x-ui.field>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeGenerate">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-wand-magic-sparkles" loading="generateBoq">{{ __('Generate BOQ') }}</x-ui.button>
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
