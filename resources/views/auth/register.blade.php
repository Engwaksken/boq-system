<x-layouts.guest title="Create account">

    <div class="text-center">

        <div
            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-[#05645b]/10 text-[#05645b]"
        >
            <i class="fas fa-user-plus text-lg"></i>
        </div>

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
        <div
            x-data="{ showPassword: false }"
        >

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
                    x-bind:type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Create a secure password"
                    class="auth-field {{ $errors->has('password') ? 'has-error' : '' }}"
                >

                <button
                    type="button"
                    x-on:click="showPassword = ! showPassword"
                    class="auth-password-toggle"
                    aria-label="Show or hide password"
                >
                    <i
                        class="fas"
                        x-bind:class="
                            showPassword
                                ? 'fa-eye-slash'
                                : 'fa-eye'
                        "
                    ></i>
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
        <div
            x-data="{ showPassword: false }"
        >

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
                    x-bind:type="showPassword ? 'text' : 'password'"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Re-enter your password"
                    class="auth-field"
                >

                <button
                    type="button"
                    x-on:click="showPassword = ! showPassword"
                    class="auth-password-toggle"
                    aria-label="Show or hide password confirmation"
                >
                    <i
                        class="fas"
                        x-bind:class="
                            showPassword
                                ? 'fa-eye-slash'
                                : 'fa-eye'
                        "
                    ></i>
                </button>

            </div>

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