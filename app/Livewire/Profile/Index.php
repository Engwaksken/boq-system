<?php

namespace App\Livewire\Profile;

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
    ];

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
                Rule::unique(User::class)->ignore(Auth::id()),
            ],

            'form.phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'form.locale' => [
                'required',
                Rule::in(['en', 'fr', 'sw']),
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
            $path = $data['avatar']->store(
                "avatars/{$user->id}",
                'public'
            );

            $data['avatar'] = $path;
        } else {
            unset($data['avatar']);
        }

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

        $this->resetValidation();

        $this->dispatch('openModal', [
            'title' => 'Bookmark Hardware',
            'size' => 'md',
        ]);
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

        $this->resetValidation();

        $this->dispatch('openModal', [
            'title' => 'Edit Bookmark',
            'size' => 'md',
        ]);
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

        $this->editingBookmarkId = null;

        $this->resetValidation();

        $this->dispatch('closeModal');
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
            'bookmarkedHardware' => $user->hardwareBookmarks,
        ]);
    }
}