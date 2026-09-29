<section class="boq-card">
    <div class="boq-card-header">
        <h2 class="boq-card-title"><i class="fas fa-map-location-dot" aria-hidden="true"></i> {{ __('Location prices') }}</h2>
        @if($locations->count() >= 2)
            <x-ui.button size="sm" variant="secondary" icon="fa-scale-balanced" wire:click="openCompare">{{ __('Compare selected') }}</x-ui.button>
        @endif
    </div>

    <div class="boq-card-body space-y-4">
        <p class="text-sm text-slate-500">{{ __('Price this BOQ for several locations. The prices of every location are kept, so you can compare them and switch between them.') }}</p>

        @error('compareKeys') <p class="boq-field-error">{{ $message }}</p> @enderror

        @if($locations->isEmpty())
            <x-ui.empty-state icon="fa-map-location-dot" :title="__('No location prices yet')" :description="__('Generate BOQ or price it for a location below.')" />
        @else
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($locations as $location)
                    <label wire:key="location-{{ md5($location['key']) }}" class="boq-location-card {{ $location['current'] ? 'is-current' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" class="boq-checkbox" value="{{ $location['key'] }}" wire:model="compareKeys" aria-label="{{ __('Compare :location', ['location' => $location['location']]) }}">
                                <span class="font-semibold">{{ $location['location'] }}</span>
                            </div>
                            @if($location['current'])
                                <x-ui.badge color="success" icon="fa-check">{{ __('In use') }}</x-ui.badge>
                            @endif
                        </div>
                        <div class="mt-2 text-lg font-bold text-brand-700">{{ \App\Support\Format::money($location['total'], $currency) }}</div>
                        <div class="text-xs text-slate-500">
                            {{ __(':priced of :total items priced', ['priced' => $location['priced_items'], 'total' => $location['total_items']]) }}
                            @if($location['priced_at'])
                                · {{ \Illuminate\Support\Carbon::parse($location['priced_at'])->diffForHumans() }}
                            @endif
                        </div>
                        @if($canEdit && ! $location['current'])
                            <button type="button" wire:click="useLocation(@js($location['key']))" wire:confirm="{{ __('Use the prices of :location for this BOQ? Approved prices are kept.', ['location' => $location['location']]) }}" class="boq-btn-ghost mt-2 text-sm">
                                <i class="fas fa-right-left" aria-hidden="true"></i> {{ __('Use these prices') }}
                            </button>
                        @endif
                    </label>
                @endforeach
            </div>
        @endif

        @if($canEdit)
            <form wire:submit="priceForLocation" class="flex flex-wrap items-start gap-2 border-t border-slate-200 pt-4">
                <div class="min-w-[14rem] flex-1">
                    <label for="price-location" class="boq-field-label">{{ __('Price for another location') }}</label>
                    <input id="price-location" type="text" wire:model="newLocation" maxlength="255" class="boq-field @error('newLocation') has-error @enderror" placeholder="{{ __('e.g. Gulu, Mbarara') }}">
                    @error('newLocation') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="boq-btn-primary mt-6" wire:loading.attr="disabled" wire:target="priceForLocation">
                    <i class="fas fa-location-dot" wire:loading.remove wire:target="priceForLocation" aria-hidden="true"></i>
                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="priceForLocation" aria-hidden="true"></i>
                    {{ __('Get prices for this location') }}
                </button>
            </form>
        @endif
    </div>

    @if($showCompare && $comparison)
        <x-ui.modal wire:key="compare-locations" id="compare-locations" :title="__('Compare location prices')" icon="fa-scale-balanced" size="xl" close="closeCompare">
            <x-ui.table>
                <thead>
                    <tr>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th class="text-right">{{ __('Qty') }}</th>
                        @foreach($comparison['locations'] as $location)
                            <th class="text-right">{{ $location['location'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($comparison['rows'] as $row)
                        <tr wire:key="compare-row-{{ $row['item']->id }}">
                            <td class="whitespace-nowrap"><span class="boq-code">{{ $row['item']->item_code ?: '—' }}</span></td>
                            <td class="min-w-[12rem]">{{ \Illuminate\Support\Str::limit($row['item']->description, 80) }}</td>
                            <td class="text-right">{{ \App\Support\Format::number($row['item']->quantity, 2) }}</td>
                            @foreach($comparison['locations'] as $location)
                                @php $rate = $row['rates'][$location['key']] ?? null; @endphp
                                <td class="text-right whitespace-nowrap {{ $row['lowest'] === $location['key'] ? 'font-bold text-emerald-700' : '' }}">
                                    {{ $rate === null ? '—' : \App\Support\Format::number($rate, 2) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">{{ __('Total') }}</th>
                        @php $cheapest = collect($comparison['locations'])->where('priced_items', '>', 0)->sortBy('total')->first()['key'] ?? null; @endphp
                        @foreach($comparison['locations'] as $location)
                            <th class="text-right whitespace-nowrap {{ $cheapest === $location['key'] ? 'text-emerald-700' : '' }}">
                                {{ \App\Support\Format::money($location['total'], $currency) }}
                                <div class="text-xs font-normal text-slate-500">{{ __(':count priced', ['count' => $location['priced_items']]) }}</div>
                            </th>
                        @endforeach
                    </tr>
                </tfoot>
            </x-ui.table>
            <p class="mt-3 text-xs text-slate-500">{{ __('The lowest price of each item is shown in green. Totals only include items priced for that location.') }}</p>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeCompare">{{ __('Close') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</section>
