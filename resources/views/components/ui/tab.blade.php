{{--
    A single tab. Renders a link when given an href, otherwise a button (pass
    wire:click or @click). :active marks the current tab; count shows a pill.
--}}
@props([
    'active' => false,
    'icon' => null,
    'href' => null,
    'count' => null,
])

@if($href)
    <a href="{{ $href }}" {{ $attributes->class(['boq-tab', 'is-active' => $active]) }} @if($active) aria-current="page" @endif>
@else
    <button type="button" {{ $attributes->class(['boq-tab', 'is-active' => $active]) }} aria-pressed="{{ $active ? 'true' : 'false' }}">
@endif
    @if($icon)
        <i class="fas {{ $icon }}" aria-hidden="true"></i>
    @endif
    <span>{{ $slot }}</span>
    @if($count !== null)
        <span class="boq-tab-count">{{ $count }}</span>
    @endif
@if($href)
    </a>
@else
    </button>
@endif
