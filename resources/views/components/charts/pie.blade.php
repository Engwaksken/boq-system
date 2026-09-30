{{--
    Pie / donut chart (inline SVG) with a legend.
    slices: list of ['label' => string, 'value' => int|float, 'color' => string]
--}}
@props([
    'slices' => [],
    'label' => null,
    'donut' => true,
    'center' => null,
    'centerLabel' => null,
    'empty' => null,
])

@php
    $slices = collect($slices)->filter(fn ($s) => ($s['value'] ?? 0) > 0)->values();
    $total = (float) $slices->sum('value');
    $radius = 15.915; // circumference = 100, so dash lengths are percentages
    $offset = 25; // start at 12 o'clock
@endphp

<figure {{ $attributes->class('boq-chart boq-chart-pie') }} @if($label) aria-label="{{ $label }}" @endif>
    @if($total <= 0)
        <p class="boq-chart-empty">{{ $empty ?? __('No data for this period yet.') }}</p>
    @else
        <svg viewBox="0 0 42 42" class="boq-chart-pie-svg" role="img" aria-label="{{ $label }}: {{ $slices->map(fn ($s) => $s['label'].' '.round($s['value'] / $total * 100).'%')->implode(', ') }}">
            <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="var(--boq-surface-sunken)" stroke-width="{{ $donut ? 7 : 31.83 }}"></circle>
            @foreach($slices as $slice)
                @php $share = $slice['value'] / $total * 100; @endphp
                <circle cx="21" cy="21" r="{{ $radius }}" fill="none"
                    stroke="{{ $slice['color'] }}" stroke-width="{{ $donut ? 7 : 31.83 }}"
                    stroke-dasharray="{{ round($share, 3) }} {{ round(100 - $share, 3) }}"
                    stroke-dashoffset="{{ round($offset, 3) }}">
                    <title>{{ $slice['label'] }}: {{ \App\Support\Format::number($slice['value'], 0) }} ({{ round($share) }}%)</title>
                </circle>
                @php $offset -= $share; @endphp
            @endforeach
            @if($donut && $center !== null)
                <text x="21" y="{{ $centerLabel ? 20.5 : 22.5 }}" text-anchor="middle" class="boq-chart-pie-center">{{ $center }}</text>
                @if($centerLabel)<text x="21" y="26" text-anchor="middle" class="boq-chart-pie-center-label">{{ $centerLabel }}</text>@endif
            @endif
        </svg>
        <ul class="boq-chart-legend">
            @foreach($slices as $slice)
                <li>
                    <span class="boq-chart-swatch" style="background: {{ $slice['color'] }}" aria-hidden="true"></span>
                    <span class="boq-chart-legend-label">{{ $slice['label'] }}</span>
                    <span class="boq-chart-legend-value">{{ \App\Support\Format::number($slice['value'], 0) }} · {{ round($slice['value'] / $total * 100) }}%</span>
                </li>
            @endforeach
        </ul>
    @endif
</figure>
