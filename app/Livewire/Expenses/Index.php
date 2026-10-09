<?php

namespace App\Livewire\Expenses;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Expense;
use App\Models\Project;
use App\Services\ExpenseBudgetService;
use App\Services\ExpenseReceiptService;
use App\Services\ExpenseService;
use App\Services\ReceiptExtractionService;
use App\Support\Regional;
use Illuminate\Support\Facades\DB;
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
    use \App\Livewire\Concerns\ExportsTables;
    use WithFileUploads, WithPagination;

    /** Purchase payment methods offered on the record-expense form. */
    public const PAYMENT_METHODS = ['Cash', 'Mobile Money', 'Bank Transfer', 'Cheque', 'Card', 'Credit', 'Other'];

    public string $search = '';

    #[Url(as: 'project', except: '')]
    public string $projectFilter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $expenseId = null;

    #[Locked]
    public ?int $selectedExpenseId = null;

    public ?int $project_id = null;

    public ?int $boq_id = null;

    public string $purchase_date = '';

    public string $supplier = '';

    public string $description = '';

    public string $quantity = '1';

    public string $unit = '';

    public string $rate = '';

    public array $additionalItems = [];

    public string $currency = '';

    public string $payment_method = '';

    public bool $is_planned = true;

    public string $explanation = '';

    public $receiptFile;

    public $extractFile;

    public array $extractFiles = [];

    public array $receiptFiles = [];

    #[Locked]
    public ?int $activeReceiptIndex = null;

    #[Locked]
    public array $receiptDrafts = [];

    public ?int $boq_item_id = null;

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
            $this->selectSingleApprovedBoq();
        }
        $this->showForm = true;
    }

    public function updatedProjectId(): void
    {
        $this->boq_id = null;
        $this->boq_item_id = null;
        foreach ($this->additionalItems as &$item) {
            $item['boq_item_id'] = null;
        }
        if ($this->expenseId === null && $this->project_id !== null) {
            $project = $this->projects()->firstWhere('id', $this->project_id);
            if ($project) {
                $this->currency = $project->currency;
            }
            $this->selectSingleApprovedBoq();
        }
    }

    public function updatedBoqId(): void
    {
        $this->boq_item_id = null;
        foreach ($this->additionalItems as &$item) {
            $item['boq_item_id'] = null;
        }
    }

    /** Use the only approved BOQ automatically when a project has exactly one. */
    private function selectSingleApprovedBoq(): void
    {
        $boqs = $this->approvedBoqs();
        if ($boqs->count() === 1) {
            $this->boq_id = $boqs->first()->id;
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
        $this->boq_id = $expense->boq_id;
        $this->purchase_date = $expense->purchase_date->toDateString();
        $this->is_planned = $expense->is_planned;
        $this->boq_item_id = $expense->boq_item_id;
        $this->showForm = true;
    }

    public function save(ExpenseService $service, ExpenseReceiptService $receipts): void
    {
        if ($this->extractFiles !== []) {
            $this->validate(['extractFiles' => ['array', 'max:10'], 'extractFiles.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        }
        $rules = $this->expenseId === null ? (new StoreExpenseRequest)->rules() : (new UpdateExpenseRequest)->rules();
        if ($this->expenseId !== null) {
            unset($rules['project_id']);
        }
        // Request-only excluded identity/total fields are not form properties.
        $rules = array_intersect_key($rules, $this->all());
        $data = $this->validate($rules);
        if ($this->expenseId === null) {
            if ($this->boq_id !== null) {
                abort_unless($this->approvedBoqs()->contains('id', $this->boq_id), 422, 'Select an approved BOQ for the assigned project.');
            }
            $items = $this->draftItems();
            $available = $this->boqItems()->keyBy('id');
            foreach ($items as &$item) {
                if (! empty($item['boq_item_id'])) {
                    $boqItem = $available->get($item['boq_item_id']);
                    abort_unless($boqItem, 422, 'Select an approved BOQ item from the selected BOQ.');
                    $item['boq_id'] = $boqItem->boq_id;
                } else {
                    $item['boq_id'] = $this->boq_id;
                }
            }
            $data['items'] = $items;
            $data['boq_id'] = $this->boq_id;
            $expense = DB::transaction(function () use ($service, $receipts, $data) {
                $expense = $service->create(auth()->user(), $data);
                if ($this->extractFile) {
                    $receipts->store(auth()->user(), $expense, $this->extractFile);
                }

                return $expense;
            });
        } else {
            $expense = $service->update(auth()->user(), Expense::findOrFail($this->expenseId), $data);
        }
        if ($this->expenseId !== null && $this->extractFile) {
            $receipts->store(auth()->user(), $expense, $this->extractFile);
        }
        $this->selectedExpenseId = $expense->id;
        if ($this->activeReceiptIndex !== null) {
            unset($this->extractFiles[$this->activeReceiptIndex], $this->receiptDrafts[$this->activeReceiptIndex]);
            if ($this->extractFiles !== []) {
                $this->activeReceiptIndex = null;
                $this->reviewReceipt(array_key_first($this->extractFiles));
                session()->flash('status', __('Expense saved. Review the next receipt.'));

                return;
            }
        }
        $this->closeForm();
        session()->flash('status', __('Expense saved successfully.'));
    }

    public function addExpenseItem(): void
    {
        abort_if(count($this->additionalItems) >= 99, 422);
        $this->additionalItems[] = ['description' => '', 'quantity' => '1', 'unit' => '', 'rate' => '', 'boq_item_id' => null];
    }

    public function removeExpenseItem(int $index): void
    {
        if (isset($this->additionalItems[$index])) {
            unset($this->additionalItems[$index]);
            $this->additionalItems = array_values($this->additionalItems);
        }
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

    public function updatedExtractFiles(): void
    {
        $this->receiptDrafts = [];
        $this->activeReceiptIndex = null;
        $this->extractFile = null;
        $this->validate(['extractFiles' => ['array', 'min:1', 'max:10'], 'extractFiles.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        $this->reviewReceipt(array_key_first($this->extractFiles));
    }

    public function reviewReceipt(int $index): void
    {
        abort_unless(isset($this->extractFiles[$index]), 404);
        $fields = ['supplier', 'purchase_date', 'description', 'quantity', 'unit', 'rate', 'payment_method', 'currency', 'additionalItems', 'extractionWarnings', 'boq_item_id', 'is_planned', 'explanation'];
        if ($this->activeReceiptIndex !== null) {
            $this->receiptDrafts[$this->activeReceiptIndex] = array_intersect_key($this->all(), array_flip($fields));
        }
        $this->activeReceiptIndex = $index;
        $this->extractFile = $this->extractFiles[$index];
        $this->resetValidation();
        if (isset($this->receiptDrafts[$index])) {
            foreach ($fields as $field) {
                $this->{$field} = $this->receiptDrafts[$index][$field];
            }
        } else {
            $this->reset($fields);
            $this->currency = $this->projects()->firstWhere('id', $this->project_id)?->currency ?? Regional::currency();
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
        $this->resetValidation('extractFile');
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
        $this->payment_method = $this->normalisePaymentMethod((string) ($fields['payment_method'] ?? ''));
        $this->currency = (string) ($fields['currency'] ?? $this->currency);
        $this->extractionWarnings = $fields['warnings'] ?? [];
        $items = $fields['items'] ?? [];
        if ($items !== []) {
            $first = array_shift($items);
            foreach (['description', 'quantity', 'unit', 'rate'] as $field) {
                $this->{$field} = (string) ($first[$field] ?? '');
            }
        }
        $this->boq_item_id = null;
        $this->additionalItems = array_map(fn ($item) => [
            'description' => $item['description'], 'quantity' => (string) $item['quantity'],
            'unit' => $item['unit'], 'rate' => (string) ($item['rate'] ?? ''), 'boq_item_id' => null,
        ], $items);
        $this->resetValidation(['supplier', 'purchase_date', 'description', 'quantity', 'unit', 'rate', 'currency', 'payment_method']);
    }

    public function closeForm(): void
    {
        $this->reset(['showForm', 'expenseId', 'project_id', 'boq_id', 'purchase_date', 'supplier', 'description', 'quantity', 'unit', 'rate', 'currency', 'payment_method', 'is_planned', 'explanation', 'extractFile', 'extractFiles', 'activeReceiptIndex', 'receiptDrafts', 'boq_item_id', 'extractionWarnings', 'additionalItems']);
        $this->resetValidation();
    }

    public function show(int $id): void
    {
        $this->authorize('view', Expense::findOrFail($id));
        $this->selectedExpenseId = $id;
        $this->reset('receiptFile', 'receiptFiles');
        $this->resetValidation();
    }

    public function closeDetails(): void
    {
        $this->reset(['selectedExpenseId', 'receiptFile', 'receiptFiles']);
        $this->resetValidation();
    }

    public function uploadReceipt(ExpenseReceiptService $service): void
    {
        $expense = Expense::findOrFail($this->selectedExpenseId);
        $this->authorize('update', $expense);
        if ($this->receiptFile && $this->receiptFiles === []) {
            $this->validate(['receiptFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
            $this->receiptFiles = [$this->receiptFile];
        }
        $this->validate(['receiptFiles' => ['required', 'array', 'min:1', 'max:10'], 'receiptFiles.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        foreach ($this->receiptFiles as $index => $file) {
            $service->store(auth()->user(), $expense, $file);
            unset($this->receiptFiles[$index]);
        }
        $this->reset('receiptFile', 'receiptFiles');
        session()->flash('status', __('Receipt uploaded successfully.'));
    }

    public function render()
    {
        $query = Expense::visibleTo(auth()->user())
            ->when($this->projectFilter !== '', fn ($query) => $query->where('project_id', $this->projectFilter))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('description', 'like', '%'.trim($this->search).'%')
                ->orWhere('supplier', 'like', '%'.trim($this->search).'%')));
        $selected = $this->selectedExpenseId ? Expense::with(['project', 'receipts', 'items'])->findOrFail($this->selectedExpenseId) : null;
        if ($selected) {
            $this->authorize('view', $selected);
        }

        return view('livewire.expenses.index', [
            'expenses' => (clone $query)->with('project')->latest('purchase_date')->latest('id')->paginate($this->exportPageSize(15)),
            'totals' => (clone $query)->selectRaw('currency, SUM(total) AS amount')->groupBy('currency')->get(),
            'projects' => $this->projects(), 'selectedExpense' => $selected,
            'boqs' => $this->approvedBoqs(),
            'boqItems' => $this->boqItems(),
            'paymentMethods' => self::PAYMENT_METHODS,
            'budgetComparisons' => auth()->user()->hasPermission('boq.view') && ($project = $this->projects()->firstWhere('id', $this->project_id)) && $this->showForm && $this->expenseId === null
                ? app(ExpenseBudgetService::class)->compare($project, $this->draftItems(), $this->currency, null, $this->boq_id) : [],
            'savedBudgetComparisons' => $selected && auth()->user()->hasPermission('boq.view') ? app(ExpenseBudgetService::class)->compare($selected->project,
                $selected->items->isNotEmpty() ? $selected->items->toArray() : [$selected->toArray()], $selected->currency, $selected->id, $selected->boq_id) : [],
        ]);
    }

    private function draftItems(): array
    {
        return [['description' => $this->description, 'quantity' => $this->quantity, 'unit' => $this->unit,
            'rate' => $this->rate, 'boq_item_id' => $this->boq_item_id], ...$this->additionalItems];
    }

    /** Approved BOQs of the selected project that an expense may be linked to. */
    private function approvedBoqs()
    {
        if (! auth()->user()->hasPermission('boq.view') || ! $this->project_id || ! $this->projects()->contains('id', $this->project_id)) {
            return collect();
        }

        return Boq::where('project_id', $this->project_id)
            ->where('organisation_id', auth()->user()->organisation_id)
            ->where('status', 'approved')
            ->orderBy('name')->get();
    }

    /** Approved items that belong to the selected approved BOQ. */
    private function boqItems()
    {
        if (! auth()->user()->hasPermission('boq.view') || ! $this->boq_id || ! $this->approvedBoqs()->contains('id', $this->boq_id)) {
            return collect();
        }

        return BoqItem::with('boq')->where('boq_id', $this->boq_id)
            ->where('status', 'approved')
            ->orderBy('description')->get();
    }

    /** Map a receipt's free-text payment method onto a form option where possible. */
    private function normalisePaymentMethod(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        foreach (self::PAYMENT_METHODS as $method) {
            if (strcasecmp($method, $value) === 0) {
                return $method;
            }
        }

        return match (strtolower(str_replace(['-', '_'], ' ', $value))) {
            'momo', 'mobile money', 'mobilemoney', 'airtel money', 'mtn momo' => 'Mobile Money',
            'bank', 'bank transfer', 'banktransfer', 'wire', 'eft' => 'Bank Transfer',
            'check', 'cheque' => 'Cheque',
            'cash' => 'Cash',
            'card', 'credit card', 'debit card' => 'Card',
            'credit', 'credit account', 'on credit' => 'Credit',
            default => $value,
        };
    }
}
