<?php

namespace App\Livewire\Admin;

use App\Models\SiteSetting;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class SiteSettings extends Component
{
    use WithFileUploads;

    public array $settings = [];

    public $logoFile = null;

    public $faviconFile = null;

    public string $logoUrl = '';

    public string $faviconUrl = '';

    public function mount(): void
    {
        $this->settings = [
            'system_name' => SiteSetting::get('system_name', 'Civil Works AI BOQ Platform'),
            'currency' => SiteSetting::get('currency', 'UGX'),
            'language' => SiteSetting::get('language', 'en'),
            'trial_duration' => SiteSetting::get('trial_duration', 7),
            'maintenance_mode' => SiteSetting::get('maintenance_mode', false),
            'logo' => SiteSetting::get('logo', ''),
            'favicon' => SiteSetting::get('favicon', ''),
            'splash_enabled' => SiteSetting::get('splash_enabled', true),
            'splash_duration' => SiteSetting::get('splash_duration', 2000),
            'splash_message' => SiteSetting::get('splash_message', ''),
            'login_title' => SiteSetting::get('login_title', ''),
            'login_subtitle' => SiteSetting::get('login_subtitle', ''),
            'allow_registration' => SiteSetting::get('allow_registration', true),
            'allow_forgot_password' => SiteSetting::get('allow_forgot_password', true),
            'privacy_policy' => SiteSetting::get('privacy_policy', ''),
            'terms_of_use' => SiteSetting::get('terms_of_use', ''),
        ];

        $this->logoUrl = $this->settings['logo'] ? asset('storage/'.$this->settings['logo']) : '';
        $this->faviconUrl = $this->settings['favicon'] ? asset('storage/'.$this->settings['favicon']) : '';
    }

    public function save(): void
    {
        // Livewire does not send unchecked checkboxes, so normalise them first.
        foreach (['maintenance_mode', 'splash_enabled', 'allow_registration', 'allow_forgot_password'] as $boolKey) {
            $this->settings[$boolKey] = (bool) ($this->settings[$boolKey] ?? false);
        }

        $this->validate([
            'settings.system_name' => 'required|string|max:255',
            'settings.currency' => 'required|string|max:10',
            'settings.language' => 'required|string|max:10',
            'settings.trial_duration' => 'required|integer|min:0',
            'settings.maintenance_mode' => 'boolean',
            'settings.logo' => 'nullable|string|max:255',
            'settings.favicon' => 'nullable|string|max:255',
            'settings.splash_enabled' => 'boolean',
            'settings.splash_duration' => 'required|integer|min:500|max:10000',
            'settings.splash_message' => 'nullable|string|max:255',
            'settings.login_title' => 'nullable|string|max:255',
            'settings.login_subtitle' => 'nullable|string|max:500',
            'settings.allow_registration' => 'boolean',
            'settings.allow_forgot_password' => 'boolean',
            'settings.privacy_policy' => 'nullable|string',
            'settings.terms_of_use' => 'nullable|string',
            'logoFile' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'faviconFile' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg,ico|max:1024',
        ]);

        if ($this->logoFile) {
            $this->settings['logo'] = $this->logoFile->storePubliclyAs(
                'site',
                'logo-'.now()->timestamp.'.'.$this->logoFile->getClientOriginalExtension(),
                'public'
            );
            $this->logoUrl = asset('storage/'.$this->settings['logo']);
        }

        if ($this->faviconFile) {
            $this->settings['favicon'] = $this->faviconFile->storePubliclyAs(
                'site',
                'favicon-'.now()->timestamp.'.'.$this->faviconFile->getClientOriginalExtension(),
                'public'
            );
            $this->faviconUrl = asset('storage/'.$this->settings['favicon']);
        }

        foreach ($this->settings as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');
            SiteSetting::set($key, $value, 'general', $type);
        }

        cache()->forget('mobile_config');

        $this->reset('logoFile', 'faviconFile');

        session()->flash('message', 'Site settings updated successfully.');
    }

    public function removeLogo(): void
    {
        $this->settings['logo'] = '';
        $this->logoUrl = '';
    }

    public function removeFavicon(): void
    {
        $this->settings['favicon'] = '';
        $this->faviconUrl = '';
    }

    public function render()
    {
        return view('livewire.admin.site-settings');
    }
}