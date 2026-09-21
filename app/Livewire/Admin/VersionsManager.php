<?php

namespace App\Livewire\Admin;

use App\Models\ProductVersion;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class VersionsManager extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;
    public int $perPage = 10;

    public array $form = [
        'version_number' => '', 'name' => '', 'release_notes' => '', 'release_date' => '',
        'classification' => 'minor', 'included_features' => '', 'requires_topup' => false,
        'eligible_plans' => '', 'minimum_supported_version' => '', 'is_active' => true,
    ];

    public function create(): void { $this->resetForm(); $this->showForm = true; }

    public function edit(int $id): void
    {
        $version = ProductVersion::findOrFail($id);
        $this->editingId = $id;
        $this->form['version_number'] = $version->version_number;
        $this->form['name'] = $version->name;
        $this->form['release_notes'] = $version->release_notes;
        $this->form['release_date'] = $version->release_date?->toDateString();
        $this->form['classification'] = $version->classification;
        $this->form['included_features'] = implode(',', $version->included_features ?? []);
        $this->form['requires_topup'] = $version->requires_topup;
        $this->form['eligible_plans'] = implode(',', $version->eligible_plans ?? []);
        $this->form['minimum_supported_version'] = $version->minimum_supported_version;
        $this->form['is_active'] = $version->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.version_number' => ['required', 'string', 'max:50', 'regex:/^[0-9]+\.[0-9]+$/'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.release_notes' => ['nullable', 'string'],
            'form.release_date' => ['nullable', 'date'],
            'form.classification' => ['required', 'in:major,minor,patch'],
            'form.included_features' => ['nullable', 'string'],
            'form.requires_topup' => ['boolean'],
            'form.eligible_plans' => ['nullable', 'string'],
            'form.minimum_supported_version' => ['nullable', 'string', 'max:50'],
            'form.is_active' => ['boolean'],
        ])['form'];

        $data = [
            'version_number' => $validated['version_number'],
            'name' => $validated['name'],
            'release_notes' => $validated['release_notes'],
            'release_date' => $validated['release_date'],
            'classification' => $validated['classification'],
            'included_features' => $this->splitList($validated['included_features']),
            'requires_topup' => $validated['requires_topup'],
            'eligible_plans' => $this->splitList($validated['eligible_plans']),
            'minimum_supported_version' => $validated['minimum_supported_version'] ?: null,
            'is_active' => $validated['is_active'],
        ];

        ProductVersion::updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('message', $this->editingId ? 'Product version updated successfully.' : 'Product version created successfully.');
        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        $version = ProductVersion::findOrFail($id);
        $version->update(['is_active' => ! $version->is_active]);
    }

    public function destroy(int $id): void
    {
        $version = ProductVersion::findOrFail($id);

        if ($version->subscriptions()->exists()) {
            $version->update(['is_active' => false]);
            session()->flash('message', 'Version is in use. It was deactivated instead of being deleted.');
            return;
        }

        $version->delete();
        session()->flash('message', 'Product version deleted.');
    }

    public function cancel(): void { $this->showForm = false; $this->resetForm(); }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->reset('form');
        $this->form['classification'] = 'minor';
        $this->form['requires_topup'] = false;
        $this->form['is_active'] = true;
    }

    public function updatedSearch(): void { $this->resetPage(); }

    private function splitList(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            explode(',', $value)
        )));
    }

    public function render()
    {
        return view('livewire.admin.versions-manager', [
            'versions' => ProductVersion::query()
                ->when($this->search, function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('version_number', 'like', '%'.$this->search.'%');
                })
                ->orderByDesc('release_date')
                ->paginate($this->perPage),
        ]);
    }
}