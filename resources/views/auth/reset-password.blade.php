<x-layouts.guest title="{{ __('Reset password') }}">

    <div class="text-center">
        <x-auth-badge icon="fa-unlock-keyhole" />

        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ __('Choose a new password') }}</h1>

        <p class="mt-2 text-sm text-slate-500">{{ __('Enter your email and a new password for your account.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="mt-7 space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('Email address') }}</label>

            <div class="auth-input-wrap">
                <i class="fas fa-envelope auth-input-icon"></i>

                <input placeholder="{{ __('e.g. name@example.com') }}"
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="auth-field {{ $errors->has('email') ? 'has-error' : '' }}"
                >
            </div>

            @error('email')
                <p class="auth-error"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>
            @enderror
        </div>

        <x-auth-password name="password" :label="__('New password')" autocomplete="new-password" placeholder="{{ __('At least 8 characters') }}" />

        <x-auth-password name="password_confirmation" :label="__('Confirm new password')" icon="fa-shield-halved" autocomplete="new-password" placeholder="{{ __('Re-enter your new password') }}" />

        <button type="submit" class="auth-primary">
            <i class="fas fa-key"></i>
            {{ __('Reset password') }}
        </button>
    </form>

</x-layouts.guest>
