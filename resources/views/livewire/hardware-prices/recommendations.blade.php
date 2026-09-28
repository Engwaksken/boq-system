@php
    $fmt = fn ($value) => $value !== null && $value !== '' ? \App\Support\Format::number((float) $value, 2) : '—';
@endphp

<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('AI Recommendations')"
        icon="fa-lightbulb"
        :subtitle="__('Best-value hardware picks based on price, stability and freshness')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="route('hardware-prices.index')">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="boq-panel">
        <form wire:submit="load" class="boq-toolbar">
            <x-ui.field :label="__('Location')" for="location" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-location-dot boq-input-icon" aria-hidden="true"></i>
                    <input type="text" wire:model.live.debounce.300ms="location" id="location" placeholder="{{ __('e.g. city, town or market') }}" class="boq-field boq-field-with-icon">
                </div>
            </x-ui.field>

            <x-ui.button type="submit" icon="fa-rotate" loading="load">{{ __('Refresh Recommendations') }}</x-ui.button>
        </form>
    </div>

    @if(isset($recommendations) && count($recommendations) > 0)
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60" wire:target="load">
            @foreach($recommendations as $rec)
                @php
                    $overall = $rec['rating']['overall'] ?? null;
                    $trend = $rec['price_history']['trend'] ?? 'stable';
                    $ratingColor = $overall === null ? 'bg-slate-100 text-slate-600' : ($overall >= 80 ? 'bg-emerald-100 text-emerald-700' : ($overall >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'));
                @endphp

                <article class="boq-card flex flex-col" wire:key="recommendation-{{ $rec['id'] }}">
                    <div class="boq-card-body flex flex-1 flex-col">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-bold {{ $ratingColor }}">{{ $overall ?? '—' }}</span>
                                <div>
                                    <p class="text-xs text-slate-500">{{ __('AI Rating') }}</p>
                                    <p class="text-xs font-medium text-slate-700">{{ __('out of 100') }}</p>
                                </div>
                            </div>

                            <x-ui.badge :color="$trend === 'rising' ? 'danger' : ($trend === 'falling' ? 'success' : 'neutral')" :icon="$trend === 'rising' ? 'fa-arrow-trend-up' : ($trend === 'falling' ? 'fa-arrow-trend-down' : 'fa-minus')">
                                {{ __(ucfirst($trend)) }}
                            </x-ui.badge>
                        </div>

                        <h3 class="text-base font-semibold text-slate-900">{{ $rec['item_name'] }}</h3>
                        @if(! empty($rec['brand']))
                            <p class="text-sm text-slate-500">{{ $rec['brand'] }}</p>
                        @endif
                        @if(! empty($rec['category']))
                            <x-ui.badge class="mt-2 w-fit">{{ $rec['category'] }}</x-ui.badge>
                        @endif

                        @if(! empty($rec['specification']))
                            <p class="mt-3 line-clamp-2 text-sm text-slate-500">{{ $rec['specification'] }}</p>
                        @endif

                        <p class="mt-4 text-xl font-bold tracking-tight text-slate-900"><x-money :amount="$rec['price'] ?? 0" :currency="$rec['currency'] ?? null" /></p>
                        <p class="text-sm text-slate-500">{{ collect([$rec['supplier'] ?? null, $rec['location'] ?? null])->filter()->join(' · ') ?: '—' }}</p>

                        <dl class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-4 text-xs">
                            @foreach(['lowest' => __('Lowest'), 'highest' => __('Highest'), 'average' => __('Average'), 'change' => __('Change')] as $key => $label)
                                <div>
                                    <dt class="text-slate-500">{{ $label }}</dt>
                                    <dd class="font-semibold tabular-nums text-slate-900">{{ $fmt($rec['price_history'][$key] ?? null) }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="mt-auto pt-4">
                            <x-ui.button variant="secondary" block :href="route('hardware-prices.show', $rec['id'])">{{ __('View Details') }}</x-ui.button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <x-ui.card>
            <x-ui.empty-state
                icon="fa-lightbulb"
                :title="__('No recommendations available yet.')"
                :description="__('Recommendations appear once enough current prices are available. Try another location.')"
            />
        </x-ui.card>
    @endif
</div>
