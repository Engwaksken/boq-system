{{--
    Statistic card. Becomes clickable when given an href (renders a link) or a
    wire:click / @click attribute (renders a button); :active highlights it as
    the current filter.
--}}
@props([
    'label',
    'value',
    'icon' => 'fa-chart-simple',
    'color' => 'green',
    'hint' => null,
    'href' => null,
    'active' => false,
])

@php
    $isButton = ! $href && ($attributes->has('wire:click') || $attributes->has('x-on:click') || $attributes->has('@click'));
@endphp

@if($href || $isButton)
    @if($href)
        <a href="{{ $href }}" {{ $attributes->class(['boq-stat-link', 'is-active' => $active]) }} @if($active) aria-current="true" @endif>
    @else
        <button type="button" {{ $attributes->class(['boq-stat-link', 'is-active' => $active]) }} aria-pressed="{{ $active ? 'true' : 'false' }}">
    @endif
        <div class="boq-stat-card boq-stat-{{ $color }}">
            <div class="min-w-0">
                <p class="boq-stat-label">{{ $label }}</p>
                <p class="boq-stat-value">{{ $value }}</p>
                @if($hint !== null && $hint !== '')
                    <p class="boq-stat-hint">{{ $hint }}</p>
                @endif
            </div>

            <span class="boq-stat-icon" aria-hidden="true">
                <i class="fas {{ $icon }}"></i>
            </span>
        </div>
    @if($href)
        </a>
    @else
        </button>
    @endif
@else
    <div {{ $attributes->class(['boq-stat-card', 'boq-stat-'.$color]) }}>
        <div class="min-w-0">
            <p class="boq-stat-label">{{ $label }}</p>
            <p class="boq-stat-value">{{ $value }}</p>
            @if($hint !== null && $hint !== '')
                <p class="boq-stat-hint">{{ $hint }}</p>
            @endif
        </div>

        <span class="boq-stat-icon" aria-hidden="true">
            <i class="fas {{ $icon }}"></i>
        </span>
    </div>
@endif
