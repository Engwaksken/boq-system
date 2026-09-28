@php
    $price = $hardwarePrice;
    $isFactory = $price->price_type === 'factory';
    $priceChange = $price->price_change;
    $priceChangePercent = $price->price_change_percent;
    $amount = fn ($value) => $value !== null ? \App\Support\Format::money($value, $price->currency) : '—';
    $details = [
        __('Brand') => $price->brand,
        __('Specification') => $price->specification,
        __('Supplier') => $price->supplier,
        __('Location') => $price->location,
        __('Source Reference') => $price->source_reference,
        __('Fetched At') => \App\Support\Format::date($price->fetched_at, true),
    ];
@endphp

<div class="boq-page-stack">
    <x-ui.page-header :title="$price->item_name" icon="fa-tag">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-ui.badge :color="$isFactory ? 'purple' : 'info'" :icon="$isFactory ? 'fa-industry' : 'fa-store'">
                {{ $isFactory ? __('Factory') : __('Hardware') }}
            </x-ui.badge>
            @if($price->category)
                <x-ui.badge>{{ $price->category }}</x-ui.badge>
            @endif
            @unless($price->is_active)
                <x-ui.badge color="danger">{{ __('Inactive') }}</x-ui.badge>
            @endunless
        </div>

        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="route('hardware-prices.index')">{{ __('Back') }}</x-ui.button>
            <x-ui.button variant="secondary" icon="fa-scale-balanced" :href="route('hardware-prices.compare')">{{ __('Compare') }}</x-ui.button>
            @if($price->source_url)
                <x-ui.button variant="ghost" icon="fa-arrow-up-right-from-square" :href="$price->source_url" target="_blank" rel="noopener noreferrer">{{ __('View source') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-5 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-1">
            <p class="text-xs font-semibold text-slate-500">{{ __('Current price') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-slate-900"><x-money :amount="$price->price" :currency="$price->currency" /></p>
            @if($price->unit)
                <p class="mt-1 text-sm text-slate-500">{{ __('per :unit', ['unit' => $price->unit]) }}</p>
            @endif
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" :title="__('Details')" icon="fa-circle-info">
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($details as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold text-slate-500">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm text-slate-900 [overflow-wrap:anywhere]">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </div>

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Lowest Price')" :value="$amount($price->lowest_price)" icon="fa-arrow-down" color="green" />
        <x-stat-card :label="__('Highest Price')" :value="$amount($price->highest_price)" icon="fa-arrow-up" color="red" />
        <x-stat-card :label="__('Average Price')" :value="$amount($price->average_price)" icon="fa-scale-balanced" color="blue" />
        <x-stat-card
            :label="__('Price Change')"
            :value="$priceChange !== null ? (((float) $priceChange > 0 ? '+' : '').\App\Support\Format::number((float) $priceChange, 2)) : '—'"
            :hint="$priceChangePercent !== null ? \App\Support\Format::number((float) $priceChangePercent, 2).'%' : null"
            icon="fa-chart-line"
            :color="$priceChange !== null && (float) $priceChange < 0 ? 'green' : 'amber'"
        />
    </div>

    <x-ui.card :title="__('Price History')" icon="fa-clock-rotate-left" :padded="false">
        @if($price->priceHistories->isNotEmpty())
            <x-ui.table>
                <thead>
                    <tr>
                        <th>{{ __('Recorded At') }}</th>
                        <th class="text-right">{{ __('Price') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Location') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($price->priceHistories as $history)
                        <tr wire:key="history-{{ $history->id }}">
                            <td class="whitespace-nowrap"><x-date :value="$history->recorded_at" time /></td>
                            <td class="is-numeric font-semibold text-slate-900"><x-money :amount="$history->price" :currency="$history->currency" /></td>
                            <td>{{ $history->supplier ?: '—' }}</td>
                            <td>{{ $history->location ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @else
            <x-ui.empty-state icon="fa-clock-rotate-left" :title="__('No price history recorded yet.')" :description="__('Changes to this price are recorded here automatically.')" />
        @endif
    </x-ui.card>
</div>
