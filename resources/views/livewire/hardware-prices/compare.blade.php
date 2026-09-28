@php
    $fmt = fn ($value, $suffix = '') => $value !== null && $value !== '' ? \App\Support\Format::number((float) $value, 2).$suffix : '—';
    $text = fn ($value) => filled($value) ? $value : '—';
    $compareHint = __('Select items above and click "Compare Selected" to see the side-by-side comparison.');
@endphp

<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Compare Hardware Prices')"
        icon="fa-scale-balanced"
        :subtitle="__('Select 2 to 10 items to compare side by side')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="route('hardware-prices.index')">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['error', 'status', 'message']" />

    <x-ui.card :title="__('Select Items')" icon="fa-list-check">
        <x-slot:actions>
            <button type="button" wire:click="compare" wire:loading.attr="disabled" wire:target="compare" class="boq-btn-primary">
                <i class="fas fa-scale-balanced" wire:loading.remove wire:target="compare" aria-hidden="true"></i>
                <i class="fas fa-spinner fa-spin" wire:loading wire:target="compare" aria-hidden="true"></i>
                {{ __('Compare Selected') }}
                <span class="rounded-full bg-white/20 px-1.5 text-xs tabular-nums" x-data x-text="($wire.selectedIds || []).length">{{ count($selectedIds ?? []) }}</span>
            </button>
        </x-slot:actions>

        @if(isset($prices) && $prices->isNotEmpty())
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($prices as $item)
                    <label class="boq-radio-card" wire:key="compare-option-{{ $item->id }}">
                        <input type="checkbox" wire:model="selectedIds" value="{{ $item->id }}">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-900">{{ $item->item_name }}</span>
                            <span class="block text-xs text-slate-500">
                                <x-money :amount="$item->price" :currency="$item->currency" />
                                @if($item->supplier) · {{ $item->supplier }} @endif
                                @if($item->location) · {{ $item->location }} @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="fa-box-open" :title="__('No items available to compare.')" :description="__('Add hardware or factory prices first.')">
                <x-ui.button variant="secondary" size="sm" :href="route('hardware-prices.index')">{{ __('Market Prices') }}</x-ui.button>
            </x-ui.empty-state>
        @endif
    </x-ui.card>

    @if(isset($comparison) && count($comparison) > 0)
        <x-ui.card :title="__('Comparison')" icon="fa-table-columns" :padded="false">
            @php
                $rows = [
                    __('Brand') => fn ($i) => $text($i['brand'] ?? null),
                    __('Category') => fn ($i) => $text($i['category'] ?? null),
                    __('Specification') => fn ($i) => $text($i['specification'] ?? null),
                    __('Unit') => fn ($i) => $text($i['unit'] ?? null),
                    __('Supplier') => fn ($i) => $text($i['supplier'] ?? null),
                    __('Location') => fn ($i) => $text($i['location'] ?? null),
                    __('Fetched At') => fn ($i) => \App\Support\Format::date($i['fetched_at'] ?? null, true) ?? '—',
                    __('Lowest') => fn ($i) => $fmt($i['price_history']['lowest'] ?? null),
                    __('Highest') => fn ($i) => $fmt($i['price_history']['highest'] ?? null),
                    __('Average') => fn ($i) => $fmt($i['price_history']['average'] ?? null),
                    __('Change') => fn ($i) => $fmt($i['price_history']['change'] ?? null),
                    __('Change %') => fn ($i) => $fmt($i['price_history']['change_percent'] ?? null, '%'),
                ];
            @endphp

            <x-ui.table>
                <thead>
                    <tr>
                        <th class="w-40">{{ __('Attribute') }}</th>
                        @foreach($comparison as $item)
                            <th class="min-w-[11rem] normal-case tracking-normal">
                                <span class="block text-sm font-semibold text-slate-900">{{ $item['item_name'] }}</span>
                                @if(isset($summary) && is_array($summary))
                                    <span class="mt-1 flex flex-wrap gap-1">
                                        @if(($summary['best_value'] ?? null) === $item['id'])
                                            <x-ui.badge color="success" icon="fa-award">{{ __('Best Value') }}</x-ui.badge>
                                        @endif
                                        @if(($summary['lowest_price'] ?? null) === $item['id'])
                                            <x-ui.badge color="info" icon="fa-arrow-down">{{ __('Lowest Price') }}</x-ui.badge>
                                        @endif
                                        @if(($summary['best_rated'] ?? null) === $item['id'])
                                            <x-ui.badge color="purple" icon="fa-star">{{ __('Best Rated') }}</x-ui.badge>
                                        @endif
                                    </span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-slate-50">
                        <td class="font-semibold text-slate-500">{{ __('Price') }}</td>
                        @foreach($comparison as $item)
                            <td class="text-base font-bold text-slate-900"><x-money :amount="$item['price'] ?? 0" :currency="$item['currency'] ?? null" /></td>
                        @endforeach
                    </tr>

                    @foreach($rows as $label => $value)
                        <tr>
                            <td class="font-semibold text-slate-500">{{ $label }}</td>
                            @foreach($comparison as $item)
                                <td>{{ $value($item) }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="bg-slate-50">
                        <td class="font-semibold text-slate-500">{{ __('AI Rating') }}</td>
                        @foreach($comparison as $item)
                            @php $overall = $item['rating']['overall'] ?? null; @endphp
                            <td>
                                <x-ui.badge :color="$overall === null ? 'neutral' : ($overall >= 80 ? 'success' : ($overall >= 60 ? 'warning' : 'danger'))">
                                    {{ $overall ?? '—' }}/100
                                </x-ui.badge>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @elseif(isset($prices) && $prices->isNotEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="fa-scale-balanced"
                :title="__('Nothing compared yet')"
                :description="$compareHint"
            />
        </x-ui.card>
    @endif
</div>
