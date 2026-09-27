<x-layouts.guest title="{{ __('Forgot password') }}">

    <div class="text-center">

        <x-auth-badge icon="fa-key" />

        <h1
            class="text-2xl font-extrabold tracking-tight text-slate-900"
        >
            {{ __('Forgot your password?') }}
        </h1>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            {{ __('Enter the email address linked to your account. We will send you a secure password reset link.') }}
        </p>

    </div>


    @if(session('status'))

        <div class="auth-status mt-6">

            <i class="fas fa-circle-check mr-1"></i>

            {{ session('status') }}

        </div>

    @endif


    <form
        method="POST"
        action="{{ route('password.email') }}"
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
                    autocomplete="email"
                    placeholder="e.g. name@example.com"
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


        {{-- Submit --}}
        <button
            type="submit"
            class="auth-primary"
        >
            <i class="fas fa-paper-plane"></i>
            {{ __('Send password reset link') }}
        </button>

    </form>


    <div
        class="mt-7 border-t border-slate-200 pt-5 text-center"
    >

        <a
            href="{{ route('login') }}"
            class="auth-link inline-flex items-center gap-2 text-sm"
        >
            <i class="fas fa-arrow-left"></i>
            {{ __('Back to sign in') }}
        </a>

    </div>

</x-layouts.guest>