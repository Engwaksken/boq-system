<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Language;
use App\Models\SiteSetting;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class LanguagesManager extends Component
{
    use \App\Livewire\Concerns\ExportsTables;
    use WithBulkSelection;
    use WithPagination;

    public bool $showForm = false;
    public ?int $editingId = null;

    public array $form = [];

    /** Language code being translated, or null when the editor is closed. */
    public ?string $translating = null;

    public string $translationSearch = '';

    public bool $untranslatedOnly = false;

    public int $translationPage = 1;

    /** @var array<string, string> sha1(key) => edited value for the visible page */
    public array $drafts = [];

    private const TRANSLATIONS_PER_PAGE = 25;

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

    public function openTranslations(int $id): void
    {
        $this->translating = Language::findOrFail($id)->code;
        $this->translationSearch = '';
        $this->untranslatedOnly = false;
        $this->translationPage = 1;
        $this->loadDrafts();
    }

    public function closeTranslations(): void
    {
        $this->translating = null;
        $this->drafts = [];
    }

    public function updatedTranslationSearch(): void
    {
        $this->translationPage = 1;
        $this->loadDrafts();
    }

    public function updatedUntranslatedOnly(): void
    {
        $this->translationPage = 1;
        $this->loadDrafts();
    }

    public function translationPageTo(int $page): void
    {
        $this->translationPage = max(1, $page);
        $this->loadDrafts();
    }

    public function saveTranslations(): void
    {
        abort_unless($this->translating !== null, 404);

        $count = 0;
        foreach ($this->visibleKeys() as $key) {
            $hash = sha1($key);
            if (! array_key_exists($hash, $this->drafts)) {
                continue;
            }

            $new = trim((string) $this->drafts[$hash]);
            $current = $this->currentValue($key);

            if ($new !== $current) {
                // Saving the file's own value (or blank) removes the override.
                Translation::put($this->translating, $key, $new === $this->fileValue($key) ? '' : $new);
                $count++;
            }
        }

        session()->flash('language-message', "{$count} translation(s) saved.");
        $this->loadDrafts();
    }

    /** @return array<string, string> English source strings (lang/en.json). */
    private function sourceStrings(): array
    {
        static $source = null;

        return $source ??= (array) json_decode((string) @file_get_contents(lang_path('en.json')), true);
    }

    /** @return array<string, string> */
    private function fileStrings(): array
    {
        static $cache = [];

        return $cache[$this->translating] ??= (array) json_decode((string) @file_get_contents(lang_path($this->translating.'.json')), true);
    }

    private function fileValue(string $key): string
    {
        return (string) ($this->fileStrings()[$key] ?? '');
    }

    private function currentValue(string $key): string
    {
        return (string) (Translation::linesFor($this->translating)[$key] ?? $this->fileValue($key));
    }

    /** @return list<string> */
    private function filteredKeys(): array
    {
        $search = mb_strtolower(trim($this->translationSearch));

        return collect(array_keys($this->sourceStrings()))
            ->filter(fn (string $key) => $search === '' || str_contains(mb_strtolower($key), $search) || str_contains(mb_strtolower($this->currentValue($key)), $search))
            ->when($this->untranslatedOnly, fn ($keys) => $keys->filter(fn (string $key) => $this->currentValue($key) === '' || ($this->translating !== 'en' && $this->currentValue($key) === $key)))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function visibleKeys(): array
    {
        return array_slice($this->filteredKeys(), ($this->translationPage - 1) * self::TRANSLATIONS_PER_PAGE, self::TRANSLATIONS_PER_PAGE);
    }

    private function loadDrafts(): void
    {
        $this->drafts = collect($this->visibleKeys())
            ->mapWithKeys(fn (string $key) => [sha1($key) => $this->currentValue($key)])
            ->all();
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
        $translationRows = [];
        $translationPages = 1;

        if ($this->translating !== null) {
            $total = count($this->filteredKeys());
            $translationPages = max(1, (int) ceil($total / self::TRANSLATIONS_PER_PAGE));
            $translationRows = array_map(fn (string $key) => ['key' => $key, 'hash' => sha1($key)], $this->visibleKeys());
        }

        return view('livewire.admin.languages-manager', [
            'languages' => Language::orderByDesc('is_default')->orderBy('name')->paginate($this->exportPageSize(10), pageName: 'languages'),
            'translationRows' => $translationRows,
            'translationPages' => $translationPages,
        ]);
    }
}
