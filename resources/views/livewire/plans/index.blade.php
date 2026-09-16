<div>
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 mb-8 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="flex items-center gap-3 text-3xl font-bold text-slate-900">
                <i class="fas fa-layer-group text-[#05645b]"></i>
                <span>Plans & Pricing</span>
            </h1>

            <p class="mt-1 text-slate-500">
                Choose the plan that fits your BOQ workflow.
            </p>
        </div>

        <a
            href="{{ route('subscriptions.index') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#05645b] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#044f48]"
        >
            <i class="fas fa-credit-card"></i>
            <span>My Subscription</span>
        </a>
    </div>

    {{-- Statistics --}}
    <div class="grid grid-cols-1 gap-4 mb-8 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 border-l-4 border-l-[#05645b] bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">
                        Available Plans
                    </p>

                    <p class="mt-1 text-3xl font-bold text-slate-900">
                        {{ $stats['available_plans'] ?? 0 }}
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-[#05645b]">
                    <i class="fas fa-layer-group text-lg"></i>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 border-l-4 border-l-blue-500 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">
                        Monthly Plans
                    </p>

                    <p class="mt-1 text-3xl font-bold text-slate-900">
                        {{ $stats['monthly_plans'] ?? 0 }}
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fas fa-calendar-alt text-lg"></i>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 border-l-4 border-l-amber-500 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">
                        Annual Plans
                    </p>

                    <p class="mt-1 text-3xl font-bold text-slate-900">
                        {{ $stats['annual_plans'] ?? 0 }}
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fas fa-calendar-check text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Search / Filters --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative w-full lg:max-w-md">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fas fa-search text-sm text-slate-400"></i>
                </div>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search plans by name, code, type or currency..."
                    class="boq-field pl-10"
                >
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <select
                        wire:model.live="perPage"
                        class="boq-field min-w-[150px] pr-10"
                    >
                        @foreach($perPageOptions as $option)
                            <option value="{{ $option }}">
                                {{ $option }} per page
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="sortBy('name')"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition
                            {{ $sortBy === 'name'
                                ? 'bg-[#05645b] text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                    >
                        <i class="fas fa-font"></i>
                        <span>Name</span>

                        @if($sortBy === 'name')
                            <i class="fas {{ $sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                        @endif
                    </button>

                    <button
                        type="button"
                        wire:click="sortBy('price')"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition
                            {{ $sortBy === 'price'
                                ? 'bg-[#05645b] text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                    >
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Price</span>

                        @if($sortBy === 'price')
                            <i class="fas {{ $sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                        @endif
                    </button>

                    <button
                        type="button"
                        wire:click="sortBy('duration_days')"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition
                            {{ $sortBy === 'duration_days'
                                ? 'bg-[#05645b] text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                    >
                        <i class="fas fa-clock"></i>
                        <span>Duration</span>

                        @if($sortBy === 'duration_days')
                            <i class="fas {{ $sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                        @endif
                    </button>

                    <button
                        type="button"
                        wire:click="sortBy('display_order')"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition
                            {{ $sortBy === 'display_order'
                                ? 'bg-[#05645b] text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                    >
                        <i class="fas fa-sort-numeric-down"></i>
                        <span>Order</span>

                        @if($sortBy === 'display_order')
                            <i class="fas {{ $sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Plans --}}
    @if($plans->isNotEmpty())
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach($plans as $plan)
                <div
                    wire:key="plan-{{ $plan->id }}"
                    class="relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="h-1 bg-[#05645b]"></div>

                    <div class="flex flex-1 flex-col p-6">
                        {{-- Plan Header --}}
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-[#05645b]">
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

                                <h2 class="text-xl font-bold text-slate-900">
                                    {{ $plan->name }}
                                </h2>

                                @if($plan->description)
                                    <p class="mt-2 text-sm leading-6 text-slate-500">
                                        {{ $plan->description }}
                                    </p>
                                @endif
                            </div>

                            @if($plan->type)
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    <i class="fas fa-tag mr-1"></i>
                                    {{ ucfirst(str_replace('_', ' ', $plan->type)) }}
                                </span>
                            @endif
                        </div>

                        {{-- Price --}}
                        <div class="mt-6 rounded-xl bg-slate-50 p-4">
                            <div class="flex items-baseline gap-2">
                                <span class="text-sm font-semibold text-slate-500">
                                    {{ $plan->currency }}
                                </span>

                                <span class="text-3xl font-bold text-slate-900">
                                    {{ number_format((float) $plan->price, 0) }}
                                </span>
                            </div>

                            <div class="mt-1 flex items-center gap-2 text-sm text-slate-500">
                                <i class="fas fa-clock text-xs"></i>

                                @if($plan->duration_days)
                                    <span>
                                        Valid for {{ $plan->duration_days }} days
                                    </span>
                                @else
                                    <span>Lifetime access</span>
                                @endif
                            </div>
                        </div>

                        {{-- Features --}}
                        @if($plan->features && $plan->features->isNotEmpty())
                            <div class="mt-6 border-t border-slate-100 pt-5">
                                <p class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                                    <i class="fas fa-list-check"></i>
                                    <span>Features</span>
                                </p>

                                <div class="space-y-3">
                                    @foreach($plan->features->take(6) as $feature)
                                        <div class="flex items-start gap-3 text-sm text-slate-600">
                                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-[#05645b]">
                                                <i class="fas fa-check text-[10px]"></i>
                                            </span>

                                            <span>
                                                {{ $feature->name }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>

                                @if($plan->features->count() > 6)
                                    <p class="mt-3 text-xs font-medium text-[#05645b]">
                                        <i class="fas fa-plus-circle mr-1"></i>
                                        {{ $plan->features->count() - 6 }} more features
                                    </p>
                                @endif
                            </div>
                        @endif

                        {{-- Limits --}}
                        <div class="mt-6 grid grid-cols-2 gap-3 border-t border-slate-100 pt-5">
                            @if(! is_null($plan->max_projects))
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-xs text-slate-400">
                                        <i class="fas fa-folder-open mr-1"></i>
                                        Projects
                                    </p>

                                    <p class="mt-1 font-bold text-slate-800">
                                        {{ $plan->max_projects }}
                                    </p>
                                </div>
                            @endif

                            @if(! is_null($plan->max_boqs))
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-xs text-slate-400">
                                        <i class="fas fa-file-invoice-dollar mr-1"></i>
                                        BOQs
                                    </p>

                                    <p class="mt-1 font-bold text-slate-800">
                                        {{ $plan->max_boqs }}
                                    </p>
                                </div>
                            @endif

                            @if(! is_null($plan->max_ai_credits))
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-xs text-slate-400">
                                        <i class="fas fa-robot mr-1"></i>
                                        AI Credits
                                    </p>

                                    <p class="mt-1 font-bold text-slate-800">
                                        {{ $plan->max_ai_credits }}
                                    </p>
                                </div>
                            @endif

                            @if(! is_null($plan->max_ocr_pages))
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-xs text-slate-400">
                                        <i class="fas fa-file-image mr-1"></i>
                                        OCR Pages
                                    </p>

                                    <p class="mt-1 font-bold text-slate-800">
                                        {{ $plan->max_ocr_pages }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Trial --}}
                        @if($plan->has_trial && $plan->trial_days > 0)
                            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                <i class="fas fa-gift mr-2"></i>
                                Includes {{ $plan->trial_days }}-day free trial
                            </div>
                        @endif

                        {{-- CTA --}}
                        <div class="mt-auto pt-6">
                            <a
                                href="{{ route('subscriptions.index') }}"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#05645b] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#044f48]"
                            >
                                <i class="fas fa-check-circle"></i>
                                <span>Choose Plan</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($plans->hasPages())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="mb-3 flex items-center gap-2 text-sm text-slate-500">
                    <i class="fas fa-list"></i>

                    <span>
                        Showing {{ $plans->firstItem() }}
                        to {{ $plans->lastItem() }}
                        of {{ $plans->total() }} plans
                    </span>
                </div>

                {{ $plans->links() }}
            </div>
        @endif
    @else
        {{-- Empty State --}}
        <div class="rounded-2xl border border-slate-200 bg-white py-14 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <i class="fas fa-layer-group text-xl"></i>
            </div>

            <h3 class="mt-4 text-base font-semibold text-slate-900">
                No plans found
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                @if($search !== '')
                    No available plans match "{{ $search }}".
                @else
                    There are currently no available subscription plans.
                @endif
            </p>

            @if($search !== '')
                <button
                    type="button"
                    wire:click="$set('search', '')"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    <i class="fas fa-times"></i>
                    <span>Clear Search</span>
                </button>
            @endif
        </div>
    @endif
</div>