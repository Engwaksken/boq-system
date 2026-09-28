{{-- Badge / pill. color: neutral | success | info | warning | danger | purple | brand --}}
@props([
    'color' => 'neutral',
    'icon' => null,
    'dot' => false,
])

<span {{ $attributes->class([
    'boq-badge',
    'boq-badge-'.$color => $color !== 'neutral',
    'boq-badge-dot' => $dot,
]) }}>
    @if($icon)
        <i class="fas {{ $icon }}" aria-hidden="true"></i>
    @endif
    {{ $slot }}
</span>
