<?php

namespace App\Livewire\Admin;

use App\Models\Quotation;
use App\Services\QuotationService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class QuotationsManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public int $perPage = 10;
    public ?int $viewingId = null;

    public function __construct(private readonly QuotationService $quotations)
    {
    }

    public function preapproveLine(int $itemId): void
    {
        $quotation = Quotation::whereHas('items', fn ($q) => $q->whereKey($itemId))->first();
        if (! $quotation || $this->viewingId !== $quotation->id) {
            return;
        }
        $item = $quotation->items()->findOrFail($itemId);
        $this->quotations->setLineApproved($item, ! $item->approved);
        $this->quotations->recalculate($quotation);
    }

    public function review(int $id): void
    {
        $quotation = Quotation::findOrFail($id);
        $this->quotations->review($quotation, auth()->user());
        session()->flash('message', 'Quotation marked as reviewed.');
    }

    public function accept(int $id): void
    {
        $quotation = Quotation::findOrFail($id);
        $this->quotations->accept($quotation, auth()->user());
        session()->flash('message', 'Quotation accepted. Approved lines were promoted to the rate library.');
        $this->viewingId = null;
    }

    public function reject(int $id): void
    {
        $quotation = Quotation::findOrFail($id);
        $this->quotations->reject($quotation, auth()->user());
        session()->flash('message', 'Quotation rejected.');
        $this->viewingId = null;
    }

    public function view(int $id): void { $this->viewingId = $id; }
    public function close(): void { $this->viewingId = null; }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    #[Computed]
    public function viewing(): ?Quotation
    {
        if (! $this->viewingId) {
            return null;
        }

        return Quotation::query()->with(['supplier', 'project', 'items.matchedRate.supplier'])->find($this->viewingId);
    }

    public function render()
    {
        return view('livewire.admin.quotations-manager', [
            'quotations' => Quotation::query()->with('supplier')
                ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('quote_number', 'like', '%'.$this->search.'%')->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%'))))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->latest()
                ->paginate($this->perPage),
        ]);
    }
}