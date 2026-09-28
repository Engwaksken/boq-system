{{-- Shared error page: no exception details, routes or permission names are ever shown. --}}
<x-layouts.guest :title="$code.' '.$heading">

    <div class="text-center">
        <x-auth-badge :icon="$icon" />

        <p class="text-sm font-bold uppercase tracking-widest text-[#05645b]">{{ __('Error') }} {{ $code }}</p>

        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">{{ $code }} – {{ $heading }}</h1>

        <p class="mt-3 text-sm leading-6 text-slate-500">{{ $message }}</p>

        <div class="mt-7 flex flex-wrap justify-center gap-3">
            <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')" class="auth-secondary w-auto px-5">
                <i class="fas fa-arrow-left"></i>
                {{ __('Back') }}
            </button>

            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="auth-primary w-auto px-5">
                <i class="fas {{ auth()->check() ? 'fa-gauge' : 'fa-right-to-bracket' }}"></i>
                {{ auth()->check() ? __('Return to Dashboard') : __('Sign in') }}
            </a>
        </div>
    </div>

</x-layouts.guest>
