<?php

namespace App\Livewire\Admin;

use App\Models\Currency;
use App\Models\Language;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

    /**
     * Livewire update requests skip route middleware, so re-check on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    #[On('default-currency-changed')]
    public function syncDefaultCurrency(string $code): void
    {
        $this->settings['currency'] = $code;
    }

    #[On('default-language-changed')]
    public function syncDefaultLanguage(string $code): void
    {
        $this->settings['language'] = $code;
    }

    public function mount(): void
    {
        $this->settings = [
            'system_name' => SiteSetting::get('system_name', 'Civil Works AI BOQ Platform'),
            'currency' => SiteSetting::get('currency', \App\Support\Regional::currency()),
            'language' => SiteSetting::get('language', 'en'),
            'country' => (string) SiteSetting::get('country', ''),
            'market_location' => (string) SiteSetting::get('market_location', ''),
            'timezone' => (string) SiteSetting::get('timezone', config('app.timezone', 'UTC')),
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
            'settings.currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'settings.language' => ['required', 'string', 'max:10', Rule::exists('languages', 'code')],
            'settings.country' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'iso2')],
            'settings.market_location' => ['nullable', 'string', 'max:150'],
            'settings.timezone' => ['required', 'timezone:all'],
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
            'logoFile' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'faviconFile' => 'nullable|mimes:png,jpg,jpeg,webp,ico|max:1024',
        ]);

        if ($this->logoFile) {
            $this->settings['logo'] = $this->logoFile->storePubliclyAs(
                'site',
                'logo-'.now()->timestamp.'.'.$this->logoFile->guessExtension(),
                'public'
            );
            $this->logoUrl = asset('storage/'.$this->settings['logo']);
        }

        if ($this->faviconFile) {
            $this->settings['favicon'] = $this->faviconFile->storePubliclyAs(
                'site',
                'favicon-'.now()->timestamp.'.'.($this->faviconFile->guessExtension() ?: 'ico'),
                'public'
            );
            $this->faviconUrl = asset('storage/'.$this->settings['favicon']);
        }

        foreach ($this->settings as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');
            SiteSetting::set($key, $value, 'general', $type);
        }

        // Keep the currency/language tables' default flags in step with the settings.
        DB::transaction(function () {
            Currency::query()->update(['is_default' => false]);
            Currency::where('code', $this->settings['currency'])->update(['is_default' => true, 'is_active' => true]);
            Language::query()->update(['is_default' => false]);
            Language::where('code', $this->settings['language'])->update(['is_default' => true, 'is_active' => true]);
        });

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
        return view('livewire.admin.site-settings', [
            'languageOptions' => Language::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('code', $this->settings['language'] ?? null))
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->all(),
        ]);
    }
}