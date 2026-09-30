<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Country;
use App\Models\HardwarePrice;
use App\Models\Supplier;
use App\Services\SupplierCsvImporter;
use App\Services\SupplierScanQueue;
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

    /** Suppliers in a city, district or region. */
    #[Url(as: 'location', except: '')]
    public string $locationFilter = '';

    public int $perPage = 10;
    public bool $showForm = false;
    public ?int $editingId = null;

    public array $form = [];

    /** Scan the website for prices right after saving a supplier that has one. */
    public bool $scanAfterSave = true;

    /** How many priced items a website scan may add. */
    public int $scanLimit = 15;

    // Scan all suppliers in a location
    public bool $showLocationScan = false;
    public string $scanLocation = '';
    /** '', 'supplier' or 'factory' */
    public string $scanType = '';

    // Add many suppliers at once (table rows or a pasted list)
    public bool $showBulkAdd = false;
    /** @var list<array<string, string>> */
    public array $bulkRows = [];
    public string $bulkPaste = '';
    /** @var array<int, list<string>> row index => problems */
    public array $bulkErrors = [];
    public bool $bulkScan = true;

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
    public function updatedLocationFilter(): void { $this->resetPage(); $this->clearSelection(); }

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

    // ------------------------------------------------------------------ scans by location / selection

    public function openLocationScan(): void
    {
        $this->scanLocation = $this->locationFilter;
        $this->scanType = $this->typeFilter;
        $this->resetErrorBag('scanLocation');
        $this->showLocationScan = true;
    }

    public function closeLocationScan(): void
    {
        $this->showLocationScan = false;
    }

    /** Queue a website scan for every active supplier with a website in the location. */
    public function scanLocationSuppliers(SupplierScanQueue $scans): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $this->validate(['scanLocation' => ['nullable', 'string', 'max:255'], 'scanType' => ['nullable', Rule::in(['', 'supplier', 'factory'])]]);

        $suppliers = SupplierScanQueue::scannable($this->scanLocation, $this->scanType)->get();
        if ($suppliers->isEmpty()) {
            $this->addError('scanLocation', __('No active suppliers with a website were found in this location.'));

            return;
        }

        $queued = $scans->queue($suppliers, HardwarePrice::ownerOrganisationFor(auth()->user()), $this->scanLimit);
        $this->showLocationScan = false;
        $this->locationFilter = trim($this->scanLocation);
        $this->resetPage();

        session()->flash('message', $this->scanQueuedMessage($queued, $suppliers->count(), trim($this->scanLocation)));
    }

    /** Queue a website scan for the selected suppliers. */
    public function bulkScan(SupplierScanQueue $scans): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $suppliers = Supplier::whereKey($this->selectedIds())->get();
        $withWebsite = $suppliers->filter(fn (Supplier $s) => filled($s->website_url));

        if ($withWebsite->isEmpty()) {
            session()->flash('error', __('None of the selected suppliers has a website to scan.'));

            return;
        }

        $queued = $scans->queue($withWebsite, HardwarePrice::ownerOrganisationFor(auth()->user()), $this->scanLimit);
        $this->clearSelection();
        session()->flash('message', $this->scanQueuedMessage($queued, $withWebsite->count())
            .($suppliers->count() > $withWebsite->count() ? ' '.__(':count without a website were skipped.', ['count' => $suppliers->count() - $withWebsite->count()]) : ''));
    }

    /**
     * Polled while scans are waiting: runs a scan the queue worker has not
     * picked up, so scans finish even without a worker.
     */
    public function runQueuedScans(SupplierScanQueue $scans): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $scans->runNextStalled();
    }

    private function scanQueuedMessage(int $queued, int $total, string $location = ''): string
    {
        $message = $location !== ''
            ? trans_choice('Scanning :count supplier in :location. Prices are added to the general prices as each scan finishes.|Scanning :count suppliers in :location. Prices are added to the general prices as each scan finishes.', $queued, ['count' => $queued, 'location' => $location])
            : trans_choice('Scanning :count supplier. Prices are added to the general prices as each scan finishes.|Scanning :count suppliers. Prices are added to the general prices as each scan finishes.', $queued, ['count' => $queued]);

        return $total > $queued ? $message.' '.__(':count already being scanned.', ['count' => $total - $queued]) : $message;
    }

    // ------------------------------------------------------------------ add many

    public function openBulkAdd(): void
    {
        $this->bulkRows = [];
        $this->bulkPaste = '';
        $this->bulkErrors = [];
        for ($i = 0; $i < 5; $i++) {
            $this->addBulkRow();
        }
        $this->showBulkAdd = true;
    }

    public function closeBulkAdd(): void
    {
        $this->reset(['showBulkAdd', 'bulkRows', 'bulkPaste', 'bulkErrors']);
    }

    public function addBulkRow(): void
    {
        if (count($this->bulkRows) >= 200) {
            return;
        }

        $this->bulkRows[] = [
            'name' => '', 'type' => $this->typeFilter === Supplier::TYPE_FACTORY ? Supplier::TYPE_FACTORY : Supplier::TYPE_SUPPLIER,
            'contact_name' => '', 'phone' => '', 'email' => '', 'website_url' => '',
            'location' => $this->locationFilter, 'country' => '', 'materials' => '',
        ];
    }

    public function removeBulkRow(int $index): void
    {
        unset($this->bulkRows[$index], $this->bulkErrors[$index]);
        $this->bulkRows = array_values($this->bulkRows);
        $this->bulkErrors = [];
    }

    /** Fill the table from rows pasted from Excel/Google Sheets or a typed list. */
    public function fillFromPaste(SupplierCsvImporter $importer): void
    {
        try {
            $parsed = $importer->parseList($this->bulkPaste);
        } catch (RuntimeException $e) {
            $this->addError('bulkPaste', $e->getMessage());

            return;
        }

        if ($parsed === []) {
            $this->addError('bulkPaste', __('Paste at least one supplier, one per line.'));

            return;
        }

        $template = ['name' => '', 'type' => Supplier::TYPE_SUPPLIER, 'contact_name' => '', 'phone' => '', 'email' => '',
            'website_url' => '', 'location' => $this->locationFilter, 'country' => '', 'materials' => ''];

        // Keep rows already typed; replace the empty ones.
        $rows = array_values(array_filter($this->bulkRows, fn ($row) => trim((string) ($row['name'] ?? '')) !== ''));
        foreach ($parsed as $row) {
            $rows[] = array_merge($template, array_intersect_key($row, $template));
        }

        $this->bulkRows = array_slice($rows, 0, 200);
        $this->bulkPaste = '';
        $this->bulkErrors = [];
        $this->resetErrorBag('bulkPaste');
    }

    /** Create every valid row; rows with problems stay in the table with the reason. */
    public function saveBulk(SupplierCsvImporter $importer, SupplierScanQueue $scans): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $rows = [];
        foreach ($this->bulkRows as $index => $row) {
            if (count(array_filter($row, fn ($v, $k) => ! in_array($k, ['type', 'location'], true) && trim((string) $v) !== '', ARRAY_FILTER_USE_BOTH)) === 0) {
                continue; // untouched row
            }
            // 'line' is the row number shown to the admin ("Repeats line 2").
            $rows[] = ['line' => $index + 1] + array_map(fn ($v) => trim((string) $v), $row) + ['status' => 'active'];
        }

        if ($rows === []) {
            $this->addError('bulkRows', __('Fill in at least one supplier.'));

            return;
        }

        $preview = $importer->previewRows($rows);
        $created = $importer->importRows($preview['valid'], auth()->id());

        $this->bulkErrors = [];
        $left = [];
        foreach ([...$preview['failed'], ...$preview['duplicates']] as $row) {
            $left[$row['line']] = $row;
        }
        ksort($left);
        $keep = [];
        foreach ($left as $line => $row) {
            $keep[] = $this->bulkRows[$line - 1];
            $this->bulkErrors[count($keep) - 1] = $row['errors'] ?? [];
        }

        $message = trans_choice(':count supplier added.|:count suppliers added.', count($created), ['count' => count($created)]);
        if ($this->bulkScan && $created !== []) {
            $queued = $scans->queue($created, HardwarePrice::ownerOrganisationFor(auth()->user()), $this->scanLimit);
            if ($queued > 0) {
                $message .= ' '.trans_choice('Scanning the website of :count supplier for prices.|Scanning the websites of :count suppliers for prices.', $queued, ['count' => $queued]);
            }
        }

        $this->resetPage();

        if ($keep === []) {
            $this->closeBulkAdd();
            session()->flash('message', $message);

            return;
        }

        // Some rows need fixing: keep the dialog open with just those rows.
        $this->bulkRows = $keep;
        $this->addError('bulkRows', (count($created) ? $message.' ' : '').trans_choice(':count row needs fixing before it can be added.|:count rows need fixing before they can be added.', count($keep), ['count' => count($keep)]));
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
                ->when(trim($this->locationFilter) !== '', fn ($q) => SupplierScanQueue::inLocation($q, $this->locationFilter))
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
            'locations' => SupplierScanQueue::locations(),
            'pendingScans' => app(SupplierScanQueue::class)->pendingCount(),
            'scanCount' => $this->showLocationScan ? SupplierScanQueue::scannable($this->scanLocation, $this->scanType)->count() : 0,
        ]);
    }
}
