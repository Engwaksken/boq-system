{{-- Shared error page: no exception details, routes or permission names are ever shown. --}}
@php
    try {
        $signedIn = auth()->check();
    } catch (\Throwable) {
        $signedIn = false;
    }

    try {
        $homeUrl = $signedIn ? route('dashboard') : route('login');
    } catch (\Throwable) {
        $homeUrl = url('/');
    }

    $backUrl = url()->previous() !== url()->current() ? url()->previous() : url('/');
@endphp

@component('errors.shell', ['pageTitle' => $code.' '.$heading])
    <span class="badge"><i class="fas {{ $icon }}" aria-hidden="true"></i></span>

    <p class="eyebrow">{{ __('Error') }} {{ $code }}</p>

    <h1>{{ $code }} – {{ $heading }}</h1>

    <p class="message">{{ $message }}</p>

    <div class="actions">
        <a href="{{ $backUrl }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            {{ __('Back') }}
        </a>

        <a href="{{ $homeUrl }}" class="btn btn-primary">
            <i class="fas {{ $signedIn ? 'fa-gauge' : 'fa-right-to-bracket' }}" aria-hidden="true"></i>
            {{ $signedIn ? __('Return to Dashboard') : __('Sign in') }}
        </a>
    </div>
@endcomponent
