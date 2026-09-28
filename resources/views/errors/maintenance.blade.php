@php
    try {
        $loginUrl = route('login');
    } catch (\Throwable) {
        $loginUrl = url('/login');
    }
@endphp

@component('errors.shell', ['pageTitle' => __('Maintenance')])
    <span class="badge"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i></span>

    <h1>{{ __('We\'ll be right back') }}</h1>

    <p class="message">{{ $message }}</p>

    <p class="message"><a href="{{ $loginUrl }}" class="link">{{ __('Administrator sign in') }}</a></p>
@endcomponent
