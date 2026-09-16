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
        SiteSetting::set('trial_duration', 14, 'general', 'integer');
        SiteSetting::set('maintenance_mode', false, 'general', 'boolean');
        SiteSetting::set('logo', '', 'general', 'string');
    }
}
