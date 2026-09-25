<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteSetting::set('system_name', 'Civil Works AI BOQ Platform', 'general', 'string');
        SiteSetting::set('currency', 'UGX', 'general', 'string');
        SiteSetting::set('language', 'en', 'general', 'string');
        SiteSetting::set('trial_duration', 7, 'general', 'integer');
        SiteSetting::set('maintenance_mode', false, 'general', 'boolean');
        SiteSetting::set('logo', '', 'general', 'string');
        SiteSetting::set('splash_enabled', true, 'mobile', 'boolean');
        SiteSetting::set('splash_duration', 2000, 'mobile', 'integer');
        SiteSetting::set('splash_message', '', 'mobile', 'string');
        SiteSetting::set('login_title', '', 'mobile', 'string');
        SiteSetting::set('login_subtitle', '', 'mobile', 'string');
        SiteSetting::set('allow_registration', true, 'mobile', 'boolean');
        SiteSetting::set('allow_forgot_password', true, 'mobile', 'boolean');
        SiteSetting::set('privacy_policy', '', 'legal', 'string');
        SiteSetting::set('terms_of_use', '', 'legal', 'string');
    }
}
