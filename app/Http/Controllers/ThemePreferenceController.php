<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quick light/dark switch in the top bar user menu. The menu updates <html>
 * immediately and posts here to remember the choice.
 */
class ThemePreferenceController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(User::INTERFACE_PREFERENCES['theme'])],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'preferences' => array_merge($user->preferences ?? [], ['theme' => $validated['theme']]),
        ])->save();

        if ($request->expectsJson()) {
            return response()->json(['theme' => $validated['theme']]);
        }

        return back();
    }
}
