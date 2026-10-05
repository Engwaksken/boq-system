<?php

namespace App\Livewire\Expenses;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Project;
use App\Services\ExpenseReceiptService;
use App\Services\ExpenseService;
use App\Services\ReceiptExtractionService;
use App\Support\Regional;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    #[Url(as: 'project', except: '')]
    public string $projectFilter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $expenseId = null;

    #[Locked]
    public ?int $selectedExpenseId = null;

    public ?int $project_id = null;

    public string $purchase_date = '';

    public string $supplier = '';

    public string $description = '';

    public string $quantity = '1';

    public string $unit = '';

    public string $rate = '';

    public string $currency = '';

    public string $payment_method = '';

    public bool $is_planned = true;

    public string $explanation = '';

    public $receiptFile;

    public $extractFile;

    public array $extractionWarnings = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasVerifiedEmail(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProjectFilter(): void
    {
        $this->resetPage();
    }

    private function projects()
    {
        return Project::where('organisation_id', auth()->user()->organisation_id)
            ->whereHas('assignments', fn ($query) => $query->where('user_id', auth()->id())->whereNull('deleted_at'))
            ->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->closeForm();
        $this->purchase_date = now()->toDateString();
        $this->currency = Regional::currency();
        $project = $this->projects()->firstWhere('id', $this->projectFilter);
        if ($project) {
            $this->project_id = $project->id;
            $this->currency = $project->currency;
        }
        $this->showForm = true;
    }

    public function updatedProjectId(): void
    {
        if ($this->expenseId === null && $this->project_id !== null) {
            $project = $this->projects()->firstWhere('id', $this->project_id);
            if ($project) {
                $this->currency = $project->currency;
            }
        }
    }

    public function edit(int $id): void
    {
        $expense = Expense::findOrFail($id);
        $this->authorize('update', $expense);
        $this->closeForm();
        $this->expenseId = $id;
        foreach (['supplier', 'description', 'quantity', 'unit', 'rate', 'currency', 'payment_method', 'explanation'] as $field) {
            $this->{$field} = (string) $expense->{$field};
        }
        $this->project_id = $expense->project_id;
        $this->purchase_date = $expense->purchase_date->toDateString();
        $this->is_planned = $expense->is_planned;
        $this->showForm = true;
    }

    public function save(ExpenseService $service, ExpenseReceiptService $receipts): void
    {
        $rules = $this->expenseId === null ? (new StoreExpenseRequest)->rules() : (new UpdateExpenseRequest)->rules();
        if ($this->expenseId !== null) {
            unset($rules['project_id']);
        }
        // Request-only excluded identity/total fields are not form properties.
        $rules = array_intersect_key($rules, $this->all());
        $data = $this->validate($rules);
        if ($this->expenseId === null) {
            $expense = $service->create(auth()->user(), $data);
        } else {
            $expense = $service->update(auth()->user(), Expense::findOrFail($this->expenseId), $data);
        }
        if ($this->extractFile) {
            $receipts->store(auth()->user(), $expense, $this->extractFile);
        }
        $this->selectedExpenseId = $expense->id;
        $this->closeForm();
        session()->flash('status', __('Expense saved successfully.'));
    }

    /**
     * Extract runs automatically once the file upload finishes, so the async upload
     * and the "Extract" button can never race. The button simply re-runs it.
     */
    public function updatedExtractFile(): void
    {
        if ($this->extractFile) {
            $this->runExtraction();
        }
    }

    /** Read expense fields from an uploaded receipt and pre-fill the form for review. */
    public function extractFromReceipt(): void
    {
        $this->runExtraction();
    }

    private function runExtraction(): void
    {
        if (! $this->extractFile) {
            $this->addError('extractFile', __('Choose a receipt file first.'));

            return;
        }

        try {
            $fields = app(ReceiptExtractionService::class)->extract($this->extractFile, auth()->user()->organisation_id);
        } catch (ValidationException $exception) {
            $this->addError('extractFile', $exception->errors()['file'][0] ?? __('The receipt could not be read.'));

            return;
        }

        $this->supplier = (string) ($fields['supplier'] ?? '');
        $this->purchase_date = (string) ($fields['purchase_date'] ?? '');
        $this->description = (string) ($fields['description'] ?? '');
        $this->quantity = (string) ($fields['quantity'] ?? '1');
        $this->unit = (string) ($fields['unit'] ?? '');
        $this->rate = (string) ($fields['rate'] ?? '');
        $this->payment_method = (string) ($fields['payment_method'] ?? '');
        $this->currency = (string) ($fields['currency'] ?? $this->currency);
        $this->extractionWarnings = $fields['warnings'] ?? [];
        $this->resetValidation(['supplier', 'purchase_date', 'description', 'quantity', 'unit', 'rate', 'currency', 'payment_method']);
    }

    public function closeForm(): void
    {
        $this->reset(['showForm', 'expenseId', 'project_id', 'purchase_date', 'supplier', 'description', 'quantity', 'unit', 'rate', 'currency', 'payment_method', 'is_planned', 'explanation', 'extractFile', 'extractionWarnings']);
        $this->resetValidation();
    }

    public function show(int $id): void
    {
        $this->authorize('view', Expense::findOrFail($id));
        $this->selectedExpenseId = $id;
        $this->reset('receiptFile');
        $this->resetValidation();
    }

    public function closeDetails(): void
    {
        $this->reset(['selectedExpenseId', 'receiptFile']);
        $this->resetValidation();
    }

    public function uploadReceipt(ExpenseReceiptService $service): void
    {
        $expense = Expense::findOrFail($this->selectedExpenseId);
        $this->authorize('update', $expense);
        $this->validate(['receiptFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        $service->store(auth()->user(), $expense, $this->receiptFile);
        $this->reset('receiptFile');
        session()->flash('status', __('Receipt uploaded successfully.'));
    }

    public function render()
    {
        $query = Expense::visibleTo(auth()->user())
            ->when($this->projectFilter !== '', fn ($query) => $query->where('project_id', $this->projectFilter))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('description', 'like', '%'.trim($this->search).'%')
                ->orWhere('supplier', 'like', '%'.trim($this->search).'%')));
        $selected = $this->selectedExpenseId ? Expense::with(['project', 'receipts'])->findOrFail($this->selectedExpenseId) : null;
        if ($selected) {
            $this->authorize('view', $selected);
        }

        return view('livewire.expenses.index', [
            'expenses' => (clone $query)->with('project')->latest('purchase_date')->latest('id')->paginate(15),
            'totals' => (clone $query)->selectRaw('currency, SUM(total) AS amount')->groupBy('currency')->get(),
            'projects' => $this->projects(), 'selectedExpense' => $selected,
        ]);
    }
}
