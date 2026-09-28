{{-- Tab strip container. Children are <x-ui.tab> items. --}}
@props(['label' => null])

<nav {{ $attributes->class('boq-tabs') }} @if($label) aria-label="{{ $label }}" @endif>
    {{ $slot }}
</nav>
