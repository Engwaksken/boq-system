<?php

namespace App\Livewire\Profile;

use App\Livewire\Components\Modal;
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
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore(Auth::id())],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.locale' => ['required', Rule::in(['en', 'fr', 'sw'])],
            'form.timezone' => ['required', 'timezone:all'],
            'form.avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function mount(): void
    {
        $user = Auth::user();
        $this->form = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'avatar' => null,
        ];
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updateProfile(): void
    {
        $this->validate();

        $user = Auth::user();
        $data = $this->form;

        if ($data['avatar']) {
            $path = $data['avatar']->store('avatars', 'public');
            $data['avatar'] = $path;
        } else {
            unset($data['avatar']);
        }

        $user->update($data);

        session()->flash('modal_success', 'Profile updated successfully.');
        $this->reset('form.avatar');
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'passwordForm.current_password' => ['required', 'current_password'],
            'passwordForm.password' => ['required', 'confirmed', 'min:8'],
        ]);

        Auth::user()->update(['password' => $validated['passwordForm']['password']]);

        session()->flash('modal_success', 'Password updated successfully.');
        $this->reset('passwordForm');
    }

    public function bookmarkHardware(int $hardwarePriceId): void
    {
        $this->hardwareBookmarkForm['hardware_price_id'] = $hardwarePriceId;
        $this->editingBookmarkId = null;
        $this->resetValidation();
        $this->dispatch('openModal', ['title' => 'Bookmark Hardware', 'size' => 'md']);
    }

    public function editBookmark(int $bookmarkId): void
    {
        $bookmark = Auth::user()->hardwareBookmarks()->findOrFail($bookmarkId);
        $this->hardwareBookmarkForm = [
            'hardware_price_id' => $bookmark->hardware_price_id,
            'location' => $bookmark->location,
            'notes' => $bookmark->notes,
        ];
        $this->editingBookmarkId = $bookmarkId;
        $this->dispatch('openModal', ['title' => 'Edit Bookmark', 'size' => 'md']);
    }

    public function saveBookmark(): void
    {
        $this->validate([
            'hardwareBookmarkForm.hardware_price_id' => ['required', 'exists:hardware_prices,id'],
            'hardwareBookmarkForm.location' => ['required', 'string', 'max:150'],
            'hardwareBookmarkForm.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();

        if ($this->editingBookmarkId) {
            $bookmark = $user->hardwareBookmarks()->findOrFail($this->editingBookmarkId);
            $bookmark->update($this->hardwareBookmarkForm);
            session()->flash('modal_success', 'Bookmark updated.');
        } else {
            $user->hardwareBookmarks()->create($this->hardwareBookmarkForm);
            session()->flash('modal_success', 'Hardware bookmarked.');
        }

        $this->reset('hardwareBookmarkForm', 'editingBookmarkId');
        $this->dispatch('closeModal');
    }

    public function deleteBookmark(int $bookmarkId): void
    {
        Auth::user()->hardwareBookmarks()->findOrFail($bookmarkId)->delete();
        session()->flash('modal_success', 'Bookmark removed.');
    }

    public function confirmDeleteAccount(): void
    {
        $this->dispatch('openModal', ['title' => 'Delete Account', 'size' => 'md']);
    }

    public function deleteAccount(string $password): void
    {
        $this->validate([
            'password' => ['required', 'current_password'],
        ], [], [
            'password.required' => 'The password field is required.',
            'password.current_password' => 'The password is incorrect.',
        ]);

        session()->flash('errors', [
            'userDeletion' => [
                'password' => ['The password is incorrect.'],
            ],
        ]);

        session()->flash('status', 'Account deletion failed. Please try again.');
        $this->redirect('/profile');
    }

    public function render()
    {
        $user = Auth::user()->load('hardwareBookmarks.hardwarePrice');
        $bookmarkedHardware = $user->hardwareBookmarks;

        return view('livewire.profile.index', [
            'bookmarkedHardware' => $bookmarkedHardware,
        ]);
    }
}