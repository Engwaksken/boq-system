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

    @php
        // Trend of the monthly average, oldest -> newest, months with recordings only.
        $trend = $monthly->filter(fn ($row) => $row['average'] !== null)->reverse()->values();
        $chart = null;
        if ($trend->count() >= 2) {
            $w = 640; $h = 180; $padL = 8; $padR = 8; $padT = 16; $padB = 28;
            $min = $trend->min('average'); $max = $trend->max('average');
            $span = ($max - $min) ?: max(abs($max) * 0.1, 1);
            $lo = $min - $span * 0.15; $hi = $max + $span * 0.15;
            $step = ($w - $padL - $padR) / max($trend->count() - 1, 1);
            $points = $trend->map(fn ($row, $i) => [
                'x' => round($padL + $i * $step, 1),
                'y' => round($padT + ($hi - $row['average']) / ($hi - $lo) * ($h - $padT - $padB), 1),
                'row' => $row,
            ]);
            $chart = compact('w', 'h', 'padT', 'padB', 'points');
        }
        $latestWithData = $monthly->first(fn ($row) => $row['average'] !== null);
        $signed = fn ($value, $decimals) => ($value > 0 ? '+' : '').\App\Support\Format::number($value, $decimals);
    @endphp

    <x-ui.card :title="__('Price History')" icon="fa-clock-rotate-left" :padded="false">
        <div class="flex flex-wrap items-end gap-3 border-b border-slate-200 p-4">
            <x-ui.field :label="__('View')" for="history-view">
                <select id="history-view" wire:model.live="historyView" class="boq-field">
                    <option value="monthly">{{ __('Monthly summary') }}</option>
                    <option value="records">{{ __('All recorded prices') }}</option>
                </select>
            </x-ui.field>
            @if($historyView === 'monthly')
                <x-ui.field :label="__('Period')" for="history-months">
                    <select id="history-months" wire:model.live="months" class="boq-field">
                        @foreach($monthOptions as $option)
                            <option value="{{ $option }}">{{ __('Last :count months', ['count' => $option]) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            @endif
        </div>

        @if($historyView === 'monthly')
            @if($trend->isEmpty())
                <x-ui.empty-state icon="fa-calendar-days" :title="__('No prices recorded in this period.')" :description="__('Monthly changes appear here once prices are recorded.')" />
            @else
                @if($latestWithData && $latestWithData['change'] !== null)
                    <p class="px-4 pt-4 text-sm text-slate-600">
                        {{ __('Average price in :month', ['month' => $latestWithData['month']->translatedFormat('F Y')]) }}:
                        <strong class="text-slate-900">{{ \App\Support\Format::money($latestWithData['average'], $price->currency) }}</strong>,
                        @if($latestWithData['change'] == 0)
                            {{ __('unchanged from the previous month.') }}
                        @else
                            {{ $latestWithData['change'] > 0 ? __('up') : __('down') }}
                            {{ \App\Support\Format::money(abs($latestWithData['change']), $price->currency) }}
                            @if($latestWithData['change_percent'] !== null)
                                ({{ \App\Support\Format::number(abs($latestWithData['change_percent']), 1) }}%)
                            @endif
                            {{ __('from the previous month.') }}
                        @endif
                    </p>
                @endif

                @if($chart)
                    <figure class="px-4 pt-4">
                        <figcaption class="text-xs font-semibold text-slate-500">{{ __('Monthly average price') }} ({{ $price->currency }})</figcaption>
                        <svg viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}" class="mt-2 h-auto w-full" role="img" aria-label="{{ __('Monthly average price trend') }}">
                            <line x1="0" x2="{{ $chart['w'] }}" y1="{{ $chart['h'] - $chart['padB'] }}" y2="{{ $chart['h'] - $chart['padB'] }}" style="stroke: var(--boq-border)" stroke-width="1" />
                            <polyline
                                fill="none"
                                style="stroke: var(--boq-primary)"
                                stroke-width="2"
                                stroke-linejoin="round"
                                stroke-linecap="round"
                                points="{{ $chart['points']->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}"
                            />
                            @foreach($chart['points'] as $i => $point)
                                @php
                                    $last = $chart['points']->count() - 1;
                                    $anchor = $i === 0 ? 'start' : ($i === $last ? 'end' : 'middle');
                                    $tip = $point['row']['month']->translatedFormat('M Y').': '.\App\Support\Format::money($point['row']['average'], $price->currency)
                                        .($point['row']['change_percent'] !== null ? ' ('.$signed($point['row']['change_percent'], 1).'%)' : '');
                                @endphp
                                <g>
                                    <title>{{ $tip }}</title>
                                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="12" fill="transparent" />
                                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" style="fill: var(--boq-primary); stroke: var(--boq-surface)" stroke-width="2" />
                                </g>
                                @if($i === 0 || $i === $last || $chart['points']->count() <= 12)
                                    <text x="{{ $point['x'] }}" y="{{ $chart['h'] - 8 }}" text-anchor="{{ $anchor }}" font-size="11" style="fill: var(--boq-text-muted)">{{ $point['row']['month']->translatedFormat('M y') }}</text>
                                @endif
                            @endforeach
                        </svg>
                    </figure>
                @endif

                <x-ui.table>
                    <thead>
                        <tr>
                            <th>{{ __('Month') }}</th>
                            <th class="text-right">{{ __('Records') }}</th>
                            <th class="text-right">{{ __('Lowest') }}</th>
                            <th class="text-right">{{ __('Highest') }}</th>
                            <th class="text-right">{{ __('Average') }}</th>
                            <th class="text-right">{{ __('Month-end') }}</th>
                            <th class="text-right">{{ __('Change vs previous month') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthly as $row)
                            <tr wire:key="month-{{ $row['month']->format('Y-m') }}">
                                <td class="whitespace-nowrap font-medium text-slate-900">{{ $row['month']->translatedFormat('F Y') }}</td>
                                @if($row['records'] === 0)
                                    <td class="is-numeric text-slate-400">0</td>
                                    <td colspan="5" class="text-slate-400">{{ __('No prices recorded') }}</td>
                                @else
                                    <td class="is-numeric">{{ \App\Support\Format::number($row['records'], 0) }}</td>
                                    <td class="is-numeric">{{ $amount($row['low']) }}</td>
                                    <td class="is-numeric">{{ $amount($row['high']) }}</td>
                                    <td class="is-numeric font-semibold text-slate-900">{{ $amount($row['average']) }}</td>
                                    <td class="is-numeric">{{ $amount($row['closing']) }}</td>
                                    <td class="is-numeric whitespace-nowrap">
                                        @if($row['change'] === null)
                                            <span class="text-slate-400">—</span>
                                        @elseif($row['change'] == 0)
                                            <span class="text-slate-500">{{ __('No change') }}</span>
                                        @else
                                            <span class="{{ $row['change'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                <i class="fas {{ $row['change'] > 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}" aria-hidden="true"></i>
                                                {{ $signed($row['change'], 2) }}
                                                @if($row['change_percent'] !== null)
                                                    ({{ $signed($row['change_percent'], 1) }}%)
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        @elseif($price->priceHistories->isNotEmpty())
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
