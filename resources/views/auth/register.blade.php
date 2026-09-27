<x-layouts.guest title="Create account">

    <div class="text-center">

        <x-auth-badge icon="fa-user-plus" />

        <h1
            class="text-2xl font-extrabold tracking-tight text-slate-900"
        >
            Create your account
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Start managing projects, BOQs and construction pricing.
        </p>

    </div>


    <form
        method="POST"
        action="{{ route('register') }}"
        class="mt-7 space-y-5"
    >
        @csrf


        {{-- Name --}}
        <div>

            <label
                for="name"
                class="mb-1.5 block text-sm font-semibold text-slate-700"
            >
                Full name
            </label>

            <div class="auth-input-wrap">

                <i
                    class="fas fa-user auth-input-icon"
                ></i>

                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="e.g. John Ssemanda"
                    class="auth-field {{ $errors->has('name') ? 'has-error' : '' }}"
                >

            </div>

            @error('name')
                <p class="auth-error">
                    <i class="fas fa-circle-exclamation mr-1"></i>
                    {{ $message }}
                </p>
            @enderror

        </div>


        {{-- Email --}}
        <div>

            <label
                for="email"
                class="mb-1.5 block text-sm font-semibold text-slate-700"
            >
                Email address
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
                    autocomplete="username"
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


        {{-- Password --}}
        <div>

            <label
                for="password"
                class="mb-1.5 block text-sm font-semibold text-slate-700"
            >
                Password
            </label>

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
                    autocomplete="new-password"
                    placeholder="Create a secure password"
                    class="auth-field {{ $errors->has('password') ? 'has-error' : '' }}"
                >

                <button
                    type="button"
                    class="auth-password-toggle"
                    data-password-toggle
                    aria-label="Show password"
                >
                    <i class="fas fa-eye"></i>
                </button>

            </div>

            <p
                class="mt-1.5 text-xs text-slate-400"
            >
                Use a strong password with a mix of letters,
                numbers and symbols.
            </p>

            @error('password')
                <p class="auth-error">
                    <i class="fas fa-circle-exclamation mr-1"></i>
                    {{ $message }}
                </p>
            @enderror

        </div>


        {{-- Confirm Password --}}
        <div>

            <label
                for="password_confirmation"
                class="mb-1.5 block text-sm font-semibold text-slate-700"
            >
                Confirm password
            </label>

            <div
                class="auth-input-wrap has-toggle"
            >

                <i
                    class="fas fa-shield-halved auth-input-icon"
                ></i>

                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Re-enter your password"
                    class="auth-field"
                >

                <button
                    type="button"
                    class="auth-password-toggle"
                    data-password-toggle
                    aria-label="Show password"
                >
                    <i class="fas fa-eye"></i>
                </button>

            </div>

        </div>


        {{-- Privacy policy & terms --}}
        <div>

            <label
                for="terms"
                class="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-slate-600"
            >

                <input
                    id="terms"
                    type="checkbox"
                    name="terms"
                    value="1"
                    required
                    @checked(old('terms'))
                    class="mt-1 rounded border-slate-300 text-[#05645b] focus:ring-[#05645b]"
                >

                <span>
                    I agree to the

                    <a
                        href="{{ route('legal.privacy') }}"
                        target="_blank"
                        rel="noopener"
                        class="auth-link"
                    >
                        Privacy Policy
                    </a>

                    and

                    <a
                        href="{{ route('legal.terms') }}"
                        target="_blank"
                        rel="noopener"
                        class="auth-link"
                    >
                        Terms of Use
                    </a>
                </span>

            </label>

            @error('terms')
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
            <i class="fas fa-user-plus"></i>
            Create account
        </button>

    </form>


    <p
        class="mt-7 text-center text-sm text-slate-500"
    >
        Already have an account?

        <a
            href="{{ route('login') }}"
            class="auth-link ml-1"
        >
            Sign in
        </a>
    </p>

</x-layouts.guest>