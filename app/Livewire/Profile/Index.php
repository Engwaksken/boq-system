<?php

namespace App\Livewire\Profile;

use App\Models\HardwarePrice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads;

    public string $activeTab = 'overview';

    public array $tabs = [
        'overview' => 'Overview',
        'security' => 'Security',
        'preferences' => 'Preferences',
        'notifications' => 'Notifications',
        'hardware-bookmarks' => 'Hardware Bookmarks',
        'company' => 'Company Profile',
        'signature' => 'Signature',
    ];

    /** @var array<string, string|null> */
    public array $companyForm = [];

    public $companyLogo = null;

    public bool $removeCompanyLogo = false;

    public array $form = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'locale' => 'en',
        'timezone' => 'UTC',
        'avatar' => null,
    ];

    public array $passwordForm = [
        'current_password' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    public array $hardwareBookmarkForm = [
        'hardware_price_id' => null,
        'location' => '',
        'notes' => '',
    ];

    public ?int $editingBookmarkId = null;

    public bool $showBookmarkModal = false;

    /** @var array<string, bool|string> */
    public array $notificationPrefs = [];

    /** @var array<string, string|int> */
    public array $displayPrefs = [];

    public string $bookmarkLocationChoice = '';

    protected function rules(): array
    {
        return [
            'form.name' => [
                'required',
                'string',
                'max:255',
            ],

            'form.email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore(Auth::id()),
            ],

            'form.phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            // Any active language (was a hard-coded en/fr/sw list, so saving the
            // overview failed silently for e.g. Luganda users).
            'form.locale' => [
                'required',
                Rule::in($this->allowedLocales()),
            ],

            'form.timezone' => [
                'required',
                'timezone:all',
            ],

            'form.avatar' => [
                'nullable',
                'image',
                'max:2048',
            ],
        ];
    }

    /** @return list<string> */
    private function allowedLocales(): array
    {
        try {
            $codes = \App\Models\Language::query()->where('is_active', true)->pluck('code')->all();
        } catch (\Throwable) {
            $codes = [];
        }

        return array_values(array_unique(array_filter([...$codes, 'en', Auth::user()?->locale])));
    }

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user, 401);

        $this->form = [
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'phone' => $user->phone ?? '',
            'locale' => $user->locale ?: 'en',
            'timezone' => $user->timezone ?: 'UTC',
            'avatar' => null,
        ];

        $this->notificationPrefs = $user->notificationPreferences();
        $this->loadCompanyForm();
        $this->displayPrefs = $user->displayPreferences() + ['locale' => $user->locale ?: 'en'];

        $tab = (string) request()->query('tab', '');

        if (array_key_exists($tab, $this->tabs)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Picking a hardware price fills the location from that price.
     */
    public function updatedHardwareBookmarkFormHardwarePriceId($priceId): void
    {
        $price = $this->bookmarkablePrice((int) $priceId);
        $location = (string) ($price?->location ?? '');

        $this->hardwareBookmarkForm['location'] = $location;
        $this->bookmarkLocationChoice = $location;
    }

    public function updatedBookmarkLocationChoice(string $value): void
    {
        $this->hardwareBookmarkForm['location'] = $value === '__other__' ? '' : $value;
    }

    private function bookmarkablePrice(int $priceId): ?HardwarePrice
    {
        if ($priceId <= 0) {
            return null;
        }

        return HardwarePrice::query()
            ->visibleTo(Auth::user()?->organisation_id)
            ->find($priceId);
    }

    /**
     * Locations where the selected item (same name and price type) has a recorded price.
     *
     * @return list<string>
     */
    private function bookmarkLocationOptions(): array
    {
        $price = $this->bookmarkablePrice((int) ($this->hardwareBookmarkForm['hardware_price_id'] ?? 0));

        if (! $price) {
            return [];
        }

        return HardwarePrice::query()
            ->visibleTo(Auth::user()?->organisation_id)
            ->where('item_name', $price->item_name)
            ->where('price_type', $price->price_type)
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location')
            ->all();
    }

    /**
     * Display preferences save as soon as they change; language updates the user's locale.
     */
    public function updatedDisplayPrefs(): void
    {
        $this->validate([
            'displayPrefs.locale' => ['required', 'string', Rule::exists('languages', 'code')->where('is_active', true)],
            'displayPrefs.currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'displayPrefs.date_format' => ['required', Rule::in(array_keys(User::DATE_FORMATS))],
            'displayPrefs.number_format' => ['required', Rule::in(User::NUMBER_FORMATS)],
            'displayPrefs.per_page' => ['required', 'integer', Rule::in([10, 20, 50, 100])],
        ]);

        $user = Auth::user();
        abort_unless($user, 401);

        $user->forceFill([
            'locale' => $this->displayPrefs['locale'],
            'display_preferences' => [
                'currency' => $this->displayPrefs['currency'],
                'date_format' => $this->displayPrefs['date_format'],
                'number_format' => $this->displayPrefs['number_format'],
                'per_page' => (int) $this->displayPrefs['per_page'],
            ],
        ])->save();

        $this->form['locale'] = $this->displayPrefs['locale'];

        $this->dispatch('display-preferences-saved');
    }

    /**
     * Notification switches and the digest radio save as soon as they change.
     */
    public function updatedNotificationPrefs(): void
    {
        $defaults = User::DEFAULT_NOTIFICATION_PREFERENCES;

        $clean = [];
        foreach ($defaults as $key => $default) {
            $value = $this->notificationPrefs[$key] ?? $default;
            $clean[$key] = $key === 'frequency'
                ? (in_array($value, ['immediate', 'hourly', 'daily', 'weekly'], true) ? $value : 'immediate')
                : (bool) $value;
        }

        $user = Auth::user();
        abort_unless($user, 401);

        $user->forceFill(['notification_preferences' => $clean])->save();
        $this->notificationPrefs = $clean;

        $this->dispatch('notification-preferences-saved');
    }

    private function loadCompanyForm(): void
    {
        $profile = Auth::user()->companyProfile;

        $this->companyForm = collect(array_keys(\App\Services\CompanyProfileService::rules()))
            ->mapWithKeys(fn ($field) => [$field => (string) ($profile?->{$field} ?? '')])
            ->all();

        if (! $profile) {
            $this->companyForm['company_name'] = (string) (Auth::user()->organisation?->name ?? '');
            $this->companyForm['email'] = (string) Auth::user()->email;
            $this->companyForm['country'] = \App\Support\Regional::countryCode();
        }
    }

    public function updatedCompanyLogo(): void
    {
        $this->validateOnly('companyLogo', ['companyLogo' => \App\Services\CompanyProfileService::LOGO_RULES]);
        $this->removeCompanyLogo = false;
    }

    public function saveCompanyProfile(\App\Services\CompanyProfileService $service): void
    {
        try {
            $service->save(Auth::user(), $this->companyForm, $this->companyLogo, $this->removeCompanyLogo);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'logo' ? 'companyLogo' : 'companyForm.'.$field, $messages[0]);
            }

            return;
        }

        $this->reset(['companyLogo', 'removeCompanyLogo']);
        $this->loadCompanyForm();
        session()->flash('status', __('Company profile saved. New BOQ exports will use these details.'));
    }

    /**
     * Switch between profile tabs.
     */
    public function setActiveTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->tabs)) {
            return;
        }

        $this->activeTab = $tab;

        $this->resetValidation();
    }

    /**
     * Update basic profile information.
     */
    public function updateProfile(): void
    {
        $this->validate();

        $user = Auth::user();

        abort_unless($user, 401);

        $data = $this->form;

        if (! empty($data['avatar'])) {
            $path = app(\App\Services\FileCompressor::class)->storeImage(
                $data['avatar'],
                "avatars/{$user->id}",
                \App\Services\FileCompressor::AVATAR_MAX_SIDE,
            );

            if ($user->avatar_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
            }

            $data['avatar_path'] = $path;
        }

        unset($data['avatar']);

        $user->update($data);

        $this->reset('form.avatar');

        session()->flash(
            'status',
            'Profile updated successfully.'
        );
    }

    /**
     * Change account password.
     */
    public function updatePassword(): void
    {
        $validated = $this->validate([
            'passwordForm.current_password' => [
                'required',
                'current_password',
            ],

            'passwordForm.password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $user = Auth::user();

        abort_unless($user, 401);

        $user->update([
            'password' => $validated['passwordForm']['password'],
        ]);

        $this->reset('passwordForm');

        session()->flash(
            'status',
            'Password updated successfully.'
        );
    }

    /**
     * Open the hardware bookmark form.
     */
    public function bookmarkHardware(?int $hardwarePriceId = null): void
    {
        $this->hardwareBookmarkForm = [
            'hardware_price_id' => $hardwarePriceId ?: null,
            'location' => '',
            'notes' => '',
        ];

        $this->editingBookmarkId = null;

        $this->bookmarkLocationChoice = '';

        if ($hardwarePriceId) {
            $this->updatedHardwareBookmarkFormHardwarePriceId($hardwarePriceId);
        }

        $this->resetValidation();

        $this->showBookmarkModal = true;
    }

    /**
     * Edit an existing hardware bookmark.
     */
    public function editBookmark(int $bookmarkId): void
    {
        $user = Auth::user();

        abort_unless($user, 401);

        $bookmark = $user->hardwareBookmarks()
            ->findOrFail($bookmarkId);

        $this->hardwareBookmarkForm = [
            'hardware_price_id' => $bookmark->hardware_price_id,
            'location' => $bookmark->location ?? '',
            'notes' => $bookmark->notes ?? '',
        ];

        $this->editingBookmarkId = $bookmark->id;

        $this->bookmarkLocationChoice = in_array($bookmark->location, $this->bookmarkLocationOptions(), true)
            ? (string) $bookmark->location
            : ($bookmark->location ? '__other__' : '');

        $this->resetValidation();

        $this->showBookmarkModal = true;
    }

    /**
     * Save or update hardware bookmark.
     */
    public function saveBookmark(): void
    {
        $validated = $this->validate([
            'hardwareBookmarkForm.hardware_price_id' => [
                'required',
                'integer',
                'exists:hardware_prices,id',
            ],

            'hardwareBookmarkForm.location' => [
                'required',
                'string',
                'max:150',
            ],

            'hardwareBookmarkForm.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $user = Auth::user();

        abort_unless($user, 401);

        $data = $validated['hardwareBookmarkForm'];

        $duplicate = $user->hardwareBookmarks()
            ->where('hardware_price_id', $data['hardware_price_id'])
            ->where('location', $data['location'])
            ->when(
                $this->editingBookmarkId !== null,
                fn ($query) => $query->whereKeyNot($this->editingBookmarkId)
            )
            ->exists();

        if ($duplicate) {
            $this->addError(
                'hardwareBookmarkForm.location',
                'You have already bookmarked this item for this location.'
            );

            return;
        }

        if ($this->editingBookmarkId !== null) {
            $bookmark = $user->hardwareBookmarks()
                ->findOrFail($this->editingBookmarkId);

            $bookmark->update($data);

            session()->flash(
                'status',
                'Hardware bookmark updated successfully.'
            );
        } else {
            $user->hardwareBookmarks()->create($data);

            session()->flash(
                'status',
                'Hardware bookmarked successfully.'
            );
        }

        $this->hardwareBookmarkForm = [
            'hardware_price_id' => null,
            'location' => '',
            'notes' => '',
        ];

        $this->closeBookmarkModal();
    }

    /**
     * Close the hardware bookmark form.
     */
    public function closeBookmarkModal(): void
    {
        $this->showBookmarkModal = false;

        $this->editingBookmarkId = null;

        $this->hardwareBookmarkForm = [
            'hardware_price_id' => null,
            'location' => '',
            'notes' => '',
        ];

        $this->resetValidation();
    }

    /**
     * Delete hardware bookmark.
     */
    public function deleteBookmark(int $bookmarkId): void
    {
        $user = Auth::user();

        abort_unless($user, 401);

        $bookmark = $user->hardwareBookmarks()
            ->findOrFail($bookmarkId);

        $bookmark->delete();

        session()->flash(
            'status',
            'Hardware bookmark removed successfully.'
        );
    }

    /**
     * Remove a registered biometric (passkey) device from the account.
     */
    public function removeBiometricDevice(string $credentialId): void
    {
        $user = Auth::user();

        abort_unless($user, 401);

        $user->webAuthnCredentials()->whereKey($credentialId)->delete();

        session()->flash('status', 'Biometric sign-in removed for that device.');
    }

    /**
     * Open account deletion confirmation.
     */
    public function confirmDeleteAccount(): void
    {
        $this->resetValidation();

        $this->dispatch('openModal', [
            'title' => 'Delete Account',
            'size' => 'md',
        ]);
    }

    /**
     * Delete account.
     *
     * Keep destructive account deletion disabled until the
     * production deletion workflow is fully implemented.
     */
    public function deleteAccount(string $password): void
    {
        session()->flash(
            'status',
            'Account deletion is currently unavailable.'
        );

        $this->dispatch('closeModal');
    }

    public function render()
    {
        $user = Auth::user();

        abort_unless($user, 401);

        $user->load([
            'hardwareBookmarks.hardwarePrice',
        ]);

        return view('livewire.profile.index', [
            'user' => $user,
            'biometricDevices' => $this->activeTab === 'security'
                ? $user->webAuthnCredentials()->latest()->get()
                : collect(),
            'bookmarkedHardware' => $user->hardwareBookmarks()
                ->with('hardwarePrice')
                ->latest()
                ->get(),
            'bookmarkLocations' => $this->showBookmarkModal ? $this->bookmarkLocationOptions() : [],
            'bookmarkablePrices' => $this->activeTab === 'hardware-bookmarks'
                ? HardwarePrice::active()
                    ->visibleTo($user->organisation_id)
                    ->orderBy('item_name')
                    ->get()
                : collect(),
        ]);
    }
}