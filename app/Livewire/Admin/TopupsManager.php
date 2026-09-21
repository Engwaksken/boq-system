<?php

namespace App\Livewire\Admin;

use App\Models\Topup;
use App\Models\TopupPurchase;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TopupsManager extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;
    public int $perPage = 10;

    public array $form = [
        'name' => '', 'code' => '', 'description' => '', 'type' => 'bundle', 'price' => 0,
        'currency' => 'UGX', 'duration_days' => null, 'is_permanent' => false,
        'release_version' => '', 'included_features' => '', 'usage_credits' => '',
        'applicable_plans' => '', 'purchase_limit' => null, 'requires_confirmation' => true,
        'is_active' => true, 'is_archived' => false, 'display_order' => 0,
    ];

    public function create(): void { $this->resetForm(); $this->showForm = true; }

    public function edit(int $id): void
    {
        $topup = Topup::findOrFail($id);
        $this->editingId = $id;
        $this->form['name'] = $topup->name;
        $this->form['code'] = $topup->code;
        $this->form['description'] = $topup->description;
        $this->form['type'] = $topup->type;
        $this->form['price'] = $topup->price;
        $this->form['currency'] = $topup->currency;
        $this->form['duration_days'] = $topup->duration_days;
        $this->form['is_permanent'] = $topup->is_permanent;
        $this->form['release_version'] = $topup->release_version;
        $this->form['included_features'] = implode(',', $topup->included_features ?? []);
        $this->form['usage_credits'] = json_encode($topup->usage_credits ?? [], JSON_UNESCAPED_SLASHES);
        $this->form['applicable_plans'] = implode(',', $topup->applicable_plans ?? []);
        $this->form['purchase_limit'] = $topup->purchase_limit;
        $this->form['requires_confirmation'] = $topup->requires_confirmation;
        $this->form['is_active'] = $topup->is_active;
        $this->form['is_archived'] = $topup->is_archived;
        $this->form['display_order'] = $topup->display_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['nullable', 'string', 'max:100', 'unique:topups,code,'.($this->editingId ?? 'NULL')],
            'form.description' => ['nullable', 'string'],
            'form.type' => ['required', 'in:feature_unlock,version_update,bundle,ai_credit_topup,ocr_credit_topup,translation_credit_topup,storage_topup,user_seat_topup,project_limit_topup,boq_limit_topup,report_export_topup'],
            'form.price' => ['required', 'numeric', 'min:0'],
            'form.currency' => ['required', 'string', 'size:3'],
            'form.duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'form.is_permanent' => ['boolean'],
            'form.release_version' => ['nullable', 'string', 'max:50'],
            'form.included_features' => ['nullable', 'string'],
            'form.usage_credits' => ['nullable', 'string'],
            'form.applicable_plans' => ['nullable', 'string'],
            'form.purchase_limit' => ['nullable', 'integer', 'min:1'],
            'form.requires_confirmation' => ['boolean'],
            'form.is_active' => ['boolean'],
            'form.is_archived' => ['boolean'],
            'form.display_order' => ['integer', 'min:0'],
        ])['form'];

        $data = $this->toAttributes($validated);
        $data['code'] = $data['code'] ?: Str::slug($data['name']).'-'.Str::lower(Str::random(4));

        Topup::updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('message', $this->editingId ? 'Top-up updated successfully.' : 'Top-up created successfully.');
        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        $topup = Topup::findOrFail($id);
        $topup->update(['is_active' => ! $topup->is_active]);
    }

    public function archive(int $id): void
    {
        $topup = Topup::findOrFail($id);
        $topup->update(['is_archived' => true, 'is_active' => false]);
        session()->flash('message', 'Top-up archived. Existing purchases remain active.');
    }

    public function cancel(): void { $this->showForm = false; $this->resetForm(); }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->reset('form');
        $this->form['currency'] = 'UGX';
        $this->form['type'] = 'bundle';
        $this->form['is_permanent'] = false;
        $this->form['requires_confirmation'] = true;
        $this->form['is_active'] = true;
        $this->form['display_order'] = 0;
    }

    public function updatedSearch(): void { $this->resetPage(); }

    private function toAttributes(array $form): array
    {
        return [
            'name' => $form['name'],
            'code' => $form['code'],
            'description' => $form['description'],
            'type' => $form['type'],
            'price' => $form['price'],
            'currency' => $form['currency'],
            'duration_days' => $form['duration_days'],
            'is_permanent' => $form['is_permanent'],
            'release_version' => $form['release_version'] ?: null,
            'included_features' => $this->splitList($form['included_features']),
            'usage_credits' => $this->decodeJson($form['usage_credits']),
            'applicable_plans' => $this->splitList($form['applicable_plans']),
            'purchase_limit' => $form['purchase_limit'],
            'requires_confirmation' => $form['requires_confirmation'],
            'is_active' => $form['is_active'],
            'is_archived' => $form['is_archived'],
            'display_order' => $form['display_order'],
        ];
    }

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

    private function decodeJson(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function render()
    {
        return view('livewire.admin.topups-manager', [
            'topups' => Topup::query()
                ->when($this->search, function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%');
                })
                ->orderBy('display_order')
                ->orderBy('name')
                ->paginate($this->perPage),
            'purchaseCounts' => TopupPurchase::query()
                ->selectRaw('topup_id, count(*) as total')
                ->groupBy('topup_id')
                ->pluck('total', 'topup_id'),
        ]);
    }
}