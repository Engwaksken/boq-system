<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Country;
use App\Models\HardwarePrice;
use App\Models\Supplier;
use App\Services\SupplierCsvImporter;
use App\Support\Regional;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class SuppliersManager extends Component
{
    use \App\Livewire\Concerns\UsesPreferredPerPage;
    use WithBulkSelection;
    use WithFileUploads;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '', 'supplier' or 'factory' */
    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    /** '', 'active' or 'inactive' */
    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    public int $perPage = 10;
    public bool $showForm = false;
    public ?int $editingId = null;

    public array $form = [];

    /** Scan the website for prices right after saving a supplier that has one. */
    public bool $scanAfterSave = true;

    /** How many priced items a website scan may add. */
    public int $scanLimit = 15;

    // CSV import
    public bool $showImport = false;
    public $importFile = null;
    /** @var array{valid: list<array>, duplicates: list<array>, failed: list<array>}|null */
    public ?array $importPreview = null;
    public ?string $importError = null;

    /**
     * Livewire update requests skip route middleware, so re-check on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    /** Clickable statistics cards set the list filters. */
    public function filterBy(string $type, string $status = ''): void
    {
        $this->typeFilter = in_array($type, ['', 'supplier', 'factory'], true) ? $type : '';
        $this->statusFilter = in_array($status, ['', 'active', 'inactive'], true) ? $status : '';
        $this->resetPage();
    }

    public function create(string $type = Supplier::TYPE_SUPPLIER): void
    {
        $this->resetForm();
        $this->form['type'] = $type === Supplier::TYPE_FACTORY ? Supplier::TYPE_FACTORY : Supplier::TYPE_SUPPLIER;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $key) {
            $this->form[$key] = $supplier->{$key} ?? $this->form[$key];
        }
        $this->form['materials_text'] = is_array($supplier->materials) ? implode(', ', $supplier->materials) : '';
        $this->form['rating'] = (string) (float) ($supplier->rating ?? 0);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->form['website_url'] = $this->normaliseUrl((string) ($this->form['website_url'] ?? ''));

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.type' => ['required', Rule::in([Supplier::TYPE_SUPPLIER, Supplier::TYPE_FACTORY])],
            'form.contact_name' => ['nullable', 'string', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.website_url' => ['nullable', 'url', 'max:500'],
            'form.location' => ['nullable', 'string', 'max:255'],
            'form.address' => ['nullable', 'string', 'max:500'],
            'form.region' => ['nullable', 'string', 'max:100'],
            'form.country' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'iso2')],
            'form.currency' => ['required', 'string', 'size:3'],
            'form.materials_text' => ['nullable', 'string', 'max:2000'],
            'form.notes' => ['nullable', 'string'],
            'form.preferred_language' => ['required', 'string', 'max:5'],
            'form.rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
        ])['form'];

        $payload = collect($validated)
            ->except('materials_text')
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all();
        $payload['materials'] = array_values(array_filter(array_map('trim', explode(',', (string) ($validated['materials_text'] ?? '')))));
        $payload['rating'] = $validated['rating'] ?: 0;

        if ($this->editingId) {
            $supplier = Supplier::findOrFail($this->editingId);
            $supplier->update($payload);
        } else {
            // The code is required when the row is inserted.
            $supplier = new Supplier($payload);
            $supplier->forceFill([
                'code' => ($payload['type'] === Supplier::TYPE_FACTORY ? 'FAC-' : 'SUP-').Str::upper(Str::random(6)),
                'created_by' => auth()->id(),
            ])->save();
        }

        $message = $this->editingId ? __('Supplier updated.') : __('Supplier created.');
        $scan = $this->scanAfterSave && filled($supplier->website_url);
        $this->cancel();

        if ($scan) {
            $this->scanPrices($supplier->id, $message);

            return;
        }

        session()->flash('message', $message);
    }

    /**
     * Reads the supplier's prices from its website with the AI provider and adds
     * them to the general prices, so they are used for BOQ pricing too.
     */
    public function scanPrices(int $id, ?string $prefix = null): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $supplier = Supplier::findOrFail($id);
        $prefix = $prefix ? $prefix.' ' : '';

        try {
            set_time_limit(180);
            $result = app(\App\Services\HardwarePriceFetchingService::class)->scanSupplier(
                $supplier,
                HardwarePrice::ownerOrganisationFor(auth()->user()),
                max(1, min(40, $this->scanLimit)),
            );
        } catch (\App\Exceptions\AiCreditExhaustedException) {
            session()->flash('error', $prefix.__('Price scan stopped: the AI providers have no tokens or credit left. Top up under AI API Settings.'));

            return;
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('error', $prefix.__('The prices of :name could not be scanned: :reason', [
                'name' => $supplier->name,
                'reason' => $exception instanceof RuntimeException ? $exception->getMessage() : __('the AI provider did not answer. Check AI API Settings and try again.'),
            ]));

            return;
        }

        session()->flash('message', $prefix.($result['found'] === 0
            ? __('No priced items were found on the website of :name.', ['name' => $supplier->name])
            : __('Scanned :name: :created new and :updated updated prices added to the general prices.', [
                'name' => $supplier->name,
                'created' => $result['created'],
                'updated' => $result['updated'],
            ])));
    }

    public function bulkDelete(): void
    {
        $this->deleteSelectedUnlessInUse(Supplier::class, ['quotations', 'rates'], 'message');
    }

    public function bulkSetActive(bool $active): void
    {
        $count = Supplier::whereKey($this->selectedIds())->update(['is_active' => $active]);

        $this->finishBulkAction($count, $active ? 'activated' : 'deactivated');
    }

    public function toggleActive(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update(['is_active' => ! $supplier->is_active]);
    }

    public function deactivate(int $id): void
    {
        Supplier::findOrFail($id)->update(['is_active' => false]);
        session()->flash('message', __('Supplier deactivated. Historical quotations were preserved.'));
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->resetValidation();
    }

    // ------------------------------------------------------------------ CSV import

    public function openImport(): void
    {
        $this->reset(['importFile', 'importPreview', 'importError']);
        $this->resetValidation();
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->reset(['showImport', 'importFile', 'importPreview', 'importError']);
    }

    public function downloadTemplate(SupplierCsvImporter $importer): StreamedResponse
    {
        return response()->streamDownload(fn () => print($importer->template()), 'supplier-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function previewImport(SupplierCsvImporter $importer): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120']], [
            'importFile.mimes' => __('Upload the CSV template (.csv).'),
        ]);

        $this->importError = null;

        try {
            $this->importPreview = $importer->preview($this->importFile->getRealPath());
        } catch (RuntimeException $e) {
            $this->importPreview = null;
            $this->importError = $e->getMessage();
        }
    }

    public function confirmImport(SupplierCsvImporter $importer): void
    {
        abort_unless($this->importPreview !== null, 422);

        $created = $importer->import($this->importPreview['valid'], auth()->id());
        $skipped = count($this->importPreview['duplicates']) + count($this->importPreview['failed']);

        session()->flash('message', trans_choice(':count supplier imported.|:count suppliers imported.', $created, ['count' => $created])
            .($skipped ? ' '.__(':skipped row(s) were skipped; download the error report to fix them.', ['skipped' => $skipped]) : ''));

        $this->showImport = false;
        $this->importFile = null;
        $this->resetPage();
    }

    public function downloadImportErrors(SupplierCsvImporter $importer): ?StreamedResponse
    {
        if ($this->importPreview === null) {
            return null;
        }

        $rows = [...$this->importPreview['failed'], ...$this->importPreview['duplicates']];
        usort($rows, fn ($a, $b) => ($a['line'] ?? 0) <=> ($b['line'] ?? 0));

        return response()->streamDownload(fn () => print($importer->errorsCsv($rows)), 'supplier-import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ helpers

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '', 'type' => Supplier::TYPE_SUPPLIER, 'contact_name' => '', 'email' => '', 'phone' => '',
            'website_url' => '', 'location' => '', 'address' => '', 'region' => '', 'country' => '',
            'currency' => Regional::currency(), 'materials_text' => '', 'notes' => '', 'preferred_language' => 'en', 'rating' => 0,
        ];
    }

    private function normaliseUrl(string $url): string
    {
        $url = trim($url);

        return $url !== '' && ! preg_match('#^https?://#i', $url) ? 'https://'.$url : $url;
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $suppliers = Supplier::query()->selectRaw("type, is_active, count(*) as total")->groupBy('type', 'is_active')->get();
        $count = fn (string $type, ?bool $active = null) => (int) $suppliers
            ->where('type', $type)
            ->when($active !== null, fn ($rows) => $rows->where('is_active', $active))
            ->sum('total');

        // Price figures use the default currency only: averaging different currencies is meaningless.
        $currency = Regional::currency();
        $prices = HardwarePrice::query()->where('is_active', true);
        $inCurrency = (clone $prices)->where('currency', $currency);

        return [
            'suppliers' => $count(Supplier::TYPE_SUPPLIER),
            'active_suppliers' => $count(Supplier::TYPE_SUPPLIER, true),
            'factories' => $count(Supplier::TYPE_FACTORY),
            'active_factories' => $count(Supplier::TYPE_FACTORY, true),
            'hardware_items' => (clone $prices)->where('price_type', HardwarePrice::TYPE_HARDWARE)->count(),
            'factory_items' => (clone $prices)->where('price_type', HardwarePrice::TYPE_FACTORY)->count(),
            'currency' => $currency,
            'average' => (float) (clone $inCurrency)->avg('price'),
            'lowest' => (clone $inCurrency)->orderBy('price')->first(['id', 'item_name', 'price', 'currency']),
            'highest' => (clone $inCurrency)->orderByDesc('price')->first(['id', 'item_name', 'price', 'currency']),
            'updated_today' => (clone $prices)->where(fn ($q) => $q->where('fetched_at', '>=', now()->startOfDay())->orWhere('last_verified_at', '>=', now()->startOfDay()))->count(),
            'updated_week' => (clone $prices)->where(fn ($q) => $q->where('fetched_at', '>=', now()->startOfWeek())->orWhere('last_verified_at', '>=', now()->startOfWeek()))->count(),
        ];
    }

    public function render()
    {
        $search = trim($this->search);

        return view('livewire.admin.suppliers-manager', [
            'suppliers' => Supplier::query()
                ->withCount(['rates', 'hardwarePrices'])
                ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('website_url', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate($this->perPage),
            'stats' => $this->stats(),
            'countries' => Country::options(),
        ]);
    }
}
