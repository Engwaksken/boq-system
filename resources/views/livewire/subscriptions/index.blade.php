<div class="boq-subscriptions-page">

    {{-- =====================================================
         HEADER
    ====================================================== --}}
    <div class="boq-page-header">

        <div>

            <h1 class="boq-page-title">
                <i class="fas fa-credit-card"></i>
                Subscriptions
            </h1>

            <p class="boq-page-subtitle">
                Manage your subscription history and choose an available plan.
            </p>

        </div>

        @if($subscription)

            <div class="boq-current-subscription">

                <div class="boq-current-subscription-label">
                    Current Subscription
                </div>

                <div class="boq-current-subscription-name">
                    {{ $subscription->plan?->name ?? 'Plan' }}
                </div>

            </div>

        @endif

    </div>


    {{-- =====================================================
         FLASH
    ====================================================== --}}
    @if(session('message'))

        <div class="boq-flash">

            <i class="fas fa-circle-check"></i>

            {{ session('message') }}

        </div>

    @endif


    {{-- =====================================================
         STATISTICS
    ====================================================== --}}
    <div class="boq-stats-grid">

        <div class="boq-stat-card boq-stat-green">

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


        <div class="boq-stat-card boq-stat-blue">

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


        <div class="boq-stat-card boq-stat-amber">

            <div>
                <p class="boq-stat-label">
                    Pending
                </p>

                <p class="boq-stat-value">
                    {{ $stats['pending'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-clock"></i>
            </span>

        </div>


        <div class="boq-stat-card boq-stat-purple">

            <div>
                <p class="boq-stat-label">
                    Available Plans
                </p>

                <p class="boq-stat-value">
                    {{ $stats['plans'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-layer-group"></i>
            </span>

        </div>

    </div>


    {{-- =====================================================
         MAIN PANEL
    ====================================================== --}}
    <div class="boq-panel boq-subscription-panel">

        {{-- =================================================
             TABS
        ================================================== --}}
        <div class="boq-tabs">

            <button
                type="button"
                wire:click.prevent="showSubscriptions"
                wire:loading.attr="disabled"
                wire:target="showSubscriptions,showPlans"
                class="boq-tab {{ $activeTab === 'subscriptions' ? 'is-active' : '' }}"
            >
                <i class="fas fa-credit-card"></i>
                Subscriptions
            </button>


            <button
                type="button"
                wire:click.prevent="showPlans"
                wire:loading.attr="disabled"
                wire:target="showSubscriptions,showPlans"
                class="boq-tab {{ $activeTab === 'plans' ? 'is-active' : '' }}"
            >
                <i class="fas fa-layer-group"></i>
                Available Plans
            </button>

        </div>


        {{-- =================================================
             LOADING
        ================================================== --}}
        <div
            wire:loading.flex
            wire:target="showSubscriptions,showPlans"
            class="boq-tab-loading"
        >
            <i class="fas fa-spinner fa-spin"></i>
            Loading...
        </div>


        {{-- =================================================
             SUBSCRIPTIONS TAB
        ================================================== --}}
        @if($activeTab === 'subscriptions')

            <div wire:key="subscriptions-tab">

                {{-- =========================================
                     FILTERS
                ========================================== --}}
                <div class="boq-filter-section">

                    <div class="boq-subscription-filter-grid">

                        {{-- Search --}}
                        <div>

                            <label class="boq-field-label">
                                Search
                            </label>

                            <div class="boq-input-icon-wrap">

                                <i class="fas fa-search boq-input-icon"></i>

                                <input
                                    type="search"
                                    wire:model.live.debounce.300ms="search"
                                    class="boq-field boq-field-with-icon"
                                    placeholder="Search plan, status or payment..."
                                >

                            </div>

                        </div>


                        {{-- Status --}}
                        <div>

                            <label class="boq-field-label">
                                Status
                            </label>

                            <select
                                wire:model.live="statusFilter"
                                class="boq-field"
                            >

                                <option value="all">
                                    All statuses
                                </option>

                                @foreach([
                                    'pending',
                                    'trial',
                                    'active',
                                    'past_due',
                                    'grace_period',
                                    'suspended',
                                    'expired',
                                    'cancelled'
                                ] as $status)

                                    <option value="{{ $status }}">
                                        {{ ucwords(str_replace('_', ' ', $status)) }}
                                    </option>

                                @endforeach

                            </select>

                        </div>


                        {{-- Period --}}
                        <div>

                            <label class="boq-field-label">
                                Period
                            </label>

                            <select
                                wire:model.live="periodFilter"
                                class="boq-field"
                            >

                                <option value="all">
                                    All periods
                                </option>

                                <option value="current">
                                    Current
                                </option>

                                <option value="ending_30">
                                    Ending in 30 days
                                </option>

                                <option value="expired">
                                    Expired
                                </option>

                                <option value="this_year">
                                    Created this year
                                </option>

                            </select>

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

                                @foreach([10, 25, 50, 100] as $size)

                                    <option value="{{ $size }}">
                                        {{ $size }}
                                    </option>

                                @endforeach

                            </select>

                        </div>

                    </div>


                    {{-- =====================================
                         BULK ACTIONS
                    ====================================== --}}
                    @if(count($selectedSubscriptions))

                        <div class="boq-bulk-actions">

                            <span>

                                {{ count($selectedSubscriptions) }}
                                selected

                            </span>

                            <button
                                type="button"
                                wire:click="confirmBulkCancel"
                                class="boq-bulk-danger"
                            >
                                <i class="fas fa-ban"></i>
                                Cancel selected
                            </button>

                            <button
                                type="button"
                                wire:click="clearSelection"
                                class="boq-bulk-clear"
                            >
                                Clear selection
                            </button>

                        </div>

                    @endif

                </div>


                {{-- =========================================
                     SUBSCRIPTIONS TABLE
                ========================================== --}}
                <div class="boq-table-wrapper">

                    <table class="boq-table">

                        <thead>

                            <tr>

                                <th class="boq-checkbox-column">

                                    <input
                                        type="checkbox"
                                        wire:model.live="selectPage"
                                        aria-label="Select current page"
                                    >

                                </th>

                                <th>
                                    Plan
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Payment
                                </th>

                                <th>
                                    Period
                                </th>

                                <th class="text-right">
                                    Price
                                </th>

                                <th class="text-right">
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @forelse($subscriptions as $item)

                                <tr wire:key="subscription-{{ $item->id }}">

                                    {{-- Checkbox --}}
                                    <td>

                                        <input
                                            type="checkbox"
                                            wire:model.live="selectedSubscriptions"
                                            value="{{ $item->id }}"
                                            aria-label="Select subscription"
                                        >

                                    </td>


                                    {{-- Plan --}}
                                    <td>

                                        <div class="boq-table-title">
                                            {{ $item->plan?->name ?? 'Unknown plan' }}
                                        </div>

                                        @if($item->plan?->code)

                                            <div class="boq-table-subtitle">
                                                {{ $item->plan->code }}
                                            </div>

                                        @endif

                                    </td>


                                    {{-- Status --}}
                                    <td>

                                        @php

                                            $statusClass = match($item->status) {

                                                'active',
                                                'trial'
                                                    => 'boq-badge-success',

                                                'pending',
                                                'grace_period'
                                                    => 'boq-badge-warning',

                                                'past_due',
                                                'suspended',
                                                'cancelled',
                                                'expired'
                                                    => 'boq-badge-danger',

                                                default
                                                    => ''
                                            };

                                        @endphp

                                        <span class="boq-badge {{ $statusClass }}">

                                            @switch($item->status)

                                                @case('active')
                                                    <i class="fas fa-circle-check"></i>
                                                    @break

                                                @case('trial')
                                                    <i class="fas fa-gift"></i>
                                                    @break

                                                @case('pending')
                                                    <i class="fas fa-clock"></i>
                                                    @break

                                                @case('grace_period')
                                                    <i class="fas fa-hourglass-half"></i>
                                                    @break

                                                @case('past_due')
                                                    <i class="fas fa-triangle-exclamation"></i>
                                                    @break

                                                @case('suspended')
                                                    <i class="fas fa-pause-circle"></i>
                                                    @break

                                                @case('expired')
                                                    <i class="fas fa-calendar-xmark"></i>
                                                    @break

                                                @case('cancelled')
                                                    <i class="fas fa-ban"></i>
                                                    @break

                                                @default
                                                    <i class="fas fa-circle"></i>

                                            @endswitch

                                            {{ ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $item->status ?? 'unknown'
                                                )
                                            ) }}

                                        </span>

                                    </td>


                                    {{-- Payment --}}
                                    <td>

                                        {{ ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $item->payment_status ?? 'pending'
                                            )
                                        ) }}

                                    </td>


                                    {{-- Period --}}
                                    <td>

                                        <span class="boq-cell-with-icon">

                                            <i class="fas fa-calendar-day"></i>

                                            {{ $item->start_date?->format('d M Y')
                                                ?? 'Not started'
                                            }}

                                        </span>

                                        <span class="boq-date-separator">
                                            –
                                        </span>

                                        {{ $item->end_date?->format('d M Y')
                                            ?? 'Ongoing'
                                        }}

                                    </td>


                                    {{-- Price --}}
                                    <td class="text-right">

                                        <strong>

                                            {{ $item->plan?->currency ?? 'UGX' }}

                                            {{ number_format(
                                                (float) ($item->plan?->price ?? 0),
                                                0
                                            ) }}

                                        </strong>

                                    </td>


                                    {{-- Actions --}}
                                    <td class="text-right">

                                        @unless(
                                            in_array(
                                                $item->status,
                                                ['cancelled', 'expired'],
                                                true
                                            )
                                        )

                                            <button
                                                type="button"
                                                wire:click="confirmCancel({{ $item->id }})"
                                                class="boq-icon-btn boq-icon-danger"
                                                title="Cancel subscription"
                                            >
                                                <i class="fas fa-ban"></i>
                                            </button>

                                        @else

                                            <span class="boq-no-action">
                                                No action
                                            </span>

                                        @endunless

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
                                            No subscriptions match your filters.
                                        </span>

                                    </td>

                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>


                {{-- Pagination --}}
                @if($subscriptions->hasPages())

                    <div class="boq-pagination">
                        {{ $subscriptions->links() }}
                    </div>

                @endif

            </div>


        {{-- =================================================
             AVAILABLE PLANS TAB
        ================================================== --}}
        @else

            <div wire:key="plans-tab">

                {{-- =========================================
                     PLAN FILTERS
                ========================================== --}}
                <div class="boq-filter-section">

                    <div class="boq-plan-filter-grid">

                        {{-- Search --}}
                        <div>

                            <label class="boq-field-label">
                                Search Plans
                            </label>

                            <div class="boq-input-icon-wrap">

                                <i class="fas fa-search boq-input-icon"></i>

                                <input
                                    type="search"
                                    wire:model.live.debounce.300ms="planSearch"
                                    class="boq-field boq-field-with-icon"
                                    placeholder="Search plan name, code or description..."
                                >

                            </div>

                        </div>


                        {{-- Billing Period --}}
                        <div>

                            <label class="boq-field-label">
                                Billing Period
                            </label>

                            <select
                                wire:model.live="planPeriodFilter"
                                class="boq-field"
                            >

                                <option value="all">
                                    All periods
                                </option>

                                <option value="monthly">
                                    Monthly
                                </option>

                                <option value="quarterly">
                                    3 months
                                </option>

                                <option value="six_month">
                                    6 months
                                </option>

                                <option value="annual">
                                    Annual
                                </option>

                                <option value="lifetime">
                                    Lifetime
                                </option>

                            </select>

                        </div>


                        {{-- Rows --}}
                        <div>

                            <label class="boq-field-label">
                                Rows
                            </label>

                            <select
                                wire:model.live="planPerPage"
                                class="boq-field"
                            >

                                @foreach([10, 25, 50, 100] as $size)

                                    <option value="{{ $size }}">
                                        {{ $size }}
                                    </option>

                                @endforeach

                            </select>

                        </div>

                    </div>

                </div>


                {{-- =========================================
                     PLAN CARDS
                ========================================== --}}
                <div class="boq-plan-grid">

                    @forelse($plans as $plan)

                        <article
                            wire:key="available-plan-{{ $plan->id }}"
                            class="boq-plan-card"
                        >

                            {{-- Heading --}}
                            <div class="boq-plan-header">

                                <div class="boq-plan-heading-copy">

                                    <div class="boq-plan-main-icon">

                                        @switch($plan->type)

                                            @case('monthly')
                                                <i class="fas fa-calendar-alt"></i>
                                                @break

                                            @case('annual')
                                                <i class="fas fa-calendar-check"></i>
                                                @break

                                            @case('lifetime')
                                                <i class="fas fa-infinity"></i>
                                                @break

                                            @default
                                                <i class="fas fa-box-open"></i>

                                        @endswitch

                                    </div>


                                    <h3 class="boq-plan-name">
                                        {{ $plan->name }}
                                    </h3>


                                    <p class="boq-plan-description">
                                        {{ $plan->description ?: 'BOQ subscription plan' }}
                                    </p>

                                </div>


                                <span class="boq-plan-type">

                                    <i class="fas fa-tag"></i>

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $plan->type ?? 'Plan'
                                        )
                                    ) }}

                                </span>

                            </div>


                            {{-- Price --}}
                            <div class="boq-plan-price">

                                <span class="boq-plan-currency">
                                    {{ $plan->currency ?? 'UGX' }}
                                </span>

                                <span class="boq-plan-price-value">
                                    {{ number_format(
                                        (float) $plan->price,
                                        0
                                    ) }}
                                </span>

                            </div>


                            {{-- Duration --}}
                            <div class="boq-plan-duration">

                                <i class="fas fa-clock"></i>

                                @if($plan->duration_days)

                                    <span>
                                        {{ $plan->duration_days }} days
                                    </span>

                                @else

                                    <span>
                                        Lifetime access
                                    </span>

                                @endif

                            </div>


                            {{-- =====================================
                                 PROJECTS / BOQs / AI
                                 ONE HORIZONTAL CARD
                            ====================================== --}}
                            <div class="boq-plan-limits">

                                <div class="boq-plan-limits-grid">

                                    {{-- Projects --}}
                                    <div class="boq-plan-limit">

                                        <div class="boq-plan-limit-label">

                                            <i class="fas fa-folder-open"></i>

                                            Projects

                                        </div>

                                        <div class="boq-plan-limit-value">
                                            {{ $plan->max_projects ?? '∞' }}
                                        </div>

                                    </div>


                                    {{-- BOQs --}}
                                    <div class="boq-plan-limit">

                                        <div class="boq-plan-limit-label">

                                            <i class="fas fa-file-invoice-dollar"></i>

                                            BOQs

                                        </div>

                                        <div class="boq-plan-limit-value">
                                            {{ $plan->max_boqs ?? '∞' }}
                                        </div>

                                    </div>


                                    {{-- AI --}}
                                    <div class="boq-plan-limit">

                                        <div class="boq-plan-limit-label">

                                            <i class="fas fa-robot"></i>

                                            AI

                                        </div>

                                        <div class="boq-plan-limit-value">
                                            {{ $plan->max_ai_credits ?? '∞' }}
                                        </div>

                                    </div>

                                </div>

                            </div>


                            {{-- =====================================
                                 FEATURES
                            ====================================== --}}
                            @if(
                                $plan->features
                                && $plan->features->isNotEmpty()
                            )

                                <div class="boq-plan-features">

                                    <p class="boq-plan-features-title">

                                        <i class="fas fa-list-check"></i>

                                        Features

                                    </p>


                                    <div class="boq-plan-feature-list">

                                        @foreach(
                                            $plan->features->take(5)
                                            as $feature
                                        )

                                            <div class="boq-plan-feature">

                                                <span class="boq-plan-feature-check">
                                                    <i class="fas fa-check"></i>
                                                </span>

                                                <span>
                                                    {{ $feature->name }}
                                                </span>

                                            </div>

                                        @endforeach

                                    </div>


                                    @if($plan->features->count() > 5)

                                        <p class="boq-plan-more-features">

                                            <i class="fas fa-plus-circle"></i>

                                            {{ $plan->features->count() - 5 }}
                                            more features

                                        </p>

                                    @endif

                                </div>

                            @endif


                            {{-- Trial --}}
                            @if(
                                ($plan->has_trial ?? false)
                                && ($plan->trial_days ?? 0) > 0
                            )

                                <div class="boq-plan-trial">

                                    <i class="fas fa-gift"></i>

                                    {{ $plan->trial_days }}-day free trial

                                </div>

                            @endif


                            {{-- =====================================
                                 ACTION
                            ====================================== --}}
                            <div class="boq-plan-action">

                                @if(
                                    $subscription
                                    && (int) $subscription->plan_id === (int) $plan->id
                                )

                                    <span class="boq-current-plan-button">

                                        <i class="fas fa-circle-check"></i>

                                        Current Plan

                                    </span>

                                @else

                                    <button
                                        type="button"
                                        wire:click="confirmSubscribe({{ $plan->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="confirmSubscribe({{ $plan->id }})"
                                        class="boq-plan-choose-button"
                                    >

                                        <i class="fas fa-circle-check"></i>

                                        <span
                                            wire:loading.remove
                                            wire:target="confirmSubscribe({{ $plan->id }})"
                                        >
                                            Choose Plan
                                        </span>

                                        <span
                                            wire:loading
                                            wire:target="confirmSubscribe({{ $plan->id }})"
                                        >
                                            Processing...
                                        </span>

                                    </button>

                                @endif

                            </div>

                        </article>


                    @empty

                        <div class="boq-plans-empty">

                            <i class="fas fa-layer-group"></i>

                            <p>
                                No available plans match your search.
                            </p>

                        </div>

                    @endforelse

                </div>


                {{-- Pagination --}}
                @if($plans->hasPages())

                    <div class="boq-pagination">
                        {{ $plans->links() }}
                    </div>

                @endif

            </div>

        @endif

    </div>


    {{-- =====================================================
         CONFIRMATION MODAL
    ====================================================== --}}
    @if($showActionModal)

        <div
            class="boq-modal-backdrop"
            wire:key="subscription-action-modal"
        >

            <div class="boq-modal boq-modal-sm">

                <div class="boq-modal-head">

                    <h2>
                        {{ $actionTitle }}
                    </h2>

                    <button
                        type="button"
                        wire:click="closeActionModal"
                        class="boq-modal-close"
                        aria-label="Close"
                    >
                        <i class="fas fa-xmark"></i>
                    </button>

                </div>


                <div class="boq-modal-body">

                    <p class="boq-modal-message">
                        {{ $actionMessage }}
                    </p>

                </div>


                <div class="boq-modal-foot">

                    <button
                        type="button"
                        wire:click="closeActionModal"
                        class="boq-btn-secondary"
                    >
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </button>


                    <button
                        type="button"
                        wire:click="performAction"
                        wire:loading.attr="disabled"
                        wire:target="performAction"
                        class="{{
                            in_array(
                                $actionType,
                                ['cancel', 'bulk_cancel'],
                                true
                            )
                                ? 'boq-btn-danger'
                                : 'boq-btn-primary'
                        }}"
                    >

                        <i class="fas {{
                            in_array(
                                $actionType,
                                ['cancel', 'bulk_cancel'],
                                true
                            )
                                ? 'fa-ban'
                                : 'fa-check'
                        }}"></i>

                        <span
                            wire:loading.remove
                            wire:target="performAction"
                        >
                            Confirm
                        </span>

                        <span
                            wire:loading
                            wire:target="performAction"
                        >
                            Processing...
                        </span>

                    </button>

                </div>

            </div>

        </div>

    @endif

</div>
