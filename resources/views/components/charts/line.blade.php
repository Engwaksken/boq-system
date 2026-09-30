{{--
    Trend chart (inline SVG): a line for the average (0..max) over columns for
    the number of entries in each period.
    points: list of ['label' => string, 'value' (or 'average') => ?float, 'count' => int]
--}}
@props([
    'points' => [],
    'max' => 5,
    'label' => null,
    'valueLabel' => null,
    'countLabel' => null,
    'empty' => null,
])

@php
    // 'value' or 'average' (as returned by SupplierRatings::trend()).
    $points = collect($points)->values()->map(fn ($p) => ['label' => $p['label'], 'value' => $p['value'] ?? $p['average'] ?? null, 'count' => (int) ($p['count'] ?? 0)]);
    $n = max(1, $points->count());
    $w = 600; $h = 200; $left = 28; $right = 28; $top = 12; $bottom = 26;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
    $step = $plotW / $n;
    $maxCount = max(1, (int) $points->max('count'));
    $x = fn (int $i) => round($left + $step * ($i + 0.5), 2);
    $y = fn (float $v) => round($top + $plotH - ($v / $max) * $plotH, 2);
    $line = $points->map(fn ($p, $i) => $p['value'] !== null ? $x($i).','.$y((float) $p['value']) : null)->filter()->values();
    $hasData = $points->sum('count') > 0;
    $labelEvery = $n > 8 ? 2 : 1;
@endphp

<figure {{ $attributes->class('boq-chart boq-chart-line') }} @if($label) aria-label="{{ $label }}" @endif>
    @if(! $hasData)
        <p class="boq-chart-empty">{{ $empty ?? __('No data for this period yet.') }}</p>
    @else
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="boq-chart-line-svg" role="img" aria-label="{{ $label }}: {{ $points->filter(fn ($p) => $p['count'] > 0)->map(fn ($p) => $p['label'].' '.number_format((float) $p['value'], 1).' ('.$p['count'].')')->implode(', ') }}">
            @foreach([0, 1, 2, 3, 4, 5] as $tick)
                @if($tick <= $max)
                    <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" class="boq-chart-grid"></line>
                    <text x="{{ $left - 6 }}" y="{{ $y($tick) + 3 }}" text-anchor="end" class="boq-chart-axis">{{ $tick }}</text>
                @endif
            @endforeach

            @foreach($points as $i => $point)
                @if($point['count'] > 0)
                    @php $barH = $point['count'] / $maxCount * $plotH * 0.9; @endphp
                    <rect x="{{ round($x($i) - $step * 0.3, 2) }}" y="{{ round($top + $plotH - $barH, 2) }}" width="{{ round($step * 0.6, 2) }}" height="{{ round($barH, 2) }}" rx="3" class="boq-chart-column">
                        <title>{{ $point['label'] }}: {{ $point['count'] }} {{ $countLabel }}</title>
                    </rect>
                @endif
                @if($i % $labelEvery === 0)
                    <text x="{{ $x($i) }}" y="{{ $h - 8 }}" text-anchor="middle" class="boq-chart-axis">{{ $point['label'] }}</text>
                @endif
            @endforeach

            @if($line->count() > 1)
                <polyline points="{{ $line->implode(' ') }}" class="boq-chart-polyline"></polyline>
            @endif
            @foreach($points as $i => $point)
                @if($point['value'] !== null)
                    <circle cx="{{ $x($i) }}" cy="{{ $y((float) $point['value']) }}" r="4" class="boq-chart-dot">
                        <title>{{ $point['label'] }}: {{ number_format((float) $point['value'], 2) }} {{ $valueLabel }} · {{ $point['count'] }} {{ $countLabel }}</title>
                    </circle>
                @endif
            @endforeach
        </svg>
        <div class="boq-chart-legend boq-chart-legend-inline">
            <span><span class="boq-chart-swatch boq-chart-swatch-line" aria-hidden="true"></span> {{ $valueLabel ?? __('Average') }}</span>
            <span><span class="boq-chart-swatch boq-chart-swatch-column" aria-hidden="true"></span> {{ $countLabel ?? __('Count') }}</span>
        </div>
    @endif
</figure>
