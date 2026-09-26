<div class="boq-page-stack">

    {{-- ============================================================
         HEADER
    ============================================================ --}}
    <div class="boq-page-header">

        <div>
            <h1 class="boq-page-title">
                <i class="fas fa-receipt"></i>
                Subscriptions Management
            </h1>

            <p class="boq-page-subtitle">
                Manage user subscriptions, statuses and subscription revenue.
            </p>
        </div>

    </div>


    {{-- ============================================================
         ADMIN TABS
    ============================================================ --}}
    @include('livewire.admin._tabs')


    {{-- ============================================================
         STATISTICS
    ============================================================ --}}
    <div class="boq-admin-stats-grid">

        <div class="boq-stat-card boq-stat-blue">
            <div>
                <p class="boq-stat-label">
                    Total Subscriptions
                </p>

                <p class="boq-stat-value">
                    {{ $stats['total'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-receipt"></i>
            </span>
        </div>


        <div class="boq-stat-card boq-stat-green">
            <div>
                <p class="boq-stat-label">
                    Active
                </p>

                <p class="boq-stat-value">
                    {{ $stats['active'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-circle-check"></i>
            </span>
        </div>


        <div class="boq-stat-card boq-stat-red">
            <div>
                <p class="boq-stat-label">
                    Expired
                </p>

                <p class="boq-stat-value">
                    {{ $stats['expired'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-calendar-xmark"></i>
            </span>
        </div>


        <div class="boq-stat-card boq-stat-amber">
            <div>
                <p class="boq-stat-label">
                    Cancelled
                </p>

                <p class="boq-stat-value">
                    {{ $stats['cancelled'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-ban"></i>
            </span>
        </div>


        <div class="boq-stat-card boq-stat-purple">
            <div>
                <p class="boq-stat-label">
                    Monthly Revenue
                </p>

                <p
                    class="boq-stat-value"
                    style="font-size: 1.1rem;"
                >
                    UGX
                    {{ number_format(
                        (float) ($stats['revenue'] ?? 0)
                    ) }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-coins"></i>
            </span>
        </div>

    </div>


    {{-- ============================================================
         FILTERS
    ============================================================ --}}
    <div class="boq-panel">

        <div class="boq-admin-filter-row">

            <div class="boq-admin-filter-search">

                <label class="boq-field-label">
                    Search
                </label>

                <div class="boq-input-icon-wrap">

                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="Search user, email, plan or status..."
                    >

                </div>

            </div>


            <div class="boq-admin-filter-small">

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

        </div>

    </div>


    {{-- ============================================================
         TABLE
    ============================================================ --}}
    <div class="boq-panel">

        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>
                    <tr>

                        <th
                            wire:click="sortBy('user.name')"
                            class="boq-sortable-header"
                        >
                            User

                            @if($sortBy === 'user.name')
                                <i class="fas {{
                                    $sortDir === 'asc'
                                        ? 'fa-arrow-up'
                                        : 'fa-arrow-down'
                                }}"></i>
                            @endif
                        </th>


                        <th
                            wire:click="sortBy('plan.name')"
                            class="boq-sortable-header"
                        >
                            Plan

                            @if($sortBy === 'plan.name')
                                <i class="fas {{
                                    $sortDir === 'asc'
                                        ? 'fa-arrow-up'
                                        : 'fa-arrow-down'
                                }}"></i>
                            @endif
                        </th>


                        <th
                            wire:click="sortBy('status')"
                            class="boq-sortable-header"
                        >
                            Status

                            @if($sortBy === 'status')
                                <i class="fas {{
                                    $sortDir === 'asc'
                                        ? 'fa-arrow-up'
                                        : 'fa-arrow-down'
                                }}"></i>
                            @endif
                        </th>


                        <th class="text-right">
                            Amount
                        </th>


                        <th
                            wire:click="sortBy('start_date')"
                            class="boq-sortable-header"
                        >
                            Start Date
                        </th>


                        <th
                            wire:click="sortBy('end_date')"
                            class="boq-sortable-header"
                        >
                            End Date
                        </th>


                        <th class="text-right">
                            Actions
                        </th>

                    </tr>
                </thead>


                <tbody>

                    @forelse($subscriptions as $sub)

                        @php
                            $statusClass = match($sub->status) {
                                'active' =>
                                    'boq-badge-success',

                                'pending' =>
                                    'boq-badge-warning',

                                'expired',
                                'cancelled' =>
                                    'boq-badge-danger',

                                default => '',
                            };
                        @endphp

                        <tr wire:key="admin-subscription-{{ $sub->id }}">

                            <td>
                                <div class="boq-table-title">
                                    {{ $sub->user?->name ?? 'Deleted user' }}
                                </div>

                                <div class="boq-table-subtitle">
                                    {{ $sub->user?->email ?? '—' }}
                                </div>
                            </td>


                            <td>
                                <span class="boq-cell-with-icon">
                                    <i class="fas fa-layer-group"></i>

                                    {{ $sub->plan?->name ?? 'Plan unavailable' }}
                                </span>
                            </td>


                            <td>
                                <span class="boq-badge {{ $statusClass }}">
                                    {{ ucfirst(
                                        $sub->status ?? 'unknown'
                                    ) }}
                                </span>
                            </td>


                            <td class="text-right">
                                <strong>
                                    {{ $sub->plan?->currency ?? 'UGX' }}

                                    {{ number_format(
                                        (float) (
                                            $sub->plan?->price ?? 0
                                        ),
                                        0
                                    ) }}
                                </strong>
                            </td>


                            <td>
                                <span class="boq-cell-with-icon">
                                    <i class="fas fa-calendar-day"></i>

                                    {{ $sub->start_date?->format('d M Y')
                                        ?? 'Not started'
                                    }}
                                </span>
                            </td>


                            <td>
                                <span class="boq-cell-with-icon">
                                    <i class="fas fa-calendar-check"></i>

                                    {{ $sub->end_date?->format('d M Y')
                                        ?? 'Ongoing'
                                    }}
                                </span>
                            </td>


                            <td class="text-right">
                                <span class="boq-no-action">
                                    —
                                </span>
                            </td>

                        </tr>


                    @empty

                        <tr>
                            <td
                                colspan="7"
                                class="boq-empty-table"
                            >
                                <i class="fas fa-receipt"></i>

                                <span>
                                    No subscriptions found.
                                </span>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        @if($subscriptions->hasPages())
            <div class="boq-pagination">
                {{ $subscriptions->links() }}
            </div>
        @endif

    </div>

</div>