@php
    $signed = fn ($value, $decimals = 2) => ((float) $value > 0 ? '+' : '').\App\Support\Format::number((float) $value, $decimals);
    $hasFilters = $search !== '' || $period !== '30' || $direction !== '' || $category !== '' || $source !== '' || $sort !== 'recent';
    $periods = ['7' => __('Last 7 days'), '30' => __('Last 30 days'), '90' => __('Last 90 days'), '365' => __('Last 12 months'), 'all' => __('All time')];
@endphp

<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Price Changes')"
        icon="fa-arrow-trend-up"
        :subtitle="__('Latest price change of every item, general market prices and your organisation\'s own. Open an item for its full monthly history.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-tags" :href="route('hardware-prices.index')">{{ __('Get Prices') }}</x-ui.button>
            <x-ui.button variant="secondary" icon="fa-scale-balanced" :href="route('hardware-prices.compare')">{{ __('Compare') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="boq-stats-grid">
        <x-stat-card
            :label="__('Items changed')"
            :value="\App\Support\Format::number($stats['total'], 0)"
            :hint="$periods[$period] ?? null"
            icon="fa-arrows-rotate"
            color="blue"
            :active="$direction === ''"
            wire:click="$set('direction', '')"
        />
        <x-stat-card
            :label="__('Went up')"
            :value="\App\Support\Format::number($stats['up'], 0)"
            icon="fa-arrow-up"
            color="red"
            :active="$direction === 'up'"
            wire:click="$set('direction', 'up')"
        />
        <x-stat-card
            :label="__('Went down')"
            :value="\App\Support\Format::number($stats['down'], 0)"
            icon="fa-arrow-down"
            color="green"
            :active="$direction === 'down'"
            wire:click="$set('direction', 'down')"
        />
        <x-stat-card
            :label="__('Average change')"
            :value="$stats['average'] !== null ? $signed($stats['average'], 1).'%' : '—'"
            icon="fa-percent"
            :color="(float) $stats['average'] > 0 ? 'red' : 'green'"
        />
    </div>

    <div class="boq-panel">
        <div class="boq-card-body">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field :label="__('Search')" for="changes-search">
                    <div class="boq-input-icon-wrap">
                        <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                        <input id="changes-search" type="search" wire:model.live.debounce.300ms="search" class="boq-field boq-field-with-icon" placeholder="{{ __('Search item, brand, specification or supplier...') }}">
                    </div>
                </x-ui.field>

                <x-ui.field :label="__('Period')" for="changes-period">
                    <select id="changes-period" wire:model.live="period" class="boq-field">
                        @foreach($periods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Category')" for="changes-category">
                    <select id="changes-category" wire:model.live="category" class="boq-field">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Sort By')" for="changes-sort">
                    <select id="changes-sort" wire:model.live="sort" class="boq-field">
                        <option value="recent">{{ __('Most recent change') }}</option>
                        <option value="up">{{ __('Biggest increase') }}</option>
                        <option value="down">{{ __('Biggest decrease') }}</option>
                    </select>
                </x-ui.field>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                @if($hasOwnPrices)
                    <div class="boq-segmented" role="radiogroup" aria-label="{{ __('Price source') }}">
                        @foreach(['' => __('All prices'), 'general' => __('General market'), 'mine' => __('My organisation')] as $value => $label)
                            <label class="{{ $source === $value ? 'is-active' : '' }} cursor-pointer">
                                <input type="radio" wire:model.live="source" value="{{ $value }}" class="sr-only">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                @endif

                @if($hasFilters)
                    <button type="button" wire:click="clearFilters" class="boq-btn-ghost boq-btn-sm">
                        <i class="fas fa-filter-circle-xmark" aria-hidden="true"></i> {{ __('Clear filters') }}
                    </button>
                @endif
            </div>
        </div>

        <div class="boq-loading-bar" wire:loading.delay wire:target="search, period, direction, category, source, sort, clearFilters, gotoPage, nextPage, previousPage"></div>

        <x-ui.table>
            <thead>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Supplier / Location') }}</th>
                    <th class="text-right">{{ __('Previous price') }}</th>
                    <th class="text-right">{{ __('Current price') }}</th>
                    <th class="text-right">{{ __('Change') }}</th>
                    <th>{{ __('Changed') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($changes as $price)
                    @php($up = (float) $price->price_change > 0)
                    <tr wire:key="change-{{ $price->id }}">
                        <td class="min-w-[12rem]">
                            <a href="{{ route('hardware-prices.show', $price) }}" class="boq-table-link">{{ $price->item_name }}</a>
                            @if($price->brand)
                                <div class="boq-table-subtitle">{{ $price->brand }}</div>
                            @endif
                            <div class="mt-1">
                                @if($price->isGeneral())
                                    <x-ui.badge icon="fa-globe">{{ __('General') }}</x-ui.badge>
                                @else
                                    <x-ui.badge color="brand" icon="fa-building">{{ __('My organisation') }}</x-ui.badge>
                                @endif
                            </div>
                        </td>
                        <td>{{ $price->category ?: '—' }}</td>
                        <td>
                            <div>{{ $price->supplier ?: '—' }}</div>
                            @if($price->location)
                                <div class="boq-table-meta">{{ $price->location }}</div>
                            @endif
                        </td>
                        <td class="is-numeric whitespace-nowrap text-slate-500"><x-money :amount="$price->previous_price" :currency="$price->currency" /></td>
                        <td class="is-numeric whitespace-nowrap font-semibold text-slate-900">
                            <x-money :amount="$price->price" :currency="$price->currency" />
                            @if($price->unit)
                                <div class="boq-table-meta">{{ __('per :unit', ['unit' => $price->unit]) }}</div>
                            @endif
                        </td>
                        <td class="is-numeric whitespace-nowrap {{ $up ? 'text-red-600' : 'text-green-600' }}">
                            <i class="fas {{ $up ? 'fa-arrow-up' : 'fa-arrow-down' }}" aria-hidden="true"></i>
                            {{ $signed($price->price_change) }}
                            @if($price->price_change_percent !== null)
                                <div class="text-xs">({{ $signed($price->price_change_percent, 1) }}%)</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if($price->price_changed_at)
                                <x-date :value="$price->price_changed_at" />
                                <div class="boq-table-meta">{{ $price->price_changed_at->diffForHumans() }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">
                            <x-ui.button size="sm" variant="secondary" icon="fa-chart-line" :href="route('hardware-prices.show', $price)">{{ __('History') }}</x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-ui.empty-state
                                icon="fa-arrow-trend-up"
                                :title="__('No price changes found.')"
                                :description="$hasFilters ? __('Try a longer period or clear the filters.') : __('Price changes appear here when the price scanner or your team updates a price.')"
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($changes->hasPages())
            <div class="boq-pagination">
                {{ $changes->links() }}
            </div>
        @endif
    </div>
</div>
