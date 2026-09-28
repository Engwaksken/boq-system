@props(['totals', 'currency' => null, 'title' => null, 'compact' => false])

@php
    $totals = is_array($totals) ? $totals : [];
    $currency = $currency ?: \App\Support\Regional::currency();
    $money = fn ($value) => $currency.' '.\App\Support\Format::number((float) $value, 0);
    $items = (int) ($totals['items'] ?? 0);
    $estimatedItems = (int) ($totals['estimated_items'] ?? 0);
    $pricedItems = (int) ($totals['priced_items'] ?? 0);
    $hasEstimate = $estimatedItems > 0;
    $hasGenerated = $pricedItems > 0;
    $difference = (float) ($totals['difference'] ?? 0);
    $pricedPercent = $items > 0 ? min(100, (int) round($pricedItems / $items * 100)) : 0;
@endphp

@if ($compact)
    @if ($items > 0)
        <div {{ $attributes->merge(['class' => 'mt-1 space-y-0.5 text-xs font-normal text-slate-500']) }}>
            <div>{{ __('Estimated') }}: <span class="font-semibold text-slate-700">{{ $hasEstimate ? $money($totals['estimated_amount'] ?? 0) : '—' }}</span></div>
            <div>{{ __('Generated') }}: <span class="font-semibold text-brand-700">{{ $hasGenerated ? $money($totals['generated_total'] ?? 0) : '—' }}</span></div>
        </div>
    @endif
@else
    <section {{ $attributes->merge(['class' => 'boq-card']) }}>
        <div class="boq-card-header">
            <h2 class="boq-card-title"><i class="fas fa-calculator" aria-hidden="true"></i> {{ $title ?? __('Totals') }}</h2>
            @if($slot->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
            @endif
        </div>

        <div class="boq-card-body">
            @if ($items === 0)
                <p class="text-sm text-slate-500">{{ __('Totals appear once BOQ items have been imported.') }}</p>
            @else
                <dl class="boq-totals-grid">
                    <div class="boq-totals-cell">
                        <dt>{{ __('Estimated amount') }}</dt>
                        <dd class="boq-totals-value">{{ $hasEstimate ? $money($totals['estimated_amount'] ?? 0) : '—' }}</dd>
                        <dd class="boq-totals-meta">{{ $estimatedItems }} {{ __('of') }} {{ $items }} {{ __('items have an estimate') }}</dd>
                    </div>

                    <div class="boq-totals-cell">
                        <dt>{{ __('Generated total') }}</dt>
                        <dd class="boq-totals-value text-brand-700">{{ $hasGenerated ? $money($totals['generated_total'] ?? 0) : '—' }}</dd>
                        <dd class="boq-totals-meta">{{ $pricedItems }} {{ __('of') }} {{ $items }} {{ __('items priced') }}</dd>
                        <dd class="boq-progress mt-2" aria-hidden="true"><span style="width: {{ $pricedPercent }}%"></span></dd>
                    </div>

                    <div class="boq-totals-cell">
                        <dt>{{ __('Difference') }}</dt>
                        @if ($hasEstimate && $hasGenerated)
                            <dd class="boq-totals-value {{ $difference > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                {{ $difference > 0 ? '+' : ($difference < 0 ? '−' : '') }}{{ $money(abs($difference)) }}
                            </dd>
                            <dd class="boq-totals-meta">{{ $difference > 0 ? __('above the estimate') : __('within the estimate') }}</dd>
                        @else
                            <dd class="boq-totals-value text-slate-400">—</dd>
                            <dd class="boq-totals-meta">{{ __('Needs both an estimate and generated prices.') }}</dd>
                        @endif
                    </div>
                </dl>
            @endif
        </div>
    </section>
@endif
