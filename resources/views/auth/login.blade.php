<x-layouts.guest title="{{ __('Sign in') }}">

    <div class="text-center">

        <x-auth-badge icon="fa-right-to-bracket" />

        <h1
            class="text-2xl font-extrabold tracking-tight text-slate-900"
        >
            {{ __('Sign in') }}
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            {{ __('Access your projects, BOQs, pricing and subscriptions.') }}
        </p>

    </div>


    @if(session('status'))

        <div class="auth-status mt-6" data-autohide="5000">
            <i class="fas fa-circle-check mr-1"></i>
            {{ session('status') }}
        </div>

    @endif


    <form
        method="POST"
        action="{{ route('login') }}"
        class="mt-7 space-y-5"
    >
        @csrf


        {{-- Email --}}
        <div>

            <label
                for="email"
                class="mb-1.5 block text-sm font-semibold text-slate-700"
            >
                {{ __('Email address') }}
            </label>

            <div class="auth-input-wrap">

                <i
                    class="fas fa-envelope auth-input-icon"
                ></i>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="{{ __('e.g. name@example.com') }}"
                    class="auth-field {{ $errors->has('email') ? 'has-error' : '' }}"
                >

            </div>

            @error('email')
                <p class="auth-error">
                    <i class="fas fa-circle-exclamation mr-1"></i>
                    {{ $message }}
                </p>
            @enderror

        </div>


        {{-- Password --}}
        <div>

            <div
                class="mb-1.5 flex items-center justify-between"
            >

                <label
                    for="password"
                    class="block text-sm font-semibold text-slate-700"
                >
                    {{ __('Password') }}
                </label>

                @if(Route::has('password.request'))

                    <a
                        href="{{ route('password.request') }}"
                        class="auth-link text-xs"
                    >
                        {{ __('Forgot password?') }}
                    </a>

                @endif

            </div>

            <div
                class="auth-input-wrap has-toggle"
            >

                <i
                    class="fas fa-lock auth-input-icon"
                ></i>

                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="{{ __('Enter your password') }}"
                    class="auth-field {{ $errors->has('password') ? 'has-error' : '' }}"
                >

                <button
                    type="button"
                    class="auth-password-toggle"
                    data-password-toggle
                    aria-label="{{ __('Show password') }}"
                >
                    <i class="fas fa-eye"></i>
                </button>

            </div>

            @error('password')
                <p class="auth-error">
                    <i class="fas fa-circle-exclamation mr-1"></i>
                    {{ $message }}
                </p>
            @enderror

        </div>


        {{-- Remember --}}
        <div
            class="flex items-center justify-between gap-4"
        >

            <label
                for="remember"
                class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600"
            >

                <input
                    id="remember"
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                    class="rounded border-slate-300 text-[#05645b] focus:ring-[#05645b]"
                >

                <span>
                    {{ __('Remember me') }}
                </span>

            </label>

        </div>


        {{-- Submit --}}
        <button
            type="submit"
            class="auth-primary"
        >
            <i class="fas fa-right-to-bracket"></i>
            {{ __('Sign in') }}
        </button>

    </form>


    {{-- Biometric sign-in: revealed by resources/js/app.js only on devices with Windows Hello / Touch ID / fingerprint --}}
    <div data-biometric-only class="hidden">

        <div class="auth-divider">
            <span>{{ __('or') }}</span>
        </div>

        <button
            type="button"
            data-biometric-login
            class="auth-secondary"
        >
            <i class="fas fa-fingerprint"></i>
            {{ __('Sign in with biometrics') }}
        </button>

        <p data-biometric-error class="auth-error hidden text-center" role="alert"></p>

        <p class="mt-2 text-center text-xs text-slate-500">
            {{ __('Set it up under Profile → Security after signing in with your password.') }}
        </p>

    </div>


    @if(Route::has('register') && \App\Models\SiteSetting::get('allow_registration', true))

        <p
            class="mt-7 text-center text-sm text-slate-500"
        >
            {{ __('Don\'t have an account?') }}

            <a
                href="{{ route('register') }}"
                class="auth-link ml-1"
            >
                {{ __('Create account') }}
            </a>
        </p>

    @endif

</x-layouts.guest>