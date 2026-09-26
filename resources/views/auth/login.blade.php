<x-layouts.guest title="Sign in">

    <div class="text-center">

        <div
            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-[#05645b]/10 text-[#05645b]"
        >
            <i class="fas fa-right-to-bracket text-lg"></i>
        </div>

        <h1
            class="text-2xl font-extrabold tracking-tight text-slate-900"
        >
            Sign in
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Access your projects, BOQs, pricing and subscriptions.
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
                    autofocus
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

            <div
                class="mb-1.5 flex items-center justify-between"
            >

                <label
                    for="password"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Password
                </label>

                @if(Route::has('password.request'))

                    <a
                        href="{{ route('password.request') }}"
                        class="auth-link text-xs"
                    >
                        Forgot password?
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
                    x-bind:type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="auth-field {{ $errors->has('password') ? 'has-error' : '' }}"
                >

                <button
                    type="button"
                    x-on:click="showPassword = ! showPassword"
                    class="auth-password-toggle"
                    x-bind:aria-label="
                        showPassword
                            ? 'Hide password'
                            : 'Show password'
                    "
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
                    Remember me
                </span>

            </label>

        </div>


        {{-- Submit --}}
        <button
            type="submit"
            class="auth-primary"
        >
            <i class="fas fa-right-to-bracket"></i>
            Sign in
        </button>

    </form>


    @if(Route::has('register'))

        <p
            class="mt-7 text-center text-sm text-slate-500"
        >
            Don't have an account?

            <a
                href="{{ route('register') }}"
                class="auth-link ml-1"
            >
                Create account
            </a>
        </p>

    @endif

</x-layouts.guest>