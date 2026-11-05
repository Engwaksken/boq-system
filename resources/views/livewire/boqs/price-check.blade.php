@php
    $currency = $boq->currency;
    $signed = fn ($value, $decimals = 2) => ($value > 0 ? '+' : '').\App\Support\Format::number($value, $decimals);
    $gapClass = fn ($value) => $value === null || abs($value) < 0.005 ? 'text-slate-500' : ($value > 0 ? 'text-red-600' : 'text-green-600');
    $canViewMarket = auth()->user()->hasPermission('hardware-prices.view');
    $filters = [
        'all' => [__('All matched'), $summary['matched']],
        'above' => [__('Our rate above market'), $summary['above']],
        'below' => [__('Our rate below market'), $summary['below']],
        'changed' => [__('Market changed since matching'), $summary['changed']],
    ];
@endphp

<div class="space-y-4">
    <section class="boq-panel">
        <div class="boq-card-header">
            <div class="min-w-0">
                <h2 class="boq-card-title"><i class="fas fa-scale-unbalanced" aria-hidden="true"></i> {{ __('Price check') }}</h2>
                <p class="boq-card-subtitle">{{ __('Each matched item\'s rate in this BOQ against today\'s market price. Our rate is the approved rate, or the latest rate set before approval.') }}</p>
            </div>
        </div>

        <div class="boq-stats-grid p-4">
            <x-stat-card :label="__('BOQ total at our rates')" :value="\App\Support\Format::money($summary['total_ours'], $currency)" icon="fa-file-invoice-dollar" color="blue" />
            <x-stat-card :label="__('Same items at today\'s market')" :value="\App\Support\Format::money($summary['total_market'], $currency)" icon="fa-store" color="purple" />
            <x-stat-card
                :label="__('Market vs our rates')"
                :value="($summary['total_difference'] > 0 ? '+' : '').\App\Support\Format::money($summary['total_difference'], $currency)"
                :hint="$summary['total_percent'] !== null ? $signed($summary['total_percent'], 1).'%' : null"
                icon="fa-chart-line"
                :color="$summary['total_difference'] > 0 ? 'red' : 'green'"
            />
            <x-stat-card :label="__('Matched items')" :value="\App\Support\Format::number($summary['matched'], 0)" :hint="$summary['unmatched'] ? __(':count not matched', ['count' => $summary['unmatched']]) : null" icon="fa-link" color="amber" />
        </div>

        @if($summary['unmatched'] > 0)
            <p class="px-4 pb-2 text-xs text-slate-500">
                {{ __(':count item(s) are not matched to a market price, so they are not compared. Match them from Items & pricing.', ['count' => $summary['unmatched']]) }}
            </p>
        @endif

        <div class="flex flex-wrap gap-2 px-4 pb-4" role="group" aria-label="{{ __('Filter') }}">
            @foreach($filters as $key => [$label, $count])
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $key }}')"
                    @class(['boq-btn-sm', 'boq-btn-primary' => $filter === $key, 'boq-btn-secondary' => $filter !== $key])
                    aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                >{{ $label }} ({{ \App\Support\Format::number($count, 0) }})</button>
            @endforeach
        </div>

        @if($rows->isEmpty())
            <x-ui.empty-state
                icon="fa-scale-unbalanced"
                :title="$summary['matched'] === 0 ? __('No items are matched to market prices yet.') : __('No items in this view.')"
                :description="$summary['matched'] === 0 ? __('Match items to market prices under Items & pricing to compare them here.') : null"
            />
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <th>{{ __('Item') }}</th>
                        <th class="text-right">{{ __('Qty') }}</th>
                        <th class="text-right">{{ __('Our rate') }}</th>
                        <th class="text-right">{{ __('Market today') }}</th>
                        <th class="text-right">{{ __('Difference') }}</th>
                        <th class="text-right">{{ __('Since matching') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php($item = $row['item'])
                        @php($market = $row['market'])
                        <tr wire:key="price-check-{{ $item->id }}">
                            <td class="min-w-[14rem]">
                                <div class="boq-table-title">{{ $item->item_code ? $item->item_code.' · ' : '' }}{{ \Illuminate\Support\Str::limit($item->description, 80) }}</div>
                                <div class="boq-table-subtitle">
                                    {{ __('Market item') }}:
                                    @if($canViewMarket)
                                        <a href="{{ route('hardware-prices.show', $market) }}" class="boq-link">{{ $market->item_name }}</a>
                                    @else
                                        {{ $market->item_name }}
                                    @endif
                                    @if($market->supplier) · {{ $market->supplier }} @endif
                                    · {{ __('as of') }} <x-date :value="$market->fetched_at" />
                                </div>
                            </td>
                            <td class="is-numeric whitespace-nowrap">{{ \App\Support\Format::number($item->quantity, 2) }} {{ $item->unit }}</td>
                            <td class="is-numeric whitespace-nowrap">
                                @if($row['rate'] !== null)
                                    <x-money :amount="$row['rate']" :currency="$item->currency" />
                                    <div class="boq-table-subtitle">{{ __($row['rate_label']) }}</div>
                                @else
                                    <span class="text-slate-400">{{ __('No rate yet') }}</span>
                                @endif
                            </td>
                            <td class="is-numeric whitespace-nowrap font-semibold text-slate-900">
                                <x-money :amount="$market->price" :currency="$market->currency" />
                                @if($market->unit)<div class="boq-table-subtitle">{{ __('per :unit', ['unit' => $market->unit]) }}</div>@endif
                            </td>
                            <td class="is-numeric whitespace-nowrap">
                                @if(! $row['comparable'])
                                    <span class="text-slate-400" title="{{ $row['rate'] === null ? __('No rate yet') : __('Different currency') }}">—</span>
                                @else
                                    <span class="{{ $gapClass($row['difference']) }}">
                                        {{ $signed($row['difference']) }}
                                        @if($row['percent'] !== null)
                                            <div class="text-xs">({{ $signed($row['percent'], 1) }}%)</div>
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="is-numeric whitespace-nowrap">
                                @if($row['since_match'] === null)
                                    <span class="text-slate-400">—</span>
                                @elseif(abs($row['since_match']) < 0.005)
                                    <span class="text-slate-500">{{ __('No change') }}</span>
                                @else
                                    <span class="{{ $gapClass($row['since_match']) }}">
                                        <i class="fas {{ $row['since_match'] > 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}" aria-hidden="true"></i>
                                        {{ $signed($row['since_match']) }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="boq-table-actions">
                                    <x-ui.button size="sm" variant="secondary" icon="fa-clock-rotate-left" wire:click="showHistory({{ $item->id }})">{{ __('Rate history') }}</x-ui.button>
                                    @if($canViewMarket)
                                        <x-ui.button size="sm" variant="ghost" icon="fa-chart-line" :href="route('hardware-prices.show', $market)">{{ __('Market history') }}</x-ui.button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <p class="px-4 py-3 text-xs text-slate-500">
                {{ __('Difference = market price minus our rate. Red means the market is now higher than our rate (the item may be under-priced); green means the market is lower.') }}
            </p>
        @endif
    </section>

    @if($historyItem)
        <x-ui.modal
            wire:key="rate-history-modal"
            :title="__('Rate history')"
            :subtitle="\Illuminate\Support\Str::limit($historyItem->description, 90)"
            icon="fa-clock-rotate-left"
            size="lg"
            close="closeHistory"
        >
            @if($history->isEmpty())
                <x-ui.empty-state icon="fa-clock-rotate-left" :title="__('No rate changes recorded yet.')" :description="__('Changes to this item\'s original, suggested, reviewed or approved rate are recorded here.')" />
            @else
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Rate') }}</th>
                            <th class="text-right">{{ __('Old') }}</th>
                            <th class="text-right">{{ __('New') }}</th>
                            <th class="text-right">{{ __('Change') }}</th>
                            <th>{{ __('By') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $entry)
                            @php($change = $entry->old_value !== null && $entry->new_value !== null ? (float) $entry->new_value - (float) $entry->old_value : null)
                            <tr wire:key="rate-history-{{ $entry->id }}">
                                <td class="whitespace-nowrap"><x-date :value="$entry->changed_at" time /></td>
                                <td>{{ $fieldLabels[$entry->field] ?? $entry->field }}</td>
                                <td class="is-numeric">{!! $entry->old_value !== null ? e(\App\Support\Format::money($entry->old_value, $entry->currency ?: $currency)) : '<span class="text-slate-400">—</span>' !!}</td>
                                <td class="is-numeric font-semibold text-slate-900">{!! $entry->new_value !== null ? e(\App\Support\Format::money($entry->new_value, $entry->currency ?: $currency)) : '<span class="text-slate-400">—</span>' !!}</td>
                                <td class="is-numeric whitespace-nowrap">
                                    @if($change === null || abs($change) < 0.005)
                                        <span class="text-slate-400">—</span>
                                    @else
                                        <span class="{{ $gapClass($change) }}">{{ $signed($change) }}</span>
                                    @endif
                                </td>
                                <td>{{ $entry->user?->name ?? __('System') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif

            <x-slot:footer>
                @if($historyItem->hardwarePrice && $canViewMarket)
                    <x-ui.button variant="secondary" icon="fa-chart-line" :href="route('hardware-prices.show', $historyItem->hardwarePrice)">{{ __('Market history') }}</x-ui.button>
                @endif
                <button type="button" wire:click="closeHistory" class="boq-btn-secondary">{{ __('Close') }}</button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
