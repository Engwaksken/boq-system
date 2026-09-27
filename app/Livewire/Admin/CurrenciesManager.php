<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Currency;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CurrenciesManager extends Component
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
        $currency = Currency::findOrFail($id);

        $this->editingId = $currency->id;
        $this->form = $currency->only(['code', 'name', 'symbol', 'decimal_places', 'is_active', 'sort_order']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->form['code'] = strtoupper(trim((string) ($this->form['code'] ?? '')));

        $data = $this->validate([
            'form.code' => ['required', 'alpha', 'size:3', Rule::unique('currencies', 'code')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'max:100'],
            'form.symbol' => ['nullable', 'string', 'max:10'],
            'form.decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'form.is_active' => ['boolean'],
            'form.sort_order' => ['required', 'integer', 'min:0'],
        ])['form'];

        $currency = $this->editingId ? Currency::findOrFail($this->editingId) : new Currency();

        if ($currency->is_default) {
            $data['is_active'] = true;
        }

        $currency->fill($data)->save();

        session()->flash('currency-message', $this->editingId ? 'Currency updated.' : 'Currency added.');
        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        $currency = Currency::findOrFail($id);

        if ($currency->is_default) {
            session()->flash('currency-message', 'The default currency cannot be deactivated.');

            return;
        }

        $currency->update(['is_active' => ! $currency->is_active]);
    }

    public function setDefault(int $id): void
    {
        $currency = Currency::findOrFail($id);

        DB::transaction(function () use ($currency) {
            Currency::query()->update(['is_default' => false]);
            $currency->update(['is_default' => true, 'is_active' => true]);
            SiteSetting::set('currency', $currency->code);
        });

        $this->dispatch('default-currency-changed', code: $currency->code);
        session()->flash('currency-message', "{$currency->code} is now the default currency.");
    }

    public function bulkDelete(): void
    {
        $count = Currency::whereKey($this->selectedIds())->where('is_default', false)->delete();

        $this->finishBulkAction($count, 'deleted (the default currency is never deleted)', 'currency-message');
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
        $this->form = ['code' => '', 'name' => '', 'symbol' => '', 'decimal_places' => 2, 'is_active' => true, 'sort_order' => 100];
    }

    public function render()
    {
        return view('livewire.admin.currencies-manager', [
            'currencies' => Currency::orderByDesc('is_default')->orderBy('sort_order')->orderBy('code')->paginate(10, pageName: 'currencies'),
        ]);
    }
}
