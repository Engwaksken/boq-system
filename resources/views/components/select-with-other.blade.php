@props([
    'label',
    'choice',
    'value',
    'options' => [],
    'otherPlaceholder' => null,
    'error' => null,
    'required' => false,
    'placeholder' => null,
    'current' => '',
])

@php
    $choiceId = 'choice-'.str_replace('.', '-', $choice);
    $otherId = $choiceId.'-other';
    $hasError = $error && $errors->has($error);
@endphp

<div {{ $attributes->class('boq-field-group') }}>
    <label for="{{ $choiceId }}" class="boq-field-label">
        {{ $label }}
        @if($required)
            <span class="boq-field-required" aria-hidden="true">*</span>
        @endif
    </label>

    <select id="{{ $choiceId }}" wire:model.live="{{ $choice }}" @class(['boq-field', 'has-error' => $hasError && $current !== '__other__'])>
        <option value="">{{ $placeholder ?? __('Select...') }}</option>
        @foreach($options as $option)
            <option value="{{ $option }}">{{ $option }}</option>
        @endforeach
        <option value="__other__">{{ __('Other…') }}</option>
    </select>

    @if($current === '__other__')
        <input
            id="{{ $otherId }}"
            type="text"
            wire:model="{{ $value }}"
            @class(['boq-field mt-2', 'has-error' => $hasError])
            placeholder="{{ $otherPlaceholder ?? __('Type a new value') }}"
            aria-label="{{ $label }}"
            autofocus
        >
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
