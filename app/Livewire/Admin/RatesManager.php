<?php

namespace App\Livewire\Admin;

use App\Models\Rate;
use App\Models\Supplier;
use App\Services\RateLibraryService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class RatesManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $verification = '';
    public string $currency = '';
    public int $perPage = 10;
    public bool $showForm = false;
    public ?int $editingId = null;

    public array $form = [
        'item' => '', 'description' => '', 'category' => '', 'unit' => 'NO', 'rate' => 0,
        'currency' => 'UGX', 'region' => '', 'country' => '', 'supplier_id' => null,
        'source_type' => 'previous_boq', 'source_reference' => '',
        'effective_from' => '', 'effective_until' => '', 'verification_status' => 'pending',
    ];

    public function __construct(private readonly RateLibraryService $library)
    {
    }

    public function create(): void { $this->resetForm(); $this->showForm = true; }

    public function edit(int $id): void
    {
        $rate = Rate::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $key) {
            $this->form[$key] = $rate->{$key} ?? $this->form[$key];
        }
        $this->form['rate'] = (string) (float) $rate->rate;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.item' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.category' => ['nullable', 'string', 'max:100'],
            'form.unit' => ['required', 'string', 'max:50'],
            'form.rate' => ['required', 'numeric', 'min:0'],
            'form.currency' => ['required', 'string', 'size:3'],
            'form.region' => ['nullable', 'string', 'max:100'],
            'form.country' => ['nullable', 'string', 'size:2'],
            'form.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'form.source_type' => ['required', 'in:previous_boq,supplier_quotation,supplier_price_list,procurement,market_survey,reference_schedule,external_feed,manual'],
            'form.source_reference' => ['nullable', 'string', 'max:255'],
            'form.effective_from' => ['nullable', 'date'],
            'form.effective_until' => ['nullable', 'date'],
            'form.verification_status' => ['required', 'in:draft,pending,approved'],
        ])['form'];

        $approved = $validated['verification_status'] === 'approved';
        $previous = Rate::find($this->editingId);
        $payload = array_merge($validated, [
            'description' => $validated['description'] ?: null,
            'country' => $validated['country'] ?: null,
            'source_reference' => $validated['source_reference'] ?: null,
            'effective_from' => $validated['effective_from'] ?: now()->toDateString(),
            'effective_until' => $validated['effective_until'] ?: null,
            'verified_by' => $approved ? auth()->id() : null,
            'verified_at' => $approved ? now() : null,
            'is_active' => $approved,
        ]);

        $rate = Rate::updateOrCreate(['id' => $this->editingId], $payload);
        if (! $previous) {
            $rate->update(['code' => 'RATE-'.Str::upper(Str::slug($rate->item).'-'.Str::random(4))]);
        }
        session()->flash('message', $this->editingId ? 'Rate updated.' : 'Rate saved (pending verification).');
        $this->cancel();
    }

    public function approve(int $id): void
    {
        $this->library->approve(Rate::findOrFail($id), auth()->user());
        session()->flash('message', 'Rate approved and now effective.');
    }

    public function reject(int $id): void
    {
        $this->library->reject(Rate::findOrFail($id), auth()->user());
        session()->flash('message', 'Rate rejected.');
    }

    public function cancel(): void { $this->showForm = false; $this->resetForm(); }
    private function resetForm(): void { $this->editingId = null; $this->reset('form'); $this->form['currency']='UGX'; $this->form['unit']='NO'; $this->form['source_type']='previous_boq'; $this->form['verification_status']='pending'; }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedVerification(): void { $this->resetPage(); }
    public function updatedCurrency(): void { $this->resetPage(); }

    #[Computed]
    public function suppliers(): array
    {
        return Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->toArray();
    }

    public function render()
    {
        $query = Rate::query()->with('supplier')
            ->when($this->search, function ($q) {
                $like = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('item', 'like', $like)->orWhere('description', 'like', $like)->orWhere('code', 'like', $like));
            })
            ->when($this->verification, fn ($q) => $q->where('verification_status', $this->verification))
            ->when($this->currency, fn ($q) => $q->where('currency', $this->currency));

        return view('livewire.admin.rates-manager', [
            'rates' => $query->orderByDesc('verified_at')->orderBy('item')->paginate($this->perPage),
        ]);
    }
}