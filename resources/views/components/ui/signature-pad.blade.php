{{--
    Signature drawing pad (resources/js/signature-pad.js). The drawing is written
    to a hidden input as a PNG data URL after every stroke.

    id:    id of the hidden input
    model: Livewire property to bind (wire:model), or
    name:  form field name for a plain form

    <x-ui.signature-pad id="preparer-signature" model="drawnSignature" />
--}}
@props([
    'id',
    'model' => null,
    'name' => null,
    'label' => null,
])

<div {{ $attributes->class('space-y-1.5') }}>
    <input type="hidden" id="{{ $id }}" @if($model) wire:model="{{ $model }}" @endif @if($name) name="{{ $name }}" @endif>

    <div class="boq-signature-pad" data-signature-pad data-signature-input="{{ $id }}" wire:ignore>
        <canvas data-signature-canvas role="img" aria-label="{{ $label ?? __('Signature pad') }}"></canvas>

        <div class="boq-signature-pad-hint" aria-hidden="true">
            <i class="fas fa-xmark"></i>
            <span>{{ __('Sign here') }}</span>
        </div>

        <div class="boq-signature-pad-actions">
            <button type="button" data-signature-undo title="{{ __('Undo the last stroke') }}">
                <i class="fas fa-rotate-left" aria-hidden="true"></i> {{ __('Undo') }}
            </button>
            <button type="button" data-signature-clear title="{{ __('Clear the pad') }}">
                <i class="fas fa-eraser" aria-hidden="true"></i> {{ __('Clear') }}
            </button>
        </div>
    </div>

    <p class="boq-field-help">{{ __('Sign with your mouse, finger or pen.') }}</p>
</div>
