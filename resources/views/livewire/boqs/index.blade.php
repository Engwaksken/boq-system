<div class="boq-page-stack">

    {{-- =====================================================
         HEADER
    ====================================================== --}}
    <div class="boq-page-header">

        <div>

            <h1 class="boq-page-title">
                <i class="fas fa-file-invoice-dollar"></i>
                BOQs
            </h1>

            <p class="boq-page-subtitle">
                Bill of Quantities for your projects.
            </p>

        </div>

        <a
            href="{{ url('/boqs/create') }}"
            class="boq-btn-primary"
        >
            <i class="fas fa-plus"></i>
            New BOQ
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
                    Total BOQs
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
                    Uploaded
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
                    Under Review
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
                    Approved
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
                    Search BOQs
                </label>

                <div class="boq-input-icon-wrap">

                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="Search BOQ name, project or status..."
                    >

                </div>

            </div>


            {{-- Rows --}}
            <div>

                <label class="boq-field-label">
                    Rows
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
                    Sort By
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

        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>

                    <tr>

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
                            Project
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
                            Version
                        </th>


                        {{-- Items --}}
                        <th>
                            Items
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
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($boqs as $boq)

                        <tr wire:key="boq-{{ $boq->id }}">

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
                                        title="View BOQ"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>

                                </div>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="8"
                                class="boq-empty-table"
                            >

                                <i class="fas fa-file-invoice-dollar"></i>

                                <span>
                                    No BOQs found.
                                </span>

                                <div class="boq-empty-action">

                                    <a
                                        href="{{ url('/boqs/create') }}"
                                        class="boq-btn-primary"
                                    >
                                        <i class="fas fa-plus"></i>
                                        Upload your first BOQ
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

</div>
