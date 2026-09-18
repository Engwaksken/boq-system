<x-layouts.guest>
    <h2 class="text-xl font-semibold text-gray-900">Sign in to your account</h2>
    <p class="mt-1 text-sm text-gray-500">Welcome back to BOQ System</p>

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-6">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="Enter your email"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 pr-12">
            <div class="absolute right-3 top-1/2 -translate-y-1/2">
                @if (Auth::check() || session()->has('show_password'))
                    <a href="javascript:void(0)" onclick="togglePassword('password')" class="text-indigo-500 text-sm hover:text-indigo-600">Show password</a>
                @endif
            </div>
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 pr-12"
                   id="password-field">
            <div class="absolute right-3 top-1/2 -translate-y-1/2">
                @if (session()->has('show_password'))
                    <a href="javascript:void(0)" onclick="togglePassword('password')" class="text-indigo-500 text-sm hover:text-indigo-600">Hide password</a>
                @else
                    <a href="javascript:void(0)" onclick="togglePassword('password')" class="text-indigo-500 text-sm hover:text-indigo-600">Show password</a>
                @endif
            </div>
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="flex items-center">
                <input id="remember" type="checkbox" name="remember"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="ml-2 text-sm text-gray-600">Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Forgot your password?</a>
            @endif
        </div>

        <div>
            <button type="submit"
                    class="inline-flex items-center justify-center w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
                Sign in
            </button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Register</a>
    </p>

    <script>
        function togglePassword(fieldId) {
            var input = document.getElementById(fieldId);
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                sessionStorage.setItem('show_password', '1');
            } else {
                input.attr('type', 'password');
                sessionStorage.removeItem('show_password');
            }
        }
    </script>
</x-layouts.guest>
