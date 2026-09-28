{{--
    Form field wrapper: label, the control (slot), hint and validation message.

    <x-ui.field :label="__('Name')" for="project-name" error="name" required>
        <input id="project-name" wire:model="name" class="boq-field">
    </x-ui.field>
--}}
@props([
    'label' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

<div {{ $attributes->class('boq-field-group') }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="boq-field-label">
            {{ $label }}
            @if($required)
                <span class="boq-field-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if($hint)
        <p class="boq-field-help">{{ $hint }}</p>
    @endif

    @if($error)
        @error($error)
            <p class="boq-field-error" role="alert">
                <i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
                <span>{{ $message }}</span>
            </p>
        @enderror
    @endif
</div>
