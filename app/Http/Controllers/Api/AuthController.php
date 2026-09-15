<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'organisation_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $organisation = null;
            if (! empty($validated['organisation_name'])) {
                $organisation = Organisation::create([
                    'name' => $validated['organisation_name'],
                    'code' => Str::upper(Str::random(6)),
                    'default_locale' => $validated['locale'] ?? 'en',
                    'default_currency' => 'UGX',
                    'is_active' => true,
                ]);
            }

            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'locale' => $validated['locale'] ?? 'en',
            ]);

            // These fields are intentionally not mass-assignable; set them explicitly.
            $user->organisation_id = $organisation?->id;
            $user->is_active = true;
            $user->save();

            // Assign default viewer role
            $viewerRole = Role::where('slug', 'viewer')->first();
            if ($viewerRole) {
                $user->roles()->attach($viewerRole->id);
            }

            // Grant 7-day trial subscription
            app(\App\Services\SubscriptionService::class)->grantTrial($user, $organisation);

            return $user;
        });

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('auth.registered'),
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Login a user.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'error_code' => 'ACCOUNT_DISABLED',
                'message' => __('auth.account_disabled'),
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('auth.logged_in'),
            'data' => [
                'user' => $user->load('roles', 'organisation'),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout the current user.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => __('auth.logged_out'),
        ]);
    }

    /**
     * Get the current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles', 'organisation');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'permissions' => $user->getAllPermissionSlugs(),
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile and optional password.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['required', 'string', 'max:10'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => __('auth.profile_updated'),
            'data' => ['user' => $user->fresh()],
        ]);
    }
}
