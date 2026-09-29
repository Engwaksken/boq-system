{{--
    Inline alert. type: success | error | warning | info
    dismissible: adds a close button (client-side only).
    autohide: milliseconds after which the alert fades out (null = stays).
--}}
@props([
    'type' => 'info',
    'title' => null,
    'icon' => null,
    'dismissible' => false,
    'autohide' => null,
])

@php
    $icon ??= match ($type) {
        'success' => 'fa-circle-check',
        'error', 'danger' => 'fa-circle-exclamation',
        'warning' => 'fa-triangle-exclamation',
        default => 'fa-circle-info',
    };

    $variant = match ($type) {
        'success' => '',
        'error', 'danger' => 'boq-alert-error',
        'warning' => 'boq-alert-warning',
        default => 'boq-alert-info',
    };
@endphp

<div
    {{ $attributes->class(['boq-alert', $variant]) }}
    role="{{ in_array($type, ['error', 'danger', 'warning'], true) ? 'alert' : 'status' }}"
    @if($dismissible) x-data="{ shown: true }" x-show="shown" @endif
    @if($autohide) data-autohide="{{ (int) $autohide }}" @endif
>
    <i class="fas {{ $icon }}" aria-hidden="true"></i>

    <div class="boq-alert-body">
        @if($title)
            <p class="boq-alert-title">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>

    @if($dismissible)
        <button type="button" class="boq-flash-close" @click="shown = false" aria-label="{{ __('Dismiss') }}">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    @endif
</div>
