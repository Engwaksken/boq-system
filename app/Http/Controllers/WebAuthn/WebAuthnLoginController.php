<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

class WebAuthnLoginController
{
    /**
     * Returns the challenge to assertion. Biometric / PIN verification is required.
     */
    public function options(AssertionRequest $request): Responsable
    {
        return $request
            ->secureLogin()
            ->toVerify($request->validate(['email' => 'sometimes|email|string']));
    }

    /**
     * Log the user in with a passkey, refusing disabled accounts.
     */
    public function login(AssertedRequest $request): JsonResponse
    {
        $user = $request->login(callbacks: fn ($user) => (bool) $user->is_active);

        if (! $user) {
            return response()->json([
                'message' => 'Biometric sign-in failed. Use your email and password instead.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'redirect' => redirect()->intended(route('dashboard', absolute: false))->getTargetUrl(),
        ]);
    }
}
