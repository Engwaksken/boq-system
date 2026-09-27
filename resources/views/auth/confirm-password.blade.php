<x-layouts.guest title="Confirm password">

    <div class="text-center">
        <x-auth-badge icon="fa-shield-halved" />

        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Confirm your password</h1>

        <p class="mt-2 text-sm text-slate-500">This is a secure area. Please confirm your password before continuing.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-7 space-y-5">
        @csrf

        <x-auth-password name="password" label="Password" autofocus />

        <button type="submit" class="auth-primary">
            <i class="fas fa-circle-check"></i>
            Confirm
        </button>
    </form>

</x-layouts.guest>
