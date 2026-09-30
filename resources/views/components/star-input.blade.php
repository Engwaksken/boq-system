{{--
    Star picker bound to a Livewire property. model: property path, label: accessible name.
    Clicking the chosen star again clears an optional rating.
--}}
@props(['model', 'label', 'optional' => false, 'size' => 'lg'])

<div x-data="{ value: $wire.entangle('{{ $model }}'), hover: 0 }" {{ $attributes->class(['boq-star-input', 'boq-stars-'.$size]) }} role="radiogroup" aria-label="{{ $label }}" @mouseleave="hover = 0">
    @for($i = 1; $i <= 5; $i++)
        <button type="button" role="radio" :aria-checked="Number(value) === {{ $i }} ? 'true' : 'false'"
            aria-label="{{ trans_choice(':count star|:count stars', $i, ['count' => $i]) }}"
            @click="value = ({{ $optional ? 'true' : 'false' }} && Number(value) === {{ $i }}) ? null : {{ $i }}"
            @mouseenter="hover = {{ $i }}"
            :class="(hover || Number(value) || 0) >= {{ $i }} ? 'is-on' : ''">
            <i class="fas fa-star" aria-hidden="true"></i>
        </button>
    @endfor
    <span class="boq-star-input-text" x-text="value ? value + ' / 5' : '{{ $optional ? __('Optional') : __('Not rated') }}'"></span>
</div>
