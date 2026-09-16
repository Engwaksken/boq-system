<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
                <i class="fas fa-credit-card text-[#05645b]"></i>
                Subscriptions
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Manage your subscription history and choose an available plan.
            </p>

        </div>

        @if($subscription)

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2">

                <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">
                    Current Subscription
                </div>

                <div class="text-sm font-bold text-emerald-950">
                    {{ $subscription->plan?->name ?? 'Plan' }}
                </div>

            </div>

        @endif

    </div>

    {{-- Flash --}}
    @if(session('message'))

        <div class="boq-flash">

            <i class="fas fa-circle-check mr-2"></i>

            {{ session('message') }}

        </div>

    @endif

    {{-- Statistics --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

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

    {{-- Main --}}
    <div class="boq-panel overflow-hidden">

        {{-- Tabs --}}
        <div class="boq-tabs">

            <button
                type="button"
                wire:click.prevent="showSubscriptions"
                wire:loading.attr="disabled"
                wire:target="showSubscriptions,showPlans"
                class="boq-tab {{ $activeTab === 'subscriptions'
                    ? 'is-active'
                    : ''
                }}"
            >
                <i class="fas fa-credit-card"></i>
                Subscriptions
            </button>

            <button
                type="button"
                wire:click.prevent="showPlans"
                wire:loading.attr="disabled"
                wire:target="showSubscriptions,showPlans"
                class="boq-tab {{ $activeTab === 'plans'
                    ? 'is-active'
                    : ''
                }}"
            >
                <i class="fas fa-layer-group"></i>
                Available Plans
            </button>

        </div>

        {{-- Loading --}}
        <div
            wire:loading.flex
            wire:target="showSubscriptions,showPlans"
            class="items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-500"
        >
            <i class="fas fa-spinner fa-spin"></i>
            Loading...
        </div>

        {{-- Subscription tab --}}
        @if($activeTab === 'subscriptions')

            <div wire:key="subscriptions-tab">

                {{-- Filter row --}}
                <div class="border-b border-slate-200 p-4">

                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(300px,1fr)_180px_200px_110px] xl:items-end">

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
                                        {{ ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $status
                                            )
                                        ) }}
                                    </option>

                                @endforeach
                            </select>

                        </div>

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

                        <div>

                            <label class="boq-field-label">
                                Rows
                            </label>

                            <select
                                wire:model.live="perPage"
                                class="boq-field"
                            >
                                @foreach([10,25,50,100] as $size)

                                    <option value="{{ $size }}">
                                        {{ $size }}
                                    </option>

                                @endforeach
                            </select>

                        </div>

                    </div>

                    @if(count($selectedSubscriptions))

                        <div class="mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">

                            <span class="text-sm font-semibold text-amber-900">
                                {{ count($selectedSubscriptions) }}
                                selected
                            </span>

                            <button
                                type="button"
                                wire:click="confirmBulkCancel"
                                class="text-sm font-bold text-red-700"
                            >
                                <i class="fas fa-ban mr-1"></i>
                                Cancel selected
                            </button>

                            <button
                                type="button"
                                wire:click="clearSelection"
                                class="text-sm font-semibold text-slate-600"
                            >
                                Clear selection
                            </button>

                        </div>

                    @endif

                </div>

                {{-- Table --}}
                <div class="overflow-x-auto">

                    <table class="boq-table min-w-full">

                        <thead>

                            <tr>

                                <th class="w-10">

                                    <input
                                        type="checkbox"
                                        wire:model.live="selectPage"
                                        aria-label="Select current page"
                                    >

                                </th>

                                <th>Plan</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Period</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            @forelse($subscriptions as $item)

                                <tr wire:key="subscription-{{ $item->id }}">

                                    <td>

                                        <input
                                            type="checkbox"
                                            wire:model.live="selectedSubscriptions"
                                            value="{{ $item->id }}"
                                            aria-label="Select subscription"
                                        >

                                    </td>

                                    <td>

                                        <div class="font-semibold text-slate-900">
                                            {{ $item->plan?->name ?? 'Unknown plan' }}
                                        </div>

                                        @if($item->plan?->code)

                                            <div class="text-xs text-slate-500">
                                                {{ $item->plan->code }}
                                            </div>

                                        @endif

                                    </td>

                                    <td>

                                        <span class="boq-badge">
                                            {{ ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $item->status
                                                )
                                            ) }}
                                        </span>

                                    </td>

                                    <td>

                                        {{ ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $item->payment_status
                                                    ?? 'pending'
                                            )
                                        ) }}

                                    </td>

                                    <td>

                                        {{ $item->start_date?->format('d M Y')
                                            ?? 'Not started'
                                        }}

                                        <span class="text-slate-400">
                                            –
                                        </span>

                                        {{ $item->end_date?->format('d M Y')
                                            ?? 'Ongoing'
                                        }}

                                    </td>

                                    <td class="text-right font-semibold">

                                        {{ $item->plan?->currency ?? 'UGX' }}

                                        {{ number_format(
                                            (float) (
                                                $item->plan?->price ?? 0
                                            ),
                                            0
                                        ) }}

                                    </td>

                                    <td class="text-right">

                                        @unless(
                                            in_array(
                                                $item->status,
                                                [
                                                    'cancelled',
                                                    'expired'
                                                ],
                                                true
                                            )
                                        )

                                            <button
                                                type="button"
                                                wire:click="confirmCancel({{ $item->id }})"
                                                class="boq-icon-btn text-red-600"
                                                title="Cancel"
                                            >
                                                <i class="fas fa-ban"></i>
                                            </button>

                                        @else

                                            <span class="text-xs text-slate-400">
                                                No action
                                            </span>

                                        @endunless

                                    </td>

                                </tr>

                            @empty

                                <tr>

                                    <td
                                        colspan="7"
                                        class="p-10 text-center text-sm text-slate-500"
                                    >

                                        <i class="fas fa-receipt mb-2 block text-2xl text-slate-300"></i>

                                        No subscriptions match your filters.

                                    </td>

                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>

                @if($subscriptions->hasPages())

                    <div class="border-t border-slate-200 p-4">
                        {{ $subscriptions->links() }}
                    </div>

                @endif

            </div>

        {{-- Plans tab --}}
        @else

            <div wire:key="plans-tab">

                <div class="border-b border-slate-200 p-4">

                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(320px,1fr)_220px_110px] xl:items-end">

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

                        <div>

                            <label class="boq-field-label">
                                Rows
                            </label>

                            <select
                                wire:model.live="planPerPage"
                                class="boq-field"
                            >
                                @foreach([10,25,50,100] as $size)

                                    <option value="{{ $size }}">
                                        {{ $size }}
                                    </option>

                                @endforeach
                            </select>

                        </div>

                    </div>

                </div>

                {{-- Plan Cards --}}
                <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">

                    @forelse($plans as $plan)

                        <div
                            wire:key="available-plan-{{ $plan->id }}"
                            class="flex flex-col rounded-xl border border-slate-200 border-l-4 border-l-[#05645b] bg-white p-4 shadow-sm"
                        >

                            <div class="flex items-start justify-between gap-3">

                                <div>

                                    <h3 class="font-bold text-slate-900">
                                        {{ $plan->name }}
                                    </h3>

                                    <p class="mt-1 text-xs leading-5 text-slate-500">
                                        {{ $plan->description
                                            ?: 'BOQ subscription plan'
                                        }}
                                    </p>

                                </div>

                                <span class="boq-badge">

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $plan->type ?? 'plan'
                                        )
                                    ) }}

                                </span>

                            </div>

                            <div class="mt-4">

                                <span class="text-sm font-semibold text-slate-500">
                                    {{ $plan->currency ?? 'UGX' }}
                                </span>

                                <span class="text-2xl font-bold text-slate-900">
                                    {{ number_format(
                                        (float) $plan->price,
                                        0
                                    ) }}
                                </span>

                            </div>

                            <div class="mt-1 flex items-center gap-2 text-xs text-slate-500">

                                <i class="fas fa-clock"></i>

                                @if($plan->duration_days)

                                    {{ $plan->duration_days }}
                                    days

                                @else

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $plan->type ?? 'Lifetime'
                                        )
                                    ) }}

                                @endif

                            </div>

                            {{-- One limits card --}}
                            <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">

                                <div class="grid grid-cols-3 divide-x divide-slate-200">

                                    <div class="px-2 py-3 text-center">

                                        <div class="text-[10px] font-semibold uppercase text-slate-400">
                                            <i class="fas fa-folder-open mr-1"></i>
                                            Projects
                                        </div>

                                        <div class="mt-1 text-base font-bold text-slate-900">
                                            {{ $plan->max_projects ?? '∞' }}
                                        </div>

                                    </div>

                                    <div class="px-2 py-3 text-center">

                                        <div class="text-[10px] font-semibold uppercase text-slate-400">
                                            <i class="fas fa-file-invoice-dollar mr-1"></i>
                                            BOQs
                                        </div>

                                        <div class="mt-1 text-base font-bold text-slate-900">
                                            {{ $plan->max_boqs ?? '∞' }}
                                        </div>

                                    </div>

                                    <div class="px-2 py-3 text-center">

                                        <div class="text-[10px] font-semibold uppercase text-slate-400">
                                            <i class="fas fa-robot mr-1"></i>
                                            AI
                                        </div>

                                        <div class="mt-1 text-base font-bold text-slate-900">
                                            {{ $plan->max_ai_credits ?? '∞' }}
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="mt-auto pt-4">

                                @if(
                                    $subscription
                                    && (int) $subscription->plan_id
                                        === (int) $plan->id
                                )

                                    <span class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-slate-100 text-sm font-semibold text-slate-500">

                                        <i class="fas fa-circle-check mr-2"></i>

                                        Current Plan

                                    </span>

                                @else

                                    <button
                                        type="button"
                                        wire:click="confirmSubscribe({{ $plan->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="confirmSubscribe({{ $plan->id }})"
                                        class="boq-btn-primary w-full justify-center"
                                    >
                                        <i class="fas fa-check-circle"></i>

                                        Choose Plan
                                    </button>

                                @endif

                            </div>

                        </div>

                    @empty

                        <div class="md:col-span-2 xl:col-span-3 p-10 text-center text-sm text-slate-500">

                            <i class="fas fa-layer-group mb-2 block text-2xl text-slate-300"></i>

                            No available plans match your search.

                        </div>

                    @endforelse

                </div>

                @if($plans->hasPages())

                    <div class="border-t border-slate-200 p-4">
                        {{ $plans->links() }}
                    </div>

                @endif

            </div>

        @endif

    </div>

    {{-- Action Modal --}}
    @if($showActionModal)

        <div
            class="boq-modal-backdrop"
            wire:key="subscription-action-modal"
        >

            <div class="boq-modal max-w-md">

                <div class="boq-modal-head">

                    <h2 class="text-lg font-bold text-slate-900">
                        {{ $actionTitle }}
                    </h2>

                    <button
                        type="button"
                        wire:click="closeActionModal"
                        class="boq-modal-close"
                    >
                        <i class="fas fa-xmark"></i>
                    </button>

                </div>

                <div class="boq-modal-body">

                    <p class="text-sm leading-6 text-slate-600">
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
                        class="{{ in_array(
                            $actionType,
                            [
                                'cancel',
                                'bulk_cancel'
                            ],
                            true
                        )
                            ? 'boq-btn-danger'
                            : 'boq-btn-primary'
                        }}"
                    >
                        <i class="fas {{
                            in_array(
                                $actionType,
                                [
                                    'cancel',
                                    'bulk_cancel'
                                ],
                                true
                            )
                                ? 'fa-ban'
                                : 'fa-check'
                        }}"></i>

                        Confirm
                    </button>

                </div>

            </div>

        </div>

    @endif

</div>
