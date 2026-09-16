<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
                <i class="fas fa-layer-group text-[#05645b]"></i>
                Plans & Pricing
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Choose the plan that fits your BOQ workflow.
            </p>
        </div>

        <a
            href="{{ route('subscriptions.index') }}"
            class="boq-btn-primary"
        >
            <i class="fas fa-credit-card"></i>
            My Subscription
        </a>
    </div>

    {{-- Statistics --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">

        <div class="boq-stat-card boq-stat-green">
            <div>
                <p class="boq-stat-label">Available Plans</p>

                <p class="boq-stat-value">
                    {{ $stats['available_plans'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-layer-group"></i>
            </span>
        </div>

        <div class="boq-stat-card boq-stat-blue">
            <div>
                <p class="boq-stat-label">Monthly Plans</p>

                <p class="boq-stat-value">
                    {{ $stats['monthly_plans'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </span>
        </div>

        <div class="boq-stat-card boq-stat-amber">
            <div>
                <p class="boq-stat-label">Annual Plans</p>

                <p class="boq-stat-value">
                    {{ $stats['annual_plans'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-calendar-check"></i>
            </span>
        </div>

    </div>

    {{-- Filters --}}
    <div class="boq-panel p-4">

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(300px,1fr)_130px_auto] xl:items-end">

            <div>
                <label class="boq-field-label">
                    Search Plans
                </label>

                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="Search plan name, code, type or currency..."
                    >
                </div>
            </div>

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

            <div>
                <label class="boq-field-label">
                    Sort By
                </label>

                <div class="flex flex-wrap gap-2">

                    @foreach([
                        'name' => ['Name', 'fa-font'],
                        'price' => ['Price', 'fa-money-bill-wave'],
                        'duration_days' => ['Duration', 'fa-clock'],
                        'display_order' => ['Order', 'fa-sort-numeric-down'],
                    ] as $field => [$label, $icon])

                        <button
                            type="button"
                            wire:click="sortBy('{{ $field }}')"
                            class="inline-flex h-10 items-center gap-2 rounded-lg px-3 text-xs font-semibold transition
                                {{ $sortBy === $field
                                    ? 'bg-[#05645b] text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }}"
                        >
                            <i class="fas {{ $icon }}"></i>

                            {{ $label }}

                            @if($sortBy === $field)
                                <i class="fas {{ $sortDir === 'asc'
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

    {{-- Plans --}}
    @if($plans->isNotEmpty())

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">

            @foreach($plans as $plan)

                <div
                    wire:key="plan-{{ $plan->id }}"
                    class="flex flex-col overflow-hidden rounded-xl border border-slate-200 border-l-4 border-l-[#05645b] bg-white shadow-sm"
                >

                    <div class="flex flex-1 flex-col p-5">

                        {{-- Heading --}}
                        <div class="flex items-start justify-between gap-3">

                            <div class="min-w-0">

                                <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-[#05645b]">

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

                                <h2 class="text-lg font-bold text-slate-900">
                                    {{ $plan->name }}
                                </h2>

                                @if($plan->description)
                                    <p class="mt-1 text-sm leading-5 text-slate-500">
                                        {{ $plan->description }}
                                    </p>
                                @endif

                            </div>

                            <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">

                                <i class="fas fa-tag mr-1"></i>

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
                        <div class="mt-4">

                            <div class="flex items-baseline gap-2">

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
                                    {{ $plan->duration_days }} days
                                @else
                                    Lifetime access
                                @endif

                            </div>

                        </div>

                        {{-- Plan limits in ONE card --}}
                        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">

                            <div class="grid grid-cols-3 divide-x divide-slate-200">

                                <div class="px-2 py-3 text-center">

                                    <div class="flex items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                        <i class="fas fa-folder-open"></i>
                                        Projects
                                    </div>

                                    <div class="mt-1 text-base font-bold text-slate-900">
                                        {{ $plan->max_projects ?? '∞' }}
                                    </div>

                                </div>

                                <div class="px-2 py-3 text-center">

                                    <div class="flex items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        BOQs
                                    </div>

                                    <div class="mt-1 text-base font-bold text-slate-900">
                                        {{ $plan->max_boqs ?? '∞' }}
                                    </div>

                                </div>

                                <div class="px-2 py-3 text-center">

                                    <div class="flex items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                        <i class="fas fa-robot"></i>
                                        AI
                                    </div>

                                    <div class="mt-1 text-base font-bold text-slate-900">
                                        {{ $plan->max_ai_credits ?? '∞' }}
                                    </div>

                                </div>

                            </div>

                        </div>

                        {{-- Features --}}
                        @if($plan->features && $plan->features->isNotEmpty())

                            <div class="mt-4 border-t border-slate-100 pt-4">

                                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                                    <i class="fas fa-list-check mr-1"></i>
                                    Features
                                </p>

                                <div class="space-y-2">

                                    @foreach($plan->features->take(5) as $feature)

                                        <div class="flex items-start gap-2 text-sm text-slate-600">

                                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-[#05645b]">
                                                <i class="fas fa-check text-[9px]"></i>
                                            </span>

                                            <span>
                                                {{ $feature->name }}
                                            </span>

                                        </div>

                                    @endforeach

                                </div>

                                @if($plan->features->count() > 5)

                                    <p class="mt-3 text-xs font-semibold text-[#05645b]">
                                        <i class="fas fa-plus-circle mr-1"></i>

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

                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">

                                <i class="fas fa-gift mr-1"></i>

                                {{ $plan->trial_days }}-day free trial

                            </div>

                        @endif

                        {{-- CTA --}}
                        <div class="mt-auto pt-5">

                            <a
                                href="{{ route('subscriptions.index') }}"
                                class="boq-btn-primary w-full justify-center"
                            >
                                <i class="fas fa-check-circle"></i>
                                Choose Plan
                            </a>

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

        @if($plans->hasPages())

            <div class="boq-panel p-4">
                {{ $plans->links() }}
            </div>

        @endif

    @else

        <div class="boq-panel py-12 text-center">

            <i class="fas fa-layer-group text-3xl text-slate-300"></i>

            <h3 class="mt-3 font-semibold text-slate-900">
                No plans found
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                No subscription plans match your search.
            </p>

        </div>

    @endif

</div>
