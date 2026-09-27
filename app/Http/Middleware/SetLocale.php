<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Chooses the UI language per request: signed-in user's language, then a guest's
 * choice (?lang=xx, remembered in the session), then the browser's preferred
 * languages, then the site default. Only active languages are used.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = $this->activeLanguages();

        if ($request->filled('lang') && in_array($request->query('lang'), $available, true) && $request->hasSession()) {
            $request->session()->put('locale', $request->query('lang'));
        }

        $candidates = array_filter([
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            ...$request->getLanguages(),
            $this->siteDefault(),
            config('app.fallback_locale', 'en'),
        ]);

        foreach ($candidates as $candidate) {
            $code = strtolower(str_replace('_', '-', (string) $candidate));

            foreach ([$code, strtok($code, '-')] as $option) {
                if (in_array($option, $available, true)) {
                    App::setLocale($option);
                    Carbon::setLocale($option);

                    return $next($request);
                }
            }
        }

        return $next($request);
    }

    /** @return list<string> */
    private function activeLanguages(): array
    {
        try {
            $codes = Language::where('is_active', true)->pluck('code')->map(fn ($code) => strtolower($code))->all();

            return $codes !== [] ? $codes : ['en'];
        } catch (Throwable) {
            return ['en'];
        }
    }

    private function siteDefault(): ?string
    {
        try {
            return SiteSetting::get('language');
        } catch (Throwable) {
            return null;
        }
    }
}
