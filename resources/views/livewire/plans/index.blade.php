@php
    $sortOptions = [
        'display_order' => [__('Recommended'), 'fa-star'],
        'price' => [__('Price'), 'fa-money-bill-wave'],
        'duration_days' => [__('Duration'), 'fa-clock'],
        'name' => [__('Name'), 'fa-font'],
    ];
    $typeIcons = ['monthly' => 'fa-calendar-days', 'quarterly' => 'fa-calendar-week', 'six_month' => 'fa-calendar-week', 'annual' => 'fa-calendar-check', 'lifetime' => 'fa-infinity'];
    $currentPlanId ??= null;
@endphp

<div class="boq-page-stack">

    <x-ui.page-header
        :title="__('Plans & Pricing')"
        icon="fa-layer-group"
        :subtitle="__('Choose the plan that fits your BOQ workflow.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-gift" :href="route('topups.index')">{{ __('Top-ups') }}</x-ui.button>
            <x-ui.button icon="fa-credit-card" :href="route('subscriptions.index')">{{ __('My Subscription') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="boq-plans-stats-grid">
        <x-stat-card :label="__('Available Plans')" :value="\App\Support\Format::number($stats['available_plans'] ?? 0, 0)" icon="fa-layer-group" color="green" />
        <x-stat-card :label="__('Monthly Plans')" :value="\App\Support\Format::number($stats['monthly_plans'] ?? 0, 0)" icon="fa-calendar-days" color="blue" />
        <x-stat-card :label="__('Annual Plans')" :value="\App\Support\Format::number($stats['annual_plans'] ?? 0, 0)" icon="fa-calendar-check" color="amber" />
    </div>

    <div class="boq-panel">
        <div class="boq-toolbar">
            <x-ui.field :label="__('Search Plans')" for="plan-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input
                        id="plan-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="{{ __('Search by plan name...') }}"
                    >
                </div>
            </x-ui.field>

            <div class="boq-field-group">
                <span class="boq-field-label">{{ __('Sort By') }}</span>
                <div class="boq-segmented">
                    @foreach($sortOptions as $field => [$label, $icon])
                        <button type="button" wire:click="sortBy('{{ $field }}')" class="{{ $sortBy === $field ? 'is-active' : '' }}" aria-pressed="{{ $sortBy === $field ? 'true' : 'false' }}">
                            <i class="fas {{ $icon }} text-[11px]" aria-hidden="true"></i>
                            {{ $label }}
                            @if($sortBy === $field)
                                <i class="fas {{ $sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[10px]" aria-hidden="true"></i>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <x-ui.field :label="__('Rows')" for="plan-rows" class="w-full sm:w-24">
                <select id="plan-rows" wire:model.live="perPage" class="boq-field">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>
    </div>

    @if($plans->isNotEmpty())
        <div class="boq-plan-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" wire:loading.class="opacity-60" wire:target="search, sortBy, perPage">
            @foreach($plans as $plan)
                @php $isCurrent = $currentPlanId && (int) $currentPlanId === (int) $plan->id; @endphp

                <article wire:key="plan-{{ $plan->id }}" @class(['boq-plan-card', 'is-current' => $isCurrent])>
                    <div class="boq-plan-header">
                        <div class="boq-plan-heading-copy">
                            <span class="boq-plan-main-icon"><i class="fas {{ $typeIcons[$plan->type] ?? 'fa-box-open' }}" aria-hidden="true"></i></span>
                            <h2 class="boq-plan-name">{{ $plan->name }}</h2>
                            @if($plan->description)
                                <p class="boq-plan-description">{{ $plan->description }}</p>
                            @endif
                        </div>

                        @if($isCurrent)
                            <x-ui.badge color="success" icon="fa-circle-check">{{ __('Current plan') }}</x-ui.badge>
                        @else
                            <span class="boq-plan-type">{{ __(\Illuminate\Support\Str::headline($plan->type ?? 'Plan')) }}</span>
                        @endif
                    </div>

                    <div class="boq-plan-price">
                        <span class="boq-plan-currency">{{ $plan->currency ?? \App\Support\Regional::currency() }}</span>
                        <span class="boq-plan-price-value">{{ \App\Support\Format::number((float) $plan->price, 0) }}</span>
                    </div>

                    <div class="boq-plan-duration">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        @if($plan->duration_hours)
                            <span>{{ trans_choice(':count hour|:count hours', (int) $plan->duration_hours, ['count' => \App\Support\Format::number($plan->duration_hours, 0)]) }}</span>
                        @elseif($plan->duration_days)
                            <span>{{ trans_choice(':count day|:count days', (int) $plan->duration_days, ['count' => \App\Support\Format::number($plan->duration_days, 0)]) }}</span>
                        @else
                            <span>{{ __('Lifetime access') }}</span>
                        @endif
                    </div>

                    <div class="boq-plan-limits">
                        <div class="boq-plan-limits-grid">
                            <div class="boq-plan-limit">
                                <div class="boq-plan-limit-label"><i class="fas fa-folder-open" aria-hidden="true"></i> {{ __('Projects') }}</div>
                                <div class="boq-plan-limit-value">{{ $plan->max_projects !== null ? \App\Support\Format::number($plan->max_projects, 0) : '∞' }}</div>
                            </div>
                            <div class="boq-plan-limit">
                                <div class="boq-plan-limit-label"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> {{ __('BOQs') }}</div>
                                <div class="boq-plan-limit-value">{{ $plan->max_boqs !== null ? \App\Support\Format::number($plan->max_boqs, 0) : '∞' }}</div>
                            </div>
                        </div>
                    </div>

                    @if($plan->features && $plan->features->isNotEmpty())
                        @php
                            $features = $plan->features;
                            $visibleFeatures = 4;
                            $hiddenFeatureCount = max(0, $features->count() - $visibleFeatures);
                        @endphp
                        <div class="boq-plan-features" x-data="{ showAllFeatures: false }">
                            <p class="boq-plan-features-title">{{ __('Features') }}</p>

                            <ul class="boq-plan-feature-list">
                                @foreach($features as $index => $feature)
                                    <li class="boq-plan-feature" @if($index >= $visibleFeatures) x-show="showAllFeatures" x-cloak @endif>
                                        <span class="boq-plan-feature-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                                        <span>{{ $feature->name }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @if($hiddenFeatureCount > 0)
                                <button
                                    type="button"
                                    class="boq-plan-more-features"
                                    x-on:click="showAllFeatures = ! showAllFeatures"
                                    x-bind:aria-expanded="showAllFeatures.toString()"
                                >
                                    <span x-show="! showAllFeatures">{{ __('Show all :count features', ['count' => $features->count()]) }}</span>
                                    <span x-show="showAllFeatures" x-cloak>{{ __('Show less') }}</span>
                                    <i class="fas fa-chevron-down" x-bind:class="showAllFeatures ? 'is-open' : ''" aria-hidden="true"></i>
                                </button>
                            @endif
                        </div>
                    @endif

                    @if(($plan->has_trial ?? false) && ($plan->trial_days ?? 0) > 0)
                        <div class="boq-plan-trial">
                            <i class="fas fa-gift" aria-hidden="true"></i>
                            {{ __(':days-day free trial', ['days' => $plan->trial_days]) }}
                        </div>
                    @endif

                    <div class="boq-plan-action">
                        @if($isCurrent)
                            <span class="boq-current-plan-button"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('Current plan') }}</span>
                        @else
                            <a href="{{ route('subscriptions.index', ['tab' => 'plans']) }}" class="boq-plan-choose-button">
                                <i class="fas fa-check-circle" aria-hidden="true"></i>
                                {{ __('Choose Plan') }}
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($plans->hasPages())
            <div class="boq-panel boq-pagination">
                {{ $plans->links() }}
            </div>
        @endif
    @else
        <x-ui.card>
            <x-ui.empty-state
                icon="fa-layer-group"
                :title="__('No plans found')"
                :description="__('No subscription plans match your search.')"
            >
                @if($search !== '')
                    <x-ui.button variant="secondary" size="sm" wire:click="$set('search', '')">{{ __('Clear search') }}</x-ui.button>
                @endif
            </x-ui.empty-state>
        </x-ui.card>
    @endif
</div>
