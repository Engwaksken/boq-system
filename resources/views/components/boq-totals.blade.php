@props(['totals', 'currency' => null, 'title' => null, 'compact' => false])

@php
    $currency = $currency ?: \App\Support\Regional::currency();
    $money = fn ($value) => $currency.' '.\App\Support\Format::number((float) $value, 0);
    $hasEstimate = ($totals['estimated_items'] ?? 0) > 0;
    $hasGenerated = ($totals['priced_items'] ?? 0) > 0;
    $difference = (float) ($totals['difference'] ?? 0);
@endphp

@if ($compact)
    @if (($totals['items'] ?? 0) > 0)
        <div {{ $attributes->merge(['class' => 'mt-1 space-y-0.5 text-xs text-gray-500']) }}>
            <div>{{ __('Estimated') }}: <span class="font-semibold text-gray-700">{{ $hasEstimate ? $money($totals['estimated_amount']) : '—' }}</span></div>
            <div>{{ __('Generated') }}: <span class="font-semibold text-indigo-700">{{ $hasGenerated ? $money($totals['generated_total']) : '—' }}</span></div>
        </div>
    @endif
@else
    <div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-200 p-5']) }}>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-gray-900">{{ $title ?? __('Totals') }}</h2>
            {{ $slot }}
        </div>

        @if (($totals['items'] ?? 0) === 0)
            <p class="mt-3 text-sm text-gray-500">{{ __('Totals appear once BOQ items have been imported.') }}</p>
        @else
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Estimated amount') }}</dt>
                    <dd class="mt-1 text-xl font-bold text-gray-900">{{ $hasEstimate ? $money($totals['estimated_amount']) : '—' }}</dd>
                    <dd class="text-xs text-gray-500">{{ $totals['estimated_items'] }} {{ __('of') }} {{ $totals['items'] }} {{ __('items have an estimate') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Generated total') }}</dt>
                    <dd class="mt-1 text-xl font-bold text-indigo-700">{{ $hasGenerated ? $money($totals['generated_total']) : '—' }}</dd>
                    <dd class="text-xs text-gray-500">{{ $totals['priced_items'] }} {{ __('of') }} {{ $totals['items'] }} {{ __('items priced') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Difference') }}</dt>
                    @if ($hasEstimate && $hasGenerated)
                        <dd class="mt-1 text-xl font-bold {{ $difference > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                            {{ $difference > 0 ? '+' : ($difference < 0 ? '−' : '') }}{{ $money(abs($difference)) }}
                        </dd>
                        <dd class="text-xs text-gray-500">{{ $difference > 0 ? __('above the estimate') : __('within the estimate') }}</dd>
                    @else
                        <dd class="mt-1 text-xl font-bold text-gray-400">—</dd>
                    @endif
                </div>
            </dl>
        @endif
    </div>
@endif
