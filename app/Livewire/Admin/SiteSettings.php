<?php

namespace App\Livewire\Admin;

use App\Models\SiteSetting;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SiteSettings extends Component
{
    public array $settings = [];

    public function mount()
    {
        $this->settings = [
            'system_name' => SiteSetting::get('system_name', 'Civil Works AI BOQ Platform'),
            'currency' => SiteSetting::get('currency', 'UGX'),
            'language' => SiteSetting::get('language', 'en'),
            'trial_duration' => SiteSetting::get('trial_duration', 7),
            'maintenance_mode' => SiteSetting::get('maintenance_mode', false),
            'logo' => SiteSetting::get('logo', ''),
        ];
    }

    public function save()
    {
        $this->validate([
            'settings.system_name' => 'required|string|max:255',
            'settings.currency' => 'required|string|max:10',
            'settings.language' => 'required|string|max:10',
            'settings.trial_duration' => 'required|integer|min:0',
            'settings.maintenance_mode' => 'boolean',
            'settings.logo' => 'nullable|string',
        ]);

        foreach ($this->settings as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');
            SiteSetting::set($key, $value, 'general', $type);
        }

        session()->flash('message', 'Site settings updated successfully.');
    }

    public function render()
    {
        return view('livewire.admin.site-settings');
    }
}
