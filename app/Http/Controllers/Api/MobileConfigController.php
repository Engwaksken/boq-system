<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Language;
use App\Models\SiteSetting;
use App\Support\Regional;
use Illuminate\Http\JsonResponse;

class MobileConfigController extends Controller
{
    /**
     * Return the configuration the Flutter app needs for its splash screen,
     * login screen and legal links. Values are managed from the admin
     * dashboard (Site Settings) and cached for a short window.
     */
    /**
     * Legal text may be HTML; keep URLs as-is and turn markup into readable text.
     */
    private static function plainText(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === strip_tags($value)) {
            return $value;
        }

        $value = preg_replace('#<(br|/p|/h[1-6]|/li|/tr|/div)\b[^>]*>#i', "\n", $value);
        $value = preg_replace('#<li\b[^>]*>#i', '- ', $value);
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Country names for the app's picker: the configured default country first, then A-Z.
     *
     * @return list<string>
     */
    private static function countryNames(): array
    {
        $names = array_values(Country::options());
        $default = Regional::countryName();

        if ($default !== '' && in_array($default, $names, true)) {
            $names = array_merge([$default], array_values(array_diff($names, [$default])));
        }

        return array_merge($names, ['Other']);
    }

    public function __invoke(): JsonResponse
    {
        $config = cache()->remember('mobile_config', 60, function () {
            return [
                'system_name' => SiteSetting::get('system_name', 'Civil Works AI BOQ Platform'),
                'splash_enabled' => SiteSetting::get('splash_enabled', true),
                'splash_duration' => SiteSetting::get('splash_duration', 2000),
                'splash_message' => SiteSetting::get('splash_message', ''),
                'login_title' => SiteSetting::get('login_title', ''),
                'login_subtitle' => SiteSetting::get('login_subtitle', ''),
                'allow_registration' => SiteSetting::get('allow_registration', true),
                'allow_forgot_password' => SiteSetting::get('allow_forgot_password', true),
                // The app renders plain text; the *_url fields open the formatted web page.
                'privacy_policy' => self::plainText((string) SiteSetting::get('privacy_policy', '')),
                'terms_of_use' => self::plainText((string) SiteSetting::get('terms_of_use', '')),
                'privacy_policy_url' => route('legal.privacy'),
                'terms_of_use_url' => route('legal.terms'),
                // Country names (the app's picker) plus codes for dialling prefixes and currency.
                'countries' => self::countryNames(),
                'countries_detailed' => Country::active()->orderBy('name')->get(['iso2', 'name', 'dial_code', 'currency_code']),
                'default_country' => Regional::countryCode(),
                'default_currency' => Regional::currency(),
                'languages' => Language::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['code', 'name', 'native_name']),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }
}