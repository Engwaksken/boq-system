{{--
    Horizontal bar chart (HTML/CSS, no JavaScript).
    items: list of ['label' => string, 'value' => float|null, 'color' => ?string, 'hint' => ?string, 'click' => ?string (Livewire action)]
    max: scale maximum (defaults to the largest value); decimals: value format.
--}}
@props([
    'items' => [],
    'max' => null,
    'decimals' => 1,
    'suffix' => '',
    'color' => 'var(--boq-primary)',
    'label' => null,
    'empty' => null,
])

@php
    $items = collect($items)->values();
    $scale = $max ?: max(1, (float) $items->max('value'));
@endphp

<figure {{ $attributes->class('boq-chart boq-chart-bar') }} @if($label) aria-label="{{ $label }}" @endif>
    @if($items->isEmpty())
        <p class="boq-chart-empty">{{ $empty ?? __('No data for this period yet.') }}</p>
    @else
        <ul class="boq-chart-bars">
            @foreach($items as $bar)
                @php $value = (float) ($bar['value'] ?? 0); @endphp
                <li class="boq-chart-bar-row">
                    <span class="boq-chart-bar-label" title="{{ $bar['label'] }}">
                        @if(! empty($bar['click']))
                            <button type="button" wire:click="{{ $bar['click'] }}" class="boq-table-link text-left">{{ $bar['label'] }}</button>
                        @else
                            {{ $bar['label'] }}
                        @endif
                        @if(! empty($bar['hint']))<small>{{ $bar['hint'] }}</small>@endif
                    </span>
                    <span class="boq-chart-bar-track" aria-hidden="true">
                        <span class="boq-chart-bar-fill" style="width: {{ max(2, min(100, $value / $scale * 100)) }}%; background: {{ $bar['color'] ?? $color }}"></span>
                    </span>
                    <span class="boq-chart-bar-value">{{ number_format($value, $decimals) }}{{ $suffix }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</figure>
