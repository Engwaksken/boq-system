<?php

namespace App\Livewire\Admin;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class SiteSettings extends Component
{
    use WithFileUploads;

    public array $settings = [];

    public ?string $logoPath = null;

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

        $this->logoPath = $this->settings['logo'] ? asset('storage/' . $this->settings['logo']) : null;
    }

    public function save(Request $request): void
    {
        $this->validate([
            'settings.system_name' => 'required|string|max:255',
            'settings.currency' => 'required|string|max:10',
            'settings.language' => 'required|string|max:10',
            'settings.trial_duration' => 'required|integer|min:0',
            'settings.maintenance_mode' => 'boolean',
            'settings.logo' => 'nullable|string',
            'logoFile' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logoFile')) {
            $file = $request->file('logoFile');
            $filename = 'logo-' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('storage'), $filename);
            $this->settings['logo'] = $filename;
        }

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