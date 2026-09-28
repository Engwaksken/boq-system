<x-layouts.guest title="{{ __('Verify your email') }}">

    <div class="text-center">
        <x-auth-badge icon="fa-envelope-circle-check" />

        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ __('Verify your email') }}</h1>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="auth-status mt-6" role="status">
            <i class="fas fa-circle-check mr-1"></i>
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-7 space-y-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <button type="submit" class="auth-primary">
                <i class="fas fa-paper-plane"></i>
                {{ __('Resend Verification Email') }}
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="auth-secondary">
                <i class="fas fa-right-from-bracket"></i>
                {{ __('Log Out') }}
            </button>
        </form>
    </div>

</x-layouts.guest>
