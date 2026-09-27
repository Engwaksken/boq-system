<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

class WebAuthnRegisterController
{
    /**
     * Returns a challenge to be verified by the user device.
     *
     * Credentials are discoverable ("userless") so the login page can offer a
     * one-tap biometric sign-in without asking for an email first.
     */
    public function options(AttestationRequest $request): Responsable
    {
        return $request
            ->userless()
            ->secureRegistration()
            ->toCreate();
    }

    /**
     * Registers a device for further WebAuthn authentication.
     */
    public function register(AttestedRequest $request): Response
    {
        $alias = Str::limit(trim((string) $request->input('alias', '')), 60, '') ?: 'This device';

        $request->save(['alias' => $alias]);

        return response()->noContent();
    }
}
