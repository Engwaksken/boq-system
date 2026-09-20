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
        ];

        $this->logoUrl = $this->settings['logo'] ? asset('storage/'.$this->settings['logo']) : '';
        $this->faviconUrl = $this->settings['favicon'] ? asset('storage/'.$this->settings['favicon']) : '';
    }

    public function save(): void
    {
        $this->validate([
            'settings.system_name' => 'required|string|max:255',
            'settings.currency' => 'required|string|max:10',
            'settings.language' => 'required|string|max:10',
            'settings.trial_duration' => 'required|integer|min:0',
            'settings.maintenance_mode' => 'boolean',
            'settings.logo' => 'nullable|string|max:255',
            'settings.favicon' => 'nullable|string|max:255',
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