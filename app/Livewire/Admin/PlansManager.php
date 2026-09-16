<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PlansManager extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;
    public int $perPage = 10;

    public array $form = [
        'name' => '', 'code' => '', 'description' => '', 'type' => 'monthly', 'duration_days' => 30,
        'price' => 0, 'currency' => 'UGX', 'is_active' => true, 'is_archived' => false,
        'has_trial' => false, 'trial_days' => 7, 'max_users' => 1, 'max_projects' => 5,
        'max_boqs' => 20, 'max_storage_bytes' => 104857600, 'max_ai_credits' => 100,
        'max_ocr_pages' => 50, 'max_translations' => 50, 'auto_renewal' => false,
        'grace_period_days' => 3, 'display_order' => 0,
    ];

    public function create(): void { $this->resetForm(); $this->showForm = true; }

    public function edit(int $id): void
    {
        $plan = Plan::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $key) {
            $this->form[$key] = $plan->{$key} ?? $this->form[$key];
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => ['required','string','max:255'],
            'form.code' => ['nullable','string','max:100','unique:plans,code,'.($this->editingId ?? 'NULL')],
            'form.description' => ['nullable','string'],
            'form.type' => ['required','in:monthly,three_month,six_month,annual,one_time,lifetime'],
            'form.duration_days' => ['nullable','integer','min:0'],
            'form.price' => ['required','numeric','min:0'],
            'form.currency' => ['required','string','size:3'],
            'form.is_active' => ['boolean'], 'form.is_archived' => ['boolean'], 'form.has_trial' => ['boolean'],
            'form.trial_days' => ['integer','min:0','max:365'], 'form.max_users' => ['nullable','integer','min:1'],
            'form.max_projects' => ['nullable','integer','min:0'], 'form.max_boqs' => ['nullable','integer','min:0'],
            'form.max_storage_bytes' => ['nullable','integer','min:0'], 'form.max_ai_credits' => ['nullable','integer','min:0'],
            'form.max_ocr_pages' => ['nullable','integer','min:0'], 'form.max_translations' => ['nullable','integer','min:0'],
            'form.auto_renewal' => ['boolean'], 'form.grace_period_days' => ['integer','min:0','max:90'],
            'form.display_order' => ['integer','min:0'],
        ])['form'];

        $validated['code'] = $validated['code'] ?: Str::slug($validated['name']);
        Plan::updateOrCreate(['id' => $this->editingId], $validated);
        session()->flash('message', $this->editingId ? 'Plan updated successfully.' : 'Plan created successfully.');
        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
    }

    public function archive(int $id): void
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_archived' => true, 'is_active' => false]);
        session()->flash('message', 'Plan archived. Existing subscriptions were preserved.');
    }

    public function cancel(): void { $this->showForm = false; $this->resetForm(); }
    private function resetForm(): void { $this->editingId = null; $this->reset('form'); $this->form['currency']='UGX'; $this->form['type']='monthly'; $this->form['duration_days']=30; $this->form['trial_days']=7; $this->form['is_active']=true; }
    public function updatedSearch(): void { $this->resetPage(); }

    public function render()
    {
        return view('livewire.admin.plans-manager', [
            'plans' => Plan::query()->when($this->search, fn($q) => $q->where('name','like','%'.$this->search.'%')->orWhere('code','like','%'.$this->search.'%'))->orderBy('display_order')->orderBy('price')->paginate($this->perPage),
        ]);
    }
}
