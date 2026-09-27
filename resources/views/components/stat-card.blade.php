@props([
    'label',
    'value',
    'icon' => 'fa-chart-simple',
    'color' => 'green',
    'hint' => null,
])

<div {{ $attributes->class(['boq-stat-card', 'boq-stat-'.$color]) }}>
    <div class="min-w-0">
        <p class="boq-stat-label">{{ $label }}</p>
        <p class="boq-stat-value">{{ $value }}</p>
        @if($hint !== null && $hint !== '')
            <p class="boq-stat-hint">{{ $hint }}</p>
        @endif
    </div>

    <span class="boq-stat-icon">
        <i class="fas {{ $icon }}"></i>
    </span>
</div>
