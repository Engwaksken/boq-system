{{--
    Session flash messages, rendered as dismissible alerts.

    keys:  session keys to show, in order (defaults cover the keys the app flashes).
    types: override the alert type per key, e.g. ['message' => 'warning'].
--}}
@props([
    'keys' => ['status', 'success', 'message', 'warning', 'error'],
    'types' => [],
])

@php
    $defaultTypes = [
        'status' => 'success',
        'success' => 'success',
        'message' => 'success',
        'warning' => 'warning',
        'error' => 'error',
    ];

    // Some controllers flash a status code rather than a sentence.
    $codes = [
        'profile-updated' => __('Profile updated.'),
        'password-updated' => __('Password updated.'),
        'verification-link-sent' => __('A new verification link has been sent to your email address.'),
    ];

    $messages = collect($keys)
        ->mapWithKeys(fn (string $key) => [$key => session($key)])
        ->filter(fn ($value) => is_string($value) && trim($value) !== '')
        ->map(fn (string $value) => $codes[$value] ?? $value);
@endphp

@if($messages->isNotEmpty())
    <div {{ $attributes->class('flex flex-col gap-2') }}>
        @foreach($messages as $key => $text)
            @php
                $type = $types[$key] ?? $defaultTypes[$key] ?? (str_contains($key, 'error') ? 'error' : 'success');
            @endphp

            <x-ui.alert :type="$type" dismissible wire:key="flash-{{ $key }}-{{ md5($text) }}">
                {{ $text }}
            </x-ui.alert>
        @endforeach
    </div>
@endif
