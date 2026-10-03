<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Plan;
use App\Models\Feature;
use App\Models\Subscription;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PlansManager extends Component
{
    use \App\Livewire\Concerns\UsesPreferredPerPage;
    use WithBulkSelection;
    use WithPagination;

    /**
     * Livewire update requests skip route middleware, so re-check on every request.
     */
    public function mount(): void
    {
        $this->form['currency'] = \App\Support\Regional::currency();
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;
    public int $perPage = 10;

    public array $form = [
        'name' => '', 'code' => '', 'description' => '', 'type' => 'monthly', 'duration_days' => 30,
        'price' => 0, 'currency' => '', 'is_active' => true, 'is_archived' => false,
        'has_trial' => false, 'trial_days' => 7, 'max_users' => 1, 'max_projects' => 5,
        'max_boqs' => 20, 'max_storage_bytes' => 104857600, 'max_ai_credits' => 100,
        'max_ocr_pages' => 50, 'max_translations' => 50, 'auto_renewal' => false,
        'grace_period_days' => 3, 'display_order' => 0,
    ];

    /** Feature ids included in the plan, edited alongside the plan form. */
    public array $featureIds = [];

    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $plan = Plan::findOrFail($id);
        $this->resetForm();
        $this->resetValidation();
        $this->editingId = $id;
        foreach (array_keys($this->form) as $key) {
            $this->form[$key] = $plan->{$key} ?? $this->form[$key];
        }
        $this->featureIds = $plan->features()->pluck('features.id')->map(fn ($id) => (int) $id)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        // Validate the generated fallback code too; validating nullable input first
        // would allow a duplicate slug to reach the database constraint.
        $this->form['code'] = trim((string) ($this->form['code'] ?? ''));
        if ($this->form['code'] === '') {
            $this->form['code'] = Str::slug((string) ($this->form['name'] ?? ''));
        }

        $validated = $this->validate([
            'form.name' => ['required','string','max:255'],
            'form.code' => ['required','string','max:100', Rule::unique('plans', 'code')->ignore($this->editingId)],
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
            'featureIds' => ['array'],
            'featureIds.*' => ['integer', Rule::exists('features', 'id')],
        ])['form'];

        $requestedFeatureIds = array_map('intval', $this->featureIds);
        $retainedInactiveIds = [];
        $existingPlan = $this->editingId ? Plan::find($this->editingId) : null;
        if ($existingPlan) {
            $retainedInactiveIds = $existingPlan->features()
                ->where('features.is_active', false)
                ->pluck('features.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        // A disabled feature may stay attached to a plan it was already on, but a
        // disabled feature that is not attached must never be added by a tampered request.
        $tamperedIds = Feature::query()
            ->whereIn('id', $requestedFeatureIds)
            ->where('is_active', false)
            ->whereNotIn('id', $retainedInactiveIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($tamperedIds->isNotEmpty()) {
            foreach ($requestedFeatureIds as $index => $id) {
                if ($tamperedIds->contains($id)) {
                    $this->addError('featureIds.'.$index, __('One or more selected features are no longer available.'));
                }
            }

            return;
        }

        $plan = Plan::updateOrCreate(['id' => $this->editingId], $validated);
        $plan->features()->sync(array_values(array_unique(array_merge($requestedFeatureIds, $retainedInactiveIds))));
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

    public function bulkDelete(): void
    {
        $this->deleteSelectedUnlessInUse(Plan::class, ['subscriptions'], 'message');
    }

    public function bulkSetActive(bool $active): void
    {
        $count = Plan::whereKey($this->selectedIds())
            ->when($active, fn ($q) => $q->where('is_archived', false))
            ->update(['is_active' => $active]);

        $this->finishBulkAction($count, $active ? 'activated' : 'deactivated');
    }

    public function bulkArchive(): void
    {
        $count = Plan::whereKey($this->selectedIds())->update(['is_archived' => true, 'is_active' => false]);

        $this->finishBulkAction($count, 'archived');
    }

    public function cancel(): void { $this->showForm = false; $this->resetForm(); $this->resetValidation(); }
    private function resetForm(): void { $this->editingId = null; $this->reset('form', 'featureIds'); $this->form['currency'] = \App\Support\Regional::currency(); $this->form['type']='monthly'; $this->form['duration_days']=30; $this->form['trial_days']=7; $this->form['is_active']=true; }
    public function updatedSearch(): void { $this->resetPage(); }

    public function render()
    {
        return view('livewire.admin.plans-manager', [
            'plans' => Plan::query()->when($this->search, fn($q) => $q->where(fn ($w) => $w->where('name','like','%'.$this->search.'%')->orWhere('code','like','%'.$this->search.'%')))->orderBy('display_order')->orderBy('price')->paginate($this->perPage),
            'features' => Feature::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'stats' => [
                'total' => Plan::count(),
                'active' => Plan::where('is_active', true)->count(),
                'trial' => Plan::where('has_trial', true)->count(),
                'subscribers' => Subscription::where('status', 'active')->count(),
            ],
        ]);
    }
}
