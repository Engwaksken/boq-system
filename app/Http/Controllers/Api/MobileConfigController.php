<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class MobileConfigController extends Controller
{
    /**
     * Countries offered as a free-text-friendly list for sign-up and profiles.
     * Kept local to the endpoint since country data is small and stable.
     */
    private const COUNTRIES = [
        'Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Burundi',
        'South Sudan', 'Sudan', 'Democratic Republic of the Congo',
        'Somalia', 'Ethiopia', 'Eritrea', 'Djibouti', 'Egypt',
        'Nigeria', 'Ghana', 'South Africa', 'Zambia', 'Mozambique',
        'Malawi', 'Zimbabwe', 'Angola', 'Cameroon', 'Botswana', 'Namibia',
        'United Kingdom', 'United States', 'Canada', 'Australia', 'India', 'Other',
    ];

    /**
     * Return the configuration the Flutter app needs for its splash screen,
     * login screen and legal links. Values are managed from the admin
     * dashboard (Site Settings) and cached for a short window.
     */
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
                'privacy_policy' => SiteSetting::get('privacy_policy', ''),
                'terms_of_use' => SiteSetting::get('terms_of_use', ''),
                'countries' => self::COUNTRIES,
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