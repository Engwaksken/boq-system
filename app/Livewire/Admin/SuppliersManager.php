<?php

namespace App\Livewire\Admin;

use App\Models\Supplier;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SuppliersManager extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public bool $showForm = false;
    public ?int $editingId = null;

    public array $form = [
        'name' => '', 'contact_name' => '', 'email' => '', 'phone' => '',
        'location' => '', 'region' => '', 'country' => '', 'currency' => 'UGX',
        'materials_text' => '', 'notes' => '', 'preferred_language' => 'en', 'rating' => 0,
    ];

    public function create(): void { $this->resetForm(); $this->showForm = true; }

    public function edit(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $key) {
            $this->form[$key] = $supplier->{$key} ?? $this->form[$key];
        }
        $this->form['materials_text'] = is_array($supplier->materials) ? implode(', ', $supplier->materials) : '';
        $this->form['rating'] = (string) (float) ($supplier->rating ?? 0);
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.contact_name' => ['nullable', 'string', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.location' => ['nullable', 'string', 'max:255'],
            'form.region' => ['nullable', 'string', 'max:100'],
            'form.country' => ['nullable', 'string', 'size:2'],
            'form.currency' => ['required', 'string', 'size:3'],
            'form.notes' => ['nullable', 'string'],
            'form.preferred_language' => ['required', 'string', 'max:5'],
            'form.rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
        ])['form'];

        $materials = array_values(array_filter(array_map('trim', explode(',', $validated['materials_text']))));

        $payload = array_merge($validated, [
            'contact_name' => $validated['contact_name'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'location' => $validated['location'] ?: null,
            'region' => $validated['region'] ?: null,
            'country' => $validated['country'] ?: null,
            'materials' => $materials,
            'rating' => $validated['rating'] ?: null,
        ]);
        unset($payload['materials_text']);

        $supplier = Supplier::updateOrCreate(['id' => $this->editingId], $payload);
        if ($supplier->wasRecentlyCreated) {
            $supplier->update(['code' => 'SUP-'.Str::upper(Str::random(6))]);
        }
        session()->flash('message', $this->editingId ? 'Supplier updated.' : 'Supplier created.');
        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update(['is_active' => ! $supplier->is_active]);
    }

    public function deactivate(int $id): void
    {
        Supplier::findOrFail($id)->update(['is_active' => false]);
        session()->flash('message', 'Supplier deactivated. Historical quotations were preserved.');
    }

    public function cancel(): void { $this->showForm = false; $this->resetForm(); }
    private function resetForm(): void { $this->editingId = null; $this->reset('form'); $this->form['currency']='UGX'; $this->form['preferred_language']='en'; }
    public function updatedSearch(): void { $this->resetPage(); }

    public function render()
    {
        return view('livewire.admin.suppliers-manager', [
            'suppliers' => Supplier::query()
                ->withCount('rates')
                ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$this->search.'%')->orWhere('contact_name', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%')))
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }
}