<x-layouts.guest title="{{ __('Maintenance') }}">

    <div class="text-center">
        <x-auth-badge icon="fa-screwdriver-wrench" />

        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ __('We\'ll be right back') }}</h1>

        <p class="mt-3 text-sm leading-6 text-slate-500">{{ $message }}</p>

        <p class="mt-6 text-sm">
            <a href="{{ route('login') }}" class="auth-link">{{ __('Administrator sign in') }}</a>
        </p>
    </div>

</x-layouts.guest>
