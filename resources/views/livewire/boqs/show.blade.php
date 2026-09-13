<div @if($this->isProcessing) wire:poll.2s="refreshProcessingStatus" @endif>
    @php
        $canEdit = Auth::user()->hasPermission('boq.edit');
        $canApprove = Auth::user()->hasPermission('boq.approve');
        $reviewedCount = $boq->items->where('status', 'reviewed')->count();
        $processingBatch = $this->processingBatch;
        $isProcessing = $this->isProcessing;
    @endphp
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ $boq->name }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $boq->status === 'approved' || $boq->status === 'analysed' ? 'bg-green-100 text-green-800' : ($boq->status === 'under_review' ? 'bg-purple-100 text-purple-800' : ($boq->status === 'uploaded' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')) }}">
                {{ $boq->status }}
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ url('/boqs') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back
            </a>
            @if(isset($pdfUrl) && $pdfUrl)
                <a href="{{ $pdfUrl }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Download PDF
                </a>
            @endif
            @if($canApprove && $reviewedCount > 0)
                <button wire:click="approveAllReviewed" wire:loading.attr="disabled" wire:target="approveAllReviewed" type="button" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition">
                    Approve reviewed ({{ $reviewedCount }})
                </button>
            @endif
            @if($canEdit)
                <button wire:click="generateBoq" wire:loading.attr="disabled" wire:target="generateBoq" type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition">
                    <svg wire:loading.remove wire:target="generateBoq" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m9-9H3" /></svg>
                    <svg wire:loading wire:target="generateBoq" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    <span wire:loading.remove wire:target="generateBoq">Generate BOQ</span>
                    <span wire:loading wire:target="generateBoq">Generating...</span>
                </button>
            @endif
        </div>
    </div>

    @error('boq')
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror
    @error('approval')
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror
    @error('review')
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @if($isProcessing && $processingBatch)
        <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-indigo-900">Processing BOQ — {{ ucfirst($processingBatch->current_stage ?? $processingBatch->status) }}</p>
                    <p class="mt-1 text-xs text-indigo-700">{{ $processingBatch->message ?: 'Working on your BOQ...' }}</p>
                </div>
                <span class="text-xs font-medium text-indigo-700">{{ $processingBatch->processed_items }}/{{ $processingBatch->total_items ?: '?' }}</span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-indigo-100">
                @php $pct = $processingBatch->total_items > 0 ? min(100, (int) round($processingBatch->processed_items / max(1, $processingBatch->total_items) * 100)) : 25; @endphp
                <div class="h-2 bg-indigo-600 transition-all" style="width: {{ $pct }}%"></div>
            </div>
            @if($processingBatch->location)
                <p class="mt-2 text-xs text-indigo-600">Location: {{ $processingBatch->location }}</p>
            @endif
        </div>
    @elseif($processingBatch && $processingBatch->status === 'failed')
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            Processing failed: {{ $processingBatch->error_message ?: 'Unknown error.' }}
        </div>
    @elseif($processingBatch && $processingBatch->status === 'completed_with_errors')
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Completed with {{ $processingBatch->failed_items }} error(s): {{ $processingBatch->message }}
        </div>
    @elseif($processingBatch && $processingBatch->status === 'completed')
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            BOQ processed: {{ $processingBatch->message }} (Location: {{ $processingBatch->location }})
        </div>
    @elseif($generationSummary)
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            BOQ generated using current prices for <strong>{{ $generationSummary['location'] }}</strong>.
            {{ $generationSummary['matched'] }} item(s) matched and {{ $generationSummary['unmatched'] }} require review.
        </div>
    @endif

    {{-- BOQ Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500">Project</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->project?->name ?? '?' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Currency</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->currency }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Version</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->version }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Source Type</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->source_type ?? '?' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Pricing Location</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->project?->location ?: $boq->project?->district ?: $boq->project?->country ?: 'Not set' }}</dd>
            </div>
            @if($boq->description)
                <div class="md:col-span-4">
                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $boq->description }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Items Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex flex-col gap-1 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h2 class="text-lg font-semibold text-gray-900">Price review ({{ $boq->items->count() }} items)</h2>
            <p class="text-xs text-gray-500">Suggested rates require review before approval.</p>
        </div>

        @if($boq->items->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item Code</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Original</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Suggested</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Reviewed</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Approved</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($boq->items as $item)
                            <tr wire:key="boq-item-{{ $item->id }}" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $item->item_code }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">{{ $item->description }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $item->unit }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) $item->quantity, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ $item->original_rate !== null ? number_format((float) $item->original_rate, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="text-sm text-indigo-700">{{ $item->ai_suggested_rate !== null ? number_format((float) $item->ai_suggested_rate, 2) : '-' }}</div>
                                    @if($item->ai_suggested_rate !== null)
                                        <div class="mt-0.5 whitespace-nowrap text-[11px] text-gray-400">{{ $item->ai_confidence !== null ? number_format((float) $item->ai_confidence, 0).'%' : 'No confidence' }} · {{ $item->location ?: 'No location' }}</div>
                                        @if($item->match_type)
                                            <div class="mt-0.5 text-[11px] font-medium {{ $item->match_type === 'manual' ? 'text-violet-600' : 'text-gray-400' }}">
                                                {{ ucfirst($item->match_type) }} match
                                                @if($item->matchedBy) by {{ $item->matchedBy->name }} @endif
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-blue-700">{{ $item->reviewed_rate !== null ? number_format((float) $item->reviewed_rate, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-700">{{ $item->approved_rate !== null ? number_format((float) $item->approved_rate, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) $item->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm min-w-40">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item->status === 'approved' ? 'bg-green-100 text-green-800' : ($item->status === 'reviewed' ? 'bg-blue-100 text-blue-800' : ($item->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800')) }}">
                                        {{ $item->status }}
                                    </span>
                                    @if($item->status === 'approved' && $item->approvedBy)
                                        <div class="mt-1 text-xs text-gray-500">Approved by {{ $item->approvedBy->name }}<br>{{ $item->approved_at?->format('M j, Y H:i') }}</div>
                                    @elseif($item->status === 'reviewed' && $item->reviewedBy)
                                        <div class="mt-1 text-xs text-gray-500">Reviewed by {{ $item->reviewedBy->name }}<br>{{ $item->reviewed_at?->format('M j, Y H:i') }}</div>
                                    @elseif($item->status === 'rejected')
                                        <div class="mt-1 max-w-52 text-xs text-red-600">{{ $item->rejection_reason }}</div>
                                        @if($item->rejectedBy)
                                            <div class="mt-0.5 text-[11px] text-gray-500">{{ $item->rejectedBy->name }} · {{ $item->rejected_at?->format('M j, Y H:i') }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($canEdit && $item->status !== 'approved')
                                        <button wire:click="startMatching({{ $item->id }})" type="button" class="text-sm font-medium text-violet-600 hover:text-violet-800">{{ $item->hardware_price_id ? 'Change match' : 'Match' }}</button>
                                        <button wire:click="startReview({{ $item->id }})" type="button" class="ml-3 text-sm font-medium text-indigo-600 hover:text-indigo-800">Review</button>
                                        <button wire:click="startReject({{ $item->id }})" type="button" class="ml-3 text-sm font-medium text-red-600 hover:text-red-800">Reject</button>
                                    @endif
                                    @if($canApprove && $item->status === 'reviewed')
                                        <button wire:click="approveItem({{ $item->id }})" wire:loading.attr="disabled" wire:target="approveItem({{ $item->id }})" type="button" class="ml-3 text-sm font-semibold text-emerald-600 hover:text-emerald-800 disabled:opacity-50">Approve</button>
                                    @endif
                                </td>
                            </tr>
                            @if($matchingItemId === $item->id)
                                <tr wire:key="match-item-{{ $item->id }}" class="bg-violet-50/60">
                                    <td colspan="11" class="px-4 py-5 sm:px-6">
                                        <div class="mx-auto max-w-6xl">
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <h3 class="font-semibold text-gray-900">Match a current price</h3>
                                                    <p class="mt-1 text-sm text-gray-600">{{ $item->description }} <span class="text-gray-400">·</span> {{ $item->unit ?: 'No unit' }}</p>
                                                </div>
                                                <button wire:click="closeEditor" type="button" class="self-start text-sm font-medium text-gray-500 hover:text-gray-900">Close</button>
                                            </div>

                                            @if($automaticCandidates)
                                                <div class="mt-5">
                                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-700">Automatic candidates</h4>
                                                    <div class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                                        @foreach($automaticCandidates as $candidate)
                                                            <div wire:key="automatic-candidate-{{ $candidate['id'] }}" class="rounded-xl border border-violet-200 bg-white p-4 shadow-sm">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div class="min-w-0">
                                                                        <p class="font-semibold text-gray-900">{{ $candidate['item_name'] }}</p>
                                                                        <p class="mt-1 text-xs text-gray-500">{{ collect([$candidate['brand'], $candidate['specification'], $candidate['category']])->filter()->join(' · ') }}</p>
                                                                    </div>
                                                                    <span class="shrink-0 rounded-full bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-700">{{ $candidate['similarity'] }}%</span>
                                                                </div>
                                                                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-gray-600">
                                                                    <div><dt class="inline font-medium">Unit:</dt> <dd class="inline">{{ $candidate['unit'] }}</dd></div>
                                                                    <div><dt class="inline font-medium">Supplier:</dt> <dd class="inline">{{ $candidate['supplier'] }}</dd></div>
                                                                    <div><dt class="inline font-medium">Location:</dt> <dd class="inline">{{ $candidate['location'] ?: 'Not specified' }}</dd></div>
                                                                    <div><dt class="inline font-medium">Fetched:</dt> <dd class="inline">{{ $candidate['fetched_at'] ?: 'Unknown' }}</dd></div>
                                                                </dl>
                                                                <div class="mt-4 flex items-center justify-between gap-3">
                                                                    <p class="text-base font-bold text-gray-900">{{ $candidate['currency'] }} {{ number_format((float) $candidate['price'], 2) }}</p>
                                                                    <button wire:click="selectHardwarePrice({{ $item->id }}, {{ $candidate['id'] }})" wire:loading.attr="disabled" type="button" class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700 disabled:opacity-50">Select</button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="mt-5 border-t border-violet-200 pt-5">
                                                <label for="price-search-{{ $item->id }}" class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Search active prices</label>
                                                <input id="price-search-{{ $item->id }}" wire:model.live.debounce.350ms="matchSearch" type="search" maxlength="100" class="mt-2 block w-full rounded-lg border-gray-300 text-sm focus:border-violet-500 focus:ring-violet-500" placeholder="Search item, brand, specification, category or supplier">
                                                <p class="mt-1 text-xs text-gray-500">Prices near {{ $boq->project?->location ?: $boq->project?->district ?: $boq->project?->country ?: 'the project' }} appear first. You can deliberately select another location shown below.</p>
                                            </div>

                                            <div wire:loading.class="opacity-50" wire:target="matchSearch" class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                                @forelse($matchResults as $candidate)
                                                    @php
                                                        $projectLocation = strtolower(trim((string) ($boq->project?->location ?: $boq->project?->district ?: $boq->project?->country)));
                                                        $candidateLocation = strtolower(trim((string) $candidate['location']));
                                                        $nearProject = $projectLocation !== '' && $candidateLocation !== '' && ($projectLocation === $candidateLocation || str_contains($projectLocation, $candidateLocation) || str_contains($candidateLocation, $projectLocation));
                                                    @endphp
                                                    <div wire:key="search-candidate-{{ $candidate['id'] }}" class="rounded-xl border {{ $nearProject ? 'border-emerald-200' : 'border-amber-200' }} bg-white p-4">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div>
                                                                <p class="font-semibold text-gray-900">{{ $candidate['item_name'] }}</p>
                                                                <p class="mt-1 text-xs text-gray-500">{{ collect([$candidate['brand'], $candidate['specification'], $candidate['category']])->filter()->join(' · ') }}</p>
                                                            </div>
                                                            <span class="shrink-0 rounded-full px-2 py-1 text-[11px] font-semibold {{ $nearProject ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ $nearProject ? 'Project location' : ($candidate['location'] ?: 'Location not set') }}</span>
                                                        </div>
                                                        <p class="mt-3 text-xs text-gray-600">{{ $candidate['unit'] }} · {{ $candidate['supplier'] }} · {{ $candidate['location'] ?: 'No location' }} · fetched {{ $candidate['fetched_at'] ?: 'unknown' }}</p>
                                                        <div class="mt-4 flex items-center justify-between gap-3">
                                                            <p class="text-base font-bold text-gray-900">{{ $candidate['currency'] }} {{ number_format((float) $candidate['price'], 2) }}</p>
                                                            <button wire:click="selectHardwarePrice({{ $item->id }}, {{ $candidate['id'] }})" wire:loading.attr="disabled" type="button" class="rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-700 disabled:opacity-50">Select price</button>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 md:col-span-2 xl:col-span-3">
                                                        No active {{ $item->currency }} prices match this search. Try an item name, brand, category or supplier.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @elseif($reviewingItemId === $item->id)
                                <tr wire:key="review-item-{{ $item->id }}" class="bg-indigo-50/60">
                                    <td colspan="11" class="px-4 py-4">
                                        <div class="ml-auto grid max-w-3xl gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] sm:items-start">
                                            <div>
                                                <label for="manual-rate-{{ $item->id }}" class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Reviewed rate</label>
                                                <input id="manual-rate-{{ $item->id }}" wire:model="manualRate" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @error('manualRate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label for="review-notes-{{ $item->id }}" class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Review notes (optional)</label>
                                                <input id="review-notes-{{ $item->id }}" wire:model="reviewNotes" type="text" maxlength="2000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Reason or supporting context">
                                                @error('reviewNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="flex flex-wrap gap-2 sm:pt-5">
                                                @if($item->ai_suggested_rate !== null)
                                                    <button wire:click="reviewUsingSuggested({{ $item->id }})" type="button" class="rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Use suggested</button>
                                                @endif
                                                <button wire:click="reviewItem({{ $item->id }})" type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Save review</button>
                                                <button wire:click="closeEditor" type="button" class="px-2 py-2 text-xs font-medium text-gray-600 hover:text-gray-900">Cancel</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @elseif($rejectingItemId === $item->id)
                                <tr wire:key="reject-item-{{ $item->id }}" class="bg-red-50/60">
                                    <td colspan="11" class="px-4 py-4">
                                        <div class="ml-auto flex max-w-3xl flex-col gap-3 sm:flex-row sm:items-start">
                                            <div class="flex-1">
                                                <label for="rejection-reason-{{ $item->id }}" class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Rejection reason</label>
                                                <textarea id="rejection-reason-{{ $item->id }}" wire:model="rejectionReason" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500" placeholder="Explain why this item is rejected"></textarea>
                                                @error('rejectionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="flex gap-2 sm:pt-5">
                                                <button wire:click="rejectItem({{ $item->id }})" type="button" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">Reject item</button>
                                                <button wire:click="closeEditor" type="button" class="px-2 py-2 text-xs font-medium text-gray-600 hover:text-gray-900">Cancel</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No items in this BOQ yet.</p>
            </div>
        @endif
    </div>
</div>
