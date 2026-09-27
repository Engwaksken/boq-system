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


    {{-- ============================================================
         STATISTICS
    ============================================================ --}}
    @if(session('message'))
        <div class="boq-flash"><i class="fas fa-circle-check"></i> {{ session('message') }}</div>
    @endif

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

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkActivate" wire:confirm="Activate the selected pending, overdue, failed or expired subscriptions?" class="boq-btn-secondary"><i class="fas fa-circle-check"></i> Activate</button>
            <button type="button" wire:click="bulkCancel" wire:confirm="Cancel the selected subscriptions and revoke their access?" class="boq-btn-secondary"><i class="fas fa-ban"></i> Cancel</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="Delete the selected subscriptions? Active ones are skipped." class="boq-btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </x-bulk-bar>

        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>
                    <tr>

                        <th class="boq-check-col"><x-select-all :ids="$subscriptions->pluck('id')" :selected="$selected" /></th>

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

                            <td class="boq-check-col"><x-select-row :id="$sub->id" /></td>

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
                                    {{ $sub->plan?->currency ?? \App\Support\Regional::currency() }}

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


                            <td>
                                <div class="boq-table-actions justify-end">
                                    @if(in_array($sub->status, ['pending', 'past_due', 'failed', 'expired'], true))
                                        <button type="button" wire:click="activate({{ $sub->id }})" wire:confirm="Activate this subscription now? Use this for payments confirmed outside the system." class="boq-icon-btn boq-icon-success" title="Activate" aria-label="Activate">
                                            <i class="fas fa-circle-check"></i>
                                        </button>
                                    @endif

                                    @if($sub->end_date && ! in_array($sub->status, ['cancelled'], true))
                                        <button type="button" wire:click="openExtend({{ $sub->id }})" class="boq-icon-btn" title="Extend" aria-label="Extend">
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                    @endif

                                    @unless(in_array($sub->status, ['cancelled', 'expired'], true))
                                        <button type="button" wire:click="cancel({{ $sub->id }})" wire:confirm="Cancel this subscription and revoke its access?" class="boq-icon-btn boq-icon-danger" title="Cancel" aria-label="Cancel">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    @endunless
                                </div>
                            </td>

                        </tr>


                    @empty

                        <tr>
                            <td
                                colspan="8"
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

    @if($extendingId)
        <div class="boq-modal-backdrop" wire:key="extend-modal" role="dialog" aria-modal="true" aria-labelledby="extend-title">
            <form wire:submit="applyExtension" class="boq-modal boq-modal-sm">
                <div class="boq-modal-head">
                    <h2 id="extend-title"><i class="fas fa-calendar-plus"></i> Extend Subscription</h2>
                    <button type="button" wire:click="closeExtend" class="boq-modal-close" aria-label="Close"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="boq-modal-body">
                    <label for="extend-days" class="boq-field-label">Add days</label>
                    <input placeholder="e.g. 30" id="extend-days" type="number" min="1" max="3650" wire:model="extendDays" class="boq-field">
                    <p class="boq-field-help">Counted from the current end date, or from today if it has already expired.</p>
                    @error('extendDays') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div class="boq-modal-foot">
                    <button type="button" wire:click="closeExtend" class="boq-btn-secondary">Cancel</button>
                    <button type="submit" class="boq-btn-primary"><i class="fas fa-calendar-check"></i> Extend</button>
                </div>
            </form>
        </div>
    @endif

</div>