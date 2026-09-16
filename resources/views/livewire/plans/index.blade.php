<div class="boq-page-stack">

    {{-- =====================================================
         HEADER
    ====================================================== --}}
    <div class="boq-page-header">

        <div>

            <h1 class="boq-page-title">
                <i class="fas fa-layer-group"></i>
                Plans & Pricing
            </h1>

            <p class="boq-page-subtitle">
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


    {{-- =====================================================
         STATISTICS
    ====================================================== --}}
    <div class="boq-plans-stats-grid">

        <div class="boq-stat-card boq-stat-green">

            <div>
                <p class="boq-stat-label">
                    Available Plans
                </p>

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
                <p class="boq-stat-label">
                    Monthly Plans
                </p>

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
                <p class="boq-stat-label">
                    Annual Plans
                </p>

                <p class="boq-stat-value">
                    {{ $stats['annual_plans'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-calendar-check"></i>
            </span>

        </div>

    </div>


    {{-- =====================================================
         FILTERS
    ====================================================== --}}
    <div class="boq-panel">

        <div class="boq-plans-index-filter">

            {{-- Search --}}
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
                        'price' => [
                            'label' => 'Price',
                            'icon' => 'fa-money-bill-wave',
                        ],
                        'duration_days' => [
                            'label' => 'Duration',
                            'icon' => 'fa-clock',
                        ],
                        'display_order' => [
                            'label' => 'Order',
                            'icon' => 'fa-sort-numeric-down',
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
         PLANS
    ====================================================== --}}
    @if($plans->isNotEmpty())

        <div class="boq-plan-grid">

            @foreach($plans as $plan)

                <article
                    wire:key="plan-{{ $plan->id }}"
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

                            <h2 class="boq-plan-name">
                                {{ $plan->name }}
                            </h2>

                            @if($plan->description)

                                <p class="boq-plan-description">
                                    {{ $plan->description }}
                                </p>

                            @endif

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


                    {{-- =================================================
                         PROJECTS / BOQs / AI
                         ONE HORIZONTAL CARD
                    ================================================== --}}
                    <div class="boq-plan-limits">

                        <div class="boq-plan-limits-grid">

                            <div class="boq-plan-limit">

                                <div class="boq-plan-limit-label">
                                    <i class="fas fa-folder-open"></i>
                                    Projects
                                </div>

                                <div class="boq-plan-limit-value">
                                    {{ $plan->max_projects ?? '∞' }}
                                </div>

                            </div>


                            <div class="boq-plan-limit">

                                <div class="boq-plan-limit-label">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    BOQs
                                </div>

                                <div class="boq-plan-limit-value">
                                    {{ $plan->max_boqs ?? '∞' }}
                                </div>

                            </div>


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


                    {{-- =================================================
                         FEATURES
                    ================================================== --}}
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


                    {{-- =================================================
                         TRIAL
                    ================================================== --}}
                    @if(
                        ($plan->has_trial ?? false)
                        && ($plan->trial_days ?? 0) > 0
                    )

                        <div class="boq-plan-trial">

                            <i class="fas fa-gift"></i>

                            {{ $plan->trial_days }}-day free trial

                        </div>

                    @endif


                    {{-- =================================================
                         ACTION
                    ================================================== --}}
                    <div class="boq-plan-action">

                        <a
                            href="{{ route('subscriptions.index') }}"
                            class="boq-plan-choose-button"
                        >
                            <i class="fas fa-check-circle"></i>
                            Choose Plan
                        </a>

                    </div>

                </article>

            @endforeach

        </div>


        {{-- Pagination --}}
        @if($plans->hasPages())

            <div class="boq-panel boq-pagination">
                {{ $plans->links() }}
            </div>

        @endif


    @else

        {{-- =================================================
             EMPTY STATE
        ================================================== --}}
        <div class="boq-panel boq-empty-state">

            <span class="boq-empty-icon">
                <i class="fas fa-layer-group"></i>
            </span>

            <h3 class="boq-empty-title">
                No plans found
            </h3>

            <p class="boq-empty-description">
                No subscription plans match your search.
            </p>

        </div>

    @endif

</div>
