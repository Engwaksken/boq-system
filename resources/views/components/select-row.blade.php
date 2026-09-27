@props(['id'])

<input
    type="checkbox"
    class="boq-checkbox"
    value="{{ $id }}"
    wire:model.live="selected"
    wire:key="select-{{ $id }}"
    aria-label="Select row"
>
