<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Interface preferences: theme mode, accent colour, density, sidebar default
 * and font size. The page previews every change on <html> straight away
 * (Alpine) and stores the values in users.preferences on Save.
 */
#[Layout('layouts.app')]
class Preferences extends Component
{
    public string $theme = 'light';

    public string $accent = 'green';

    public string $density = 'comfortable';

    public string $sidebar = 'expanded';

    public string $font = 'default';

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->fill($user->interfacePreferences());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach (User::INTERFACE_PREFERENCES as $key => $values) {
            $rules[$key] = ['required', 'string', Rule::in($values)];
        }

        return $rules;
    }

    public function save(): void
    {
        $validated = $this->validate();

        /** @var User $user */
        $user = auth()->user();
        $user->forceFill([
            'preferences' => array_merge($user->preferences ?? [], $validated),
        ])->save();

        session()->flash('success', __('Your preferences have been saved.'));

        $this->redirectRoute('preferences');
    }

    /** Put every setting back to its default (previewed, saved on Save). */
    public function restoreDefaults(): void
    {
        $this->resetValidation();

        $defaults = [];

        foreach (User::INTERFACE_PREFERENCES as $key => $values) {
            $defaults[$key] = $values[0];
        }

        $this->fill($defaults);
        $this->dispatch('preferences-preview', preferences: $defaults);
    }

    public function render()
    {
        return view('livewire.preferences', [
            'accents' => [
                'green' => __('Green'),
                'blue' => __('Blue'),
                'indigo' => __('Indigo'),
                'purple' => __('Purple'),
                'teal' => __('Teal'),
                'orange' => __('Orange'),
                'rose' => __('Rose'),
            ],
        ])->title(__('Preferences'));
    }
}
