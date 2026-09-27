<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Language;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class LanguagesManager extends Component
{
    use WithBulkSelection;
    use WithPagination;

    public bool $showForm = false;
    public ?int $editingId = null;

    public array $form = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public function mount(): void
    {
        $this->resetForm();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $language = Language::findOrFail($id);

        $this->editingId = $language->id;
        $this->form = $language->only(['code', 'name', 'native_name', 'direction', 'date_format', 'is_active']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->form['code'] = strtolower(trim((string) ($this->form['code'] ?? '')));

        $data = $this->validate([
            'form.code' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/', Rule::unique('languages', 'code')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'max:100'],
            'form.native_name' => ['required', 'string', 'max:100'],
            'form.direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'form.date_format' => ['required', 'string', 'max:32'],
            'form.is_active' => ['boolean'],
        ], [
            'form.code.regex' => 'Use an ISO language code such as en, lg, sw or fr.',
        ])['form'];

        $language = $this->editingId ? Language::findOrFail($this->editingId) : new Language();

        if ($language->is_default) {
            $data['is_active'] = true;
        }

        $language->fill($data)->save();

        session()->flash('language-message', $this->editingId ? 'Language updated.' : 'Language added.');
        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        $language = Language::findOrFail($id);

        if ($language->is_default) {
            session()->flash('language-message', 'The default language cannot be deactivated.');

            return;
        }

        $language->update(['is_active' => ! $language->is_active]);
    }

    public function setDefault(int $id): void
    {
        $language = Language::findOrFail($id);

        DB::transaction(function () use ($language) {
            Language::query()->update(['is_default' => false]);
            $language->update(['is_default' => true, 'is_active' => true]);
            SiteSetting::set('language', $language->code);
        });

        $this->dispatch('default-language-changed', code: $language->code);
        session()->flash('language-message', "{$language->name} is now the default language.");
    }

    public function bulkDelete(): void
    {
        $count = Language::whereKey($this->selectedIds())->where('is_default', false)->delete();

        $this->finishBulkAction($count, 'deleted (the default language is never deleted)', 'language-message');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = ['code' => '', 'name' => '', 'native_name' => '', 'direction' => 'ltr', 'date_format' => 'Y-m-d', 'is_active' => true];
    }

    public function render()
    {
        return view('livewire.admin.languages-manager', [
            'languages' => Language::orderByDesc('is_default')->orderBy('name')->paginate(10, pageName: 'languages'),
        ]);
    }
}
