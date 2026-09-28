{{--
    Button / link button.

    variant: primary | secondary | danger | ghost | soft
    size:    sm | lg (default is medium)
    href:    renders an <a> instead of a <button>
    loading: Livewire action name(s) to show a spinner for and disable during (wire:target)
--}}
@props([
    'variant' => 'primary',
    'size' => null,
    'icon' => null,
    'iconRight' => null,
    'href' => null,
    'type' => 'button',
    'loading' => null,
    'block' => false,
])

@php
    $classes = [
        'boq-btn-'.$variant,
        'boq-btn-sm' => $size === 'sm',
        'boq-btn-lg' => $size === 'lg',
        'boq-btn-block' => $block,
    ];
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if($icon)
            <i class="fas {{ $icon }}" aria-hidden="true"></i>
        @endif
        {{ $slot }}
        @if($iconRight)
            <i class="fas {{ $iconRight }}" aria-hidden="true"></i>
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class($classes) }}
        @if($loading) wire:loading.attr="disabled" wire:target="{{ $loading }}" @endif
    >
        @if($loading)
            <i class="fas fa-spinner fa-spin" wire:loading wire:target="{{ $loading }}" aria-hidden="true"></i>
            @if($icon)
                <i class="fas {{ $icon }}" wire:loading.remove wire:target="{{ $loading }}" aria-hidden="true"></i>
            @endif
        @elseif($icon)
            <i class="fas {{ $icon }}" aria-hidden="true"></i>
        @endif
        {{ $slot }}
        @if($iconRight)
            <i class="fas {{ $iconRight }}" aria-hidden="true"></i>
        @endif
    </button>
@endif
