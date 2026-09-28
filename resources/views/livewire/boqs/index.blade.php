<div class="boq-page-stack">

    @if(session('status'))
        <div class="boq-flash mb-4"><i class="fas fa-circle-check"></i> {{ session('status') }}</div>
    @endif

    {{-- =====================================================
         HEADER
    ====================================================== --}}
    <div class="boq-page-header">

        <div>

            <h1 class="boq-page-title">
                <i class="fas fa-file-invoice-dollar"></i>
                {{ __('BOQs') }}
            </h1>

            <p class="boq-page-subtitle">
                {{ __('Bill of Quantities for your projects.') }}
            </p>

        </div>

        <a
            href="{{ url('/boqs/create') }}"
            class="boq-btn-primary"
        >
            <i class="fas fa-plus"></i>
            {{ __('New BOQ') }}
        </a>

    </div>


    {{-- =====================================================
         STATISTICS
         SAME DISPLAY AS PLANS & PRICING
    ====================================================== --}}
    <div class="boq-stats-grid">

        {{-- Total BOQs --}}
        <div class="boq-stat-card boq-stat-green">

            <div>

                <p class="boq-stat-label">
                    {{ __('Total BOQs') }}
                </p>

                <p class="boq-stat-value">
                    {{ $stats['total_boqs'] ?? 0 }}
                </p>

            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </span>

        </div>


        {{-- Uploaded --}}
        <div class="boq-stat-card boq-stat-blue">

            <div>

                <p class="boq-stat-label">
                    {{ __('Uploaded') }}
                </p>

                <p class="boq-stat-value">
                    {{ $stats['uploaded'] ?? 0 }}
                </p>

            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-cloud-arrow-up"></i>
            </span>

        </div>


        {{-- Under Review --}}
        <div class="boq-stat-card boq-stat-amber">

            <div>

                <p class="boq-stat-label">
                    {{ __('Under Review') }}
                </p>

                <p class="boq-stat-value">
                    {{ $stats['under_review'] ?? 0 }}
                </p>

            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-magnifying-glass-chart"></i>
            </span>

        </div>


        {{-- Approved --}}
        <div class="boq-stat-card boq-stat-purple">

            <div>

                <p class="boq-stat-label">
                    {{ __('Approved') }}
                </p>

                <p class="boq-stat-value">
                    {{ $stats['approved'] ?? 0 }}
                </p>

            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-circle-check"></i>
            </span>

        </div>

    </div>


    {{-- =====================================================
         SEARCH / ROWS / SORT
    ====================================================== --}}
    <div class="boq-panel">

        <div
            class="boq-index-filter-grid boq-index-filter-grid-boqs"
        >

            {{-- Search --}}
            <div>

                <label class="boq-field-label">
                    {{ __('Search BOQs') }}
                </label>

                <div class="boq-input-icon-wrap">

                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="{{ __('Search BOQ name, project or status...') }}"
                    >

                </div>

            </div>


            {{-- Rows --}}
            <div>

                <label class="boq-field-label">
                    {{ __('Rows') }}
                </label>

                <select
                    wire:model.live="perPage"
                    class="boq-field"
                >

                    @foreach($perPageOptions as $option)

                        <option value="{{ $option }}">
                            {{ $option }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{-- Sort --}}
            <div>

                <label class="boq-field-label">
                    {{ __('Sort By') }}
                </label>

                <div class="boq-sort-buttons">

                    @foreach([
                        'name' => [
                            'label' => 'Name',
                            'icon' => 'fa-font',
                        ],
                        'status' => [
                            'label' => 'Status',
                            'icon' => 'fa-circle-check',
                        ],
                        'currency' => [
                            'label' => 'Currency',
                            'icon' => 'fa-coins',
                        ],
                        'created_at' => [
                            'label' => 'Created',
                            'icon' => 'fa-calendar',
                        ],
                    ] as $field => $sortOption)

                        <button
                            type="button"
                            wire:click="sortBy('{{ $field }}')"
                            class="boq-sort-button {{ $sortBy === $field ? 'is-active' : '' }}"
                        >

                            <i class="fas {{ $sortOption['icon'] }}"></i>

                            {{ $sortOption['label'] }}

                            @if($sortBy === $field)

                                <i class="fas {{
                                    $sortDir === 'asc'
                                        ? 'fa-arrow-up'
                                        : 'fa-arrow-down'
                                }}"></i>

                            @endif

                        </button>

                    @endforeach

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         BOQ TABLE
    ====================================================== --}}
    <div class="boq-panel">

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected BOQs?') }}" class="boq-btn-danger"><i class="fas fa-trash"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>


        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>

                    <tr>

                        <th class="boq-check-col"><x-select-all :ids="$boqs->pluck('id')" :selected="$selected" /></th>

                        {{-- Name --}}
                        <th
                            wire:click="sortBy('name')"
                            class="boq-sortable-header"
                        >

                            <span>
                                Name

                                @if($sortBy === 'name')

                                    <i class="fas {{
                                        $sortDir === 'asc'
                                            ? 'fa-arrow-up'
                                            : 'fa-arrow-down'
                                    }}"></i>

                                @endif
                            </span>

                        </th>


                        {{-- Project --}}
                        <th>
                            {{ __('Project') }}
                        </th>


                        {{-- Status --}}
                        <th
                            wire:click="sortBy('status')"
                            class="boq-sortable-header"
                        >

                            <span>
                                Status

                                @if($sortBy === 'status')

                                    <i class="fas {{
                                        $sortDir === 'asc'
                                            ? 'fa-arrow-up'
                                            : 'fa-arrow-down'
                                    }}"></i>

                                @endif
                            </span>

                        </th>


                        {{-- Currency --}}
                        <th
                            wire:click="sortBy('currency')"
                            class="boq-sortable-header"
                        >

                            <span>
                                Currency

                                @if($sortBy === 'currency')

                                    <i class="fas {{
                                        $sortDir === 'asc'
                                            ? 'fa-arrow-up'
                                            : 'fa-arrow-down'
                                    }}"></i>

                                @endif
                            </span>

                        </th>


                        {{-- Version --}}
                        <th>
                            {{ __('Version') }}
                        </th>


                        {{-- Items --}}
                        <th>
                            {{ __('Items') }}
                        </th>


                        {{-- Created --}}
                        <th
                            wire:click="sortBy('created_at')"
                            class="boq-sortable-header"
                        >

                            <span>
                                Created

                                @if($sortBy === 'created_at')

                                    <i class="fas {{
                                        $sortDir === 'asc'
                                            ? 'fa-arrow-up'
                                            : 'fa-arrow-down'
                                    }}"></i>

                                @endif
                            </span>

                        </th>


                        {{-- Actions --}}
                        <th class="text-right">
                            {{ __('Actions') }}
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($boqs as $boq)

                        <tr wire:key="boq-{{ $boq->id }}">

                            <td class="boq-check-col">
                                @can('delete', $boq)
                                    <x-select-row :id="$boq->id" />
                                @endcan
                            </td>

                            {{-- BOQ --}}
                            <td>

                                <a
                                    href="{{ url('/boqs/'.$boq->id) }}"
                                    class="boq-table-link"
                                >
                                    {{ $boq->name }}
                                </a>

                            </td>


                            {{-- Project --}}
                            <td>

                                @if($boq->project)

                                    <span class="boq-cell-with-icon">

                                        <i class="fas fa-folder-open"></i>

                                        {{ $boq->project->name }}

                                    </span>

                                @else

                                    <span class="boq-table-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Status --}}
                            <td>

                                @php

                                    $statusClass = match($boq->status) {

                                        'approved'
                                            => 'boq-badge-success',

                                        'uploaded'
                                            => 'boq-badge-info',

                                        'under_review'
                                            => 'boq-badge-warning',

                                        'rejected'
                                            => 'boq-badge-danger',

                                        default
                                            => ''
                                    };

                                @endphp

                                <span class="boq-badge {{ $statusClass }}">

                                    @switch($boq->status)

                                        @case('approved')
                                            <i class="fas fa-circle-check"></i>
                                            @break

                                        @case('uploaded')
                                            <i class="fas fa-cloud-arrow-up"></i>
                                            @break

                                        @case('under_review')
                                            <i class="fas fa-magnifying-glass-chart"></i>
                                            @break

                                        @case('rejected')
                                            <i class="fas fa-circle-xmark"></i>
                                            @break

                                        @default
                                            <i class="fas fa-circle"></i>

                                    @endswitch

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $boq->status ?? 'unknown'
                                        )
                                    ) }}

                                </span>

                            </td>


                            {{-- Currency --}}
                            <td>

                                @if($boq->currency)

                                    <span class="boq-currency-badge">
                                        {{ $boq->currency }}
                                    </span>

                                @else

                                    <span class="boq-table-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Version --}}
                            <td>

                                <span class="boq-version-badge">

                                    <i class="fas fa-code-branch"></i>

                                    v{{ $boq->version ?? 1 }}

                                </span>

                            </td>


                            {{-- Items --}}
                            <td>

                                <span class="boq-item-count">

                                    <i class="fas fa-list-ul"></i>

                                    {{ $boq->items_count
                                        ?? $boq->items->count()
                                    }}

                                </span>

                                <x-boq-totals compact :totals="$totals[$boq->id] ?? []" :currency="$boq->currency" />

                            </td>


                            {{-- Created --}}
                            <td>

                                @if($boq->created_at)

                                    <span class="boq-cell-with-icon">

                                        <i class="fas fa-calendar-day"></i>

                                        {{ $boq->created_at->format('d M Y') }}

                                    </span>

                                @else

                                    <span class="boq-table-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Actions --}}
                            <td class="text-right">

                                <div class="boq-table-actions">

                                    {{-- View --}}
                                    <a
                                        href="{{ url('/boqs/'.$boq->id) }}"
                                        class="boq-icon-btn"
                                        title="{{ __('View BOQ') }}"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @can('update', $boq)
                                        <button type="button" wire:click="editBoq({{ $boq->id }})" class="boq-icon-btn" title="{{ __('Edit BOQ') }}" aria-label="{{ __('Edit BOQ') }}">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    @endcan

                                    @can('delete', $boq)
                                        <button type="button" wire:click="confirmDelete({{ $boq->id }})" class="boq-icon-btn boq-icon-danger" title="{{ __('Delete BOQ') }}" aria-label="{{ __('Delete BOQ') }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endcan

                                </div>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="9"
                                class="boq-empty-table"
                            >

                                <i class="fas fa-file-invoice-dollar"></i>

                                <span>
                                    {{ __('No BOQs found.') }}
                                </span>

                                <div class="boq-empty-action">

                                    <a
                                        href="{{ url('/boqs/create') }}"
                                        class="boq-btn-primary"
                                    >
                                        <i class="fas fa-plus"></i>
                                        {{ __('Upload your first BOQ') }}
                                    </a>

                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- =================================================
             PAGINATION
        ================================================== --}}
        @if($boqs->hasPages())

            <div class="boq-pagination">

                {{ $boqs->links() }}

            </div>

        @endif

    </div>


    @if($editingBoqId)
        <div class="boq-modal-backdrop" wire:key="boq-edit-modal" role="dialog" aria-modal="true" aria-labelledby="boq-edit-title">
            <form wire:submit="saveBoq" class="boq-modal boq-modal-sm">
                <div class="boq-modal-head">
                    <h2 id="boq-edit-title"><i class="fas fa-pen"></i> {{ __('Edit BOQ') }}</h2>
                    <button type="button" wire:click="closeEdit" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="boq-modal-body space-y-4">
                    <div>
                        <label for="boq-edit-name" class="boq-field-label">{{ __('BOQ Name') }} *</label>
                        <input id="boq-edit-name" type="text" wire:model="editName" class="boq-field" maxlength="255" placeholder="{{ __('e.g. Main building - Phase 1') }}">
                        @error('editName') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="boq-edit-project" class="boq-field-label">{{ __('Project') }} *</label>
                        <select id="boq-edit-project" wire:model="editProjectId" class="boq-field">
                            @foreach($projects as $projectOption)
                                <option value="{{ $projectOption->id }}">{{ $projectOption->name }}{{ $projectOption->code ? ' ('.$projectOption->code.')' : '' }}</option>
                            @endforeach
                        </select>
                        @error('editProjectId') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <p class="boq-field-help">{{ __('To change item rates, open the BOQ and review its items.') }}</p>
                </div>

                <div class="boq-modal-foot">
                    <button type="button" wire:click="closeEdit" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="boq-btn-primary"><i class="fas fa-floppy-disk"></i> {{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if($deletingBoqId)
        <div class="boq-modal-backdrop" wire:key="boq-delete-modal" role="dialog" aria-modal="true" aria-labelledby="boq-delete-title">
            <div class="boq-modal boq-modal-sm">
                <div class="boq-modal-head">
                    <h2 id="boq-delete-title"><i class="fas fa-trash"></i> {{ __('Delete BOQ?') }}</h2>
                    <button type="button" wire:click="cancelDelete" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="boq-modal-body">
                    <p class="boq-modal-message">{{ __('The BOQ and its items will be removed from your list. An administrator can restore it if needed.') }}</p>
                </div>
                <div class="boq-modal-foot">
                    <button type="button" wire:click="cancelDelete" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="deleteBoq" class="boq-btn-danger"><i class="fas fa-trash"></i> {{ __('Delete') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
