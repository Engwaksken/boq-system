@props([
    'label',
    'choice',
    'value',
    'options' => [],
    'otherPlaceholder' => 'Type a new value',
    'error' => null,
    'required' => false,
    'placeholder' => 'Select...',
    'current' => '',
])

@php
    $choiceId = 'choice-'.str_replace('.', '-', $choice);
@endphp

<div {{ $attributes }}>
    <label for="{{ $choiceId }}" class="boq-field-label">
        {{ $label }}@if($required) *@endif
    </label>

    <select id="{{ $choiceId }}" wire:model.live="{{ $choice }}" class="boq-field">
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $option)
            <option value="{{ $option }}">{{ $option }}</option>
        @endforeach
        <option value="__other__">Other…</option>
    </select>

    @if($current === '__other__')
        <input
            type="text"
            wire:model="{{ $value }}"
            class="boq-field mt-2"
            placeholder="{{ $otherPlaceholder }}"
            autofocus
        >
    @endif

    @if($error)
        @error($error)
            <p class="boq-field-error">{{ $message }}</p>
        @enderror
    @endif
</div>
