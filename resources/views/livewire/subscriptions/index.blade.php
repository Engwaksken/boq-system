<div class="boq-subscriptions-page">

    {{-- =========================================================
         HEADER
    ========================================================== --}}
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

        @if($currentSubscription)
            <div class="boq-current-subscription">

                <div class="boq-current-subscription-label">
                    Current Subscription
                </div>

                <div class="boq-current-subscription-name">
                    {{ $currentSubscription->plan?->name ?? 'Plan' }}
                </div>

            </div>
        @endif

    </div>


    {{-- =========================================================
         FLASH MESSAGE
    ========================================================== --}}
    @if(session('message'))
        <div
            class="boq-flash"
            x-data="{ visible: true }"
            x-init="setTimeout(() => visible = false, 5000)"
            x-show="visible"
            x-transition
        >
            <i class="fas fa-circle-check"></i>

            <span>
                {{ session('message') }}
            </span>

            <button
                type="button"
                class="boq-flash-close"
                x-on:click="visible = false"
                aria-label="Close message"
            >
                <i class="fas fa-xmark"></i>
            </button>
        </div>
    @endif


    {{-- =========================================================
         STATISTICS
    ========================================================== --}}
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


    {{-- =========================================================
         MAIN SUBSCRIPTION PANEL
    ========================================================== --}}
    <div class="boq-panel boq-subscription-panel">

        {{-- =====================================================
             TABS
        ====================================================== --}}
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


        {{-- =====================================================
             TAB LOADING INDICATOR
        ====================================================== --}}
        <div
            wire:loading.flex
            wire:target="showSubscriptions,showPlans"
            class="boq-tab-loading"
        >
            <i class="fas fa-spinner fa-spin"></i>
            Loading...
        </div>


        {{-- =====================================================
             SUBSCRIPTIONS TAB
        ====================================================== --}}
        @if($activeTab === 'subscriptions')

            <div wire:key="subscriptions-tab">

                {{-- =================================================
                     FILTERS
                ================================================== --}}
                <div class="boq-filter-section">

                    <div class="boq-subscription-filter-grid">

                        {{-- Search --}}
                        <div>

                            <label
                                for="subscription-search"
                                class="boq-field-label"
                            >
                                Search
                            </label>

                            <div class="boq-input-icon-wrap">

                                <i class="fas fa-search boq-input-icon"></i>

                                <input
                                    id="subscription-search"
                                    type="search"
                                    wire:model.live.debounce.300ms="search"
                                    class="boq-field boq-field-with-icon"
                                    placeholder="Search plan, status or payment..."
                                    autocomplete="off"
                                >

                            </div>

                        </div>


                        {{-- Status --}}
                        <div>

                            <label
                                for="subscription-status"
                                class="boq-field-label"
                            >
                                Status
                            </label>

                            <select
                                id="subscription-status"
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
                                    'cancelled',
                                ] as $status)

                                    <option value="{{ $status }}">
                                        {{ ucwords(str_replace('_', ' ', $status)) }}
                                    </option>

                                @endforeach

                            </select>

                        </div>


                        {{-- Period --}}
                        <div>

                            <label
                                for="subscription-period"
                                class="boq-field-label"
                            >
                                Period
                            </label>

                            <select
                                id="subscription-period"
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

                            <label
                                for="subscription-per-page"
                                class="boq-field-label"
                            >
                                Rows
                            </label>

                            <select
                                id="subscription-per-page"
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


                    {{-- =============================================
                         BULK ACTIONS
                    ============================================== --}}
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


                {{-- =================================================
                     SUBSCRIPTIONS TABLE
                ================================================== --}}
                <div class="boq-table-wrapper">

                    <table class="boq-table">

                        <thead>
                            <tr>

                                <th class="boq-checkbox-column">

                                    <input
                                        type="checkbox"
                                        wire:model.live="selectPage"
                                        aria-label="Select all subscriptions on this page"
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
                                            aria-label="Select {{ $item->plan?->name ?? 'subscription' }}"
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
                                                    => '',
                                            };

                                            $statusIcon = match($item->status) {
                                                'active'
                                                    => 'fa-circle-check',

                                                'trial'
                                                    => 'fa-gift',

                                                'pending'
                                                    => 'fa-clock',

                                                'grace_period'
                                                    => 'fa-hourglass-half',

                                                'past_due'
                                                    => 'fa-triangle-exclamation',

                                                'suspended'
                                                    => 'fa-pause-circle',

                                                'expired'
                                                    => 'fa-calendar-xmark',

                                                'cancelled'
                                                    => 'fa-ban',

                                                default
                                                    => 'fa-circle',
                                            };
                                        @endphp

                                        <span class="boq-badge {{ $statusClass }}">

                                            <i class="fas {{ $statusIcon }}"></i>

                                            {{ ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $item->status ?? 'unknown'
                                                )
                                            ) }}

                                        </span>

                                    </td>


                                    {{-- Payment Status --}}
                                    <td>

                                        @php
                                            $paymentStatus =
                                                $item->payment_status
                                                ?? 'pending';

                                            $paymentClass = match($paymentStatus) {
                                                'paid',
                                                'successful',
                                                'completed'
                                                    => 'boq-badge-success',

                                                'pending',
                                                'processing',
                                                'initiated'
                                                    => 'boq-badge-warning',

                                                'failed',
                                                'cancelled',
                                                'refunded'
                                                    => 'boq-badge-danger',

                                                default
                                                    => '',
                                            };
                                        @endphp

                                        <span class="boq-badge {{ $paymentClass }}">
                                            {{ ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $paymentStatus
                                                )
                                            ) }}
                                        </span>

                                    </td>


                                    {{-- Period --}}
                                    <td>

                                        <div class="boq-cell-stack">

                                            <span class="boq-cell-with-icon">

                                                <i class="fas fa-calendar-day"></i>

                                                {{ $item->start_date?->format('d M Y')
                                                    ?? 'Not started'
                                                }}

                                            </span>

                                            <span class="boq-table-subtitle">

                                                Until

                                                {{ $item->end_date?->format('d M Y')
                                                    ?? 'Ongoing'
                                                }}

                                            </span>

                                        </div>

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
                                                [
                                                    'cancelled',
                                                    'expired',
                                                ],
                                                true
                                            )
                                        )

                                            <button
                                                type="button"
                                                wire:click="confirmCancel({{ $item->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="confirmCancel({{ $item->id }})"
                                                class="boq-icon-btn boq-icon-danger"
                                                title="Cancel subscription"
                                                aria-label="Cancel subscription"
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


                {{-- =================================================
                     SUBSCRIPTION PAGINATION
                ================================================== --}}
                @if($subscriptions->hasPages())

                    <div class="boq-pagination">
                        {{ $subscriptions->links() }}
                    </div>

                @endif

            </div>


        {{-- =========================================================
             AVAILABLE PLANS TAB
        ========================================================== --}}
        @else

            <div wire:key="plans-tab">

                {{-- =================================================
                     PLAN FILTERS
                ================================================== --}}
                <div class="boq-filter-section">

                    <div class="boq-plan-filter-grid">

                        {{-- Search --}}
                        <div>

                            <label
                                for="plan-search"
                                class="boq-field-label"
                            >
                                Search Plans
                            </label>

                            <div class="boq-input-icon-wrap">

                                <i class="fas fa-search boq-input-icon"></i>

                                <input
                                    id="plan-search"
                                    type="search"
                                    wire:model.live.debounce.300ms="planSearch"
                                    class="boq-field boq-field-with-icon"
                                    placeholder="Search plan name, code or description..."
                                    autocomplete="off"
                                >

                            </div>

                        </div>


                        {{-- Billing Period --}}
                        <div>

                            <label
                                for="plan-period"
                                class="boq-field-label"
                            >
                                Billing Period
                            </label>

                            <select
                                id="plan-period"
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

                            <label
                                for="plan-per-page"
                                class="boq-field-label"
                            >
                                Rows
                            </label>

                            <select
                                id="plan-per-page"
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


                {{-- =================================================
                     PLAN CARDS
                ================================================== --}}
                <div class="boq-plan-grid">

                    @forelse($plans as $plan)

                        <article
                            wire:key="available-plan-{{ $plan->id }}"
                            class="boq-plan-card"
                        >

                            {{-- =========================================
                                 PLAN HEADER
                            ========================================== --}}
                            <div class="boq-plan-header">

                                <div class="boq-plan-heading-copy">

                                    <div class="boq-plan-main-icon">

                                        @switch($plan->type)

                                            @case('monthly')
                                                <i class="fas fa-calendar-alt"></i>
                                                @break

                                            @case('three_month')
                                                <i class="fas fa-calendar-week"></i>
                                                @break

                                            @case('six_month')
                                                <i class="fas fa-calendar-days"></i>
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


                                    <div>

                                        <h3 class="boq-plan-name">
                                            {{ $plan->name }}
                                        </h3>

                                        <p class="boq-plan-description">
                                            {{ $plan->description ?: 'BOQ subscription plan' }}
                                        </p>

                                    </div>

                                </div>


                                <span class="boq-plan-type">

                                    <i class="fas fa-tag"></i>

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $plan->type ?? 'plan'
                                        )
                                    ) }}

                                </span>

                            </div>


                            {{-- =========================================
                                 PRICE
                            ========================================== --}}
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


                            {{-- =========================================
                                 DURATION
                            ========================================== --}}
                            <div class="boq-plan-duration">

                                <i class="fas fa-clock"></i>

                                @if($plan->type === 'lifetime')

                                    <span>
                                        Lifetime access
                                    </span>

                                @elseif($plan->duration_days)

                                    <span>
                                        {{ $plan->duration_days }} days
                                    </span>

                                @else

                                    <span>
                                        Duration based on plan
                                    </span>

                                @endif

                            </div>


                            {{-- =========================================
                                 PLAN LIMITS
                            ========================================== --}}
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


                            {{-- =========================================
                                 EXTRA LIMITS
                            ========================================== --}}
                            @if(
                                $plan->max_users
                                || $plan->max_ocr_pages
                                || $plan->max_storage_bytes
                            )

                                <div class="boq-plan-secondary-limits">

                                    @if($plan->max_users)

                                        <div>
                                            <i class="fas fa-users"></i>

                                            {{ $plan->max_users }}
                                            users
                                        </div>

                                    @endif


                                    @if($plan->max_ocr_pages)

                                        <div>
                                            <i class="fas fa-file-lines"></i>

                                            {{ $plan->max_ocr_pages }}
                                            OCR pages
                                        </div>

                                    @endif


                                    @if($plan->max_storage_bytes)

                                        <div>
                                            <i class="fas fa-database"></i>

                                            {{ number_format(
                                                $plan->max_storage_bytes
                                                / 1024
                                                / 1024,
                                                0
                                            ) }}
                                            MB storage
                                        </div>

                                    @endif

                                </div>

                            @endif


                            {{-- =========================================
                                 FEATURES
                            ========================================== --}}
                            @if(
                                $plan->relationLoaded('features')
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


                            {{-- =========================================
                                 FREE TRIAL
                            ========================================== --}}
                            @if(
                                ($plan->has_trial ?? false)
                                && ($plan->trial_days ?? 0) > 0
                            )

                                <div class="boq-plan-trial">

                                    <i class="fas fa-gift"></i>

                                    {{ $plan->trial_days }}-day free trial

                                </div>

                            @endif


                            {{-- =========================================
                                 ACTION
                            ========================================== --}}
                            <div class="boq-plan-action">

                                @if(
                                    $currentSubscription
                                    && (int) $currentSubscription->plan_id
                                        === (int) $plan->id
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

                                        <i
                                            wire:loading.remove
                                            wire:target="confirmSubscribe({{ $plan->id }})"
                                            class="fas fa-circle-check"
                                        ></i>

                                        <i
                                            wire:loading
                                            wire:target="confirmSubscribe({{ $plan->id }})"
                                            class="fas fa-spinner fa-spin"
                                        ></i>

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


                {{-- =================================================
                     PLAN PAGINATION
                ================================================== --}}
                @if($plans->hasPages())

                    <div class="boq-pagination">
                        {{ $plans->links() }}
                    </div>

                @endif

            </div>

        @endif

    </div>


    {{-- =========================================================
         CONFIRMATION MODAL
    ========================================================== --}}
    @if($showActionModal)

        <div
            class="boq-modal-backdrop"
            wire:key="subscription-action-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="subscription-action-title"
        >

            <div class="boq-modal boq-modal-sm">

                {{-- Modal Header --}}
                <div class="boq-modal-head">

                    <h2 id="subscription-action-title">
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


                {{-- Modal Body --}}
                <div class="boq-modal-body">

                    <p class="boq-modal-message">
                        {{ $actionMessage }}
                    </p>

                </div>


                {{-- Modal Footer --}}
                <div class="boq-modal-foot">

                    <button
                        type="button"
                        wire:click="closeActionModal"
                        wire:loading.attr="disabled"
                        wire:target="performAction"
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
                                [
                                    'cancel',
                                    'bulk_cancel',
                                ],
                                true
                            )
                                ? 'boq-btn-danger'
                                : 'boq-btn-primary'
                        }}"
                    >

                        <i
                            wire:loading.remove
                            wire:target="performAction"
                            class="fas {{
                                in_array(
                                    $actionType,
                                    [
                                        'cancel',
                                        'bulk_cancel',
                                    ],
                                    true
                                )
                                    ? 'fa-ban'
                                    : 'fa-check'
                            }}"
                        ></i>

                        <i
                            wire:loading
                            wire:target="performAction"
                            class="fas fa-spinner fa-spin"
                        ></i>


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