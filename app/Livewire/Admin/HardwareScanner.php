<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Services\HardwarePriceFetchingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

#[Layout('layouts.app')]
class HardwareScanner extends Component
{
    use WithFileUploads;

    public array $scanForm = [
        'category' => '',
        'location' => 'Kampala',
        'limit' => 5,
    ];

    public $csvFile = null;

    public bool $isScanning = false;
    public bool $isRunningDailyFetch = false;

    public array $scanResults = [];
    public array $importResults = [];
    public array $dailyFetchResults = [];

    public bool $showCategoryModal = false;
    public bool $showDeleteCategoryModal = false;

    public ?int $editingCategoryId = null;
    public ?int $deleteCategoryId = null;

    public array $categoryForm = [
        'name' => '',
        'description' => '',
        'default_items' => '',
        'sort_order' => 100,
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->ensureSelectedCategory();
    }

    public function scanPrices(HardwarePriceFetchingService $service): void
    {
        $this->validate([
            'scanForm.category' => ['required', 'string', 'max:100'],
            'scanForm.location' => ['required', 'string', 'max:150'],
            'scanForm.limit' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $organisationId = auth()->user()?->organisation_id;

        if (! $organisationId) {
            session()->flash(
                'modal_error',
                'Your user account is not assigned to an organisation. Please contact an administrator to assign one, or upload via CSV import instead.'
            );

            return;
        }

        $this->isScanning = true;
        $this->scanResults = [];

        try {
            $items = $service->fetchPricesForCategory(
                category: $this->scanForm['category'],
                location: $this->scanForm['location'],
                limit: (int) $this->scanForm['limit'],
                organisationId: (int) $organisationId
            );

            foreach ($items as $item) {
                $existing = HardwarePrice::query()
                    ->where('organisation_id', $organisationId)
                    ->where('item_name', $item['item_name'])
                    ->where('category', $item['category'])
                    ->where('supplier', $item['supplier'])
                    ->where('location', $item['location'])
                    ->first();

                if ($existing) {
                    $existing->update(array_merge($item, [
                        'fetched_at' => now(),
                        'is_active' => true,
                    ]));

                    $status = 'updated';
                } else {
                    HardwarePrice::create(array_merge($item, [
                        'organisation_id' => $organisationId,
                        'fetched_at' => now(),
                        'is_active' => true,
                    ]));

                    $status = 'created';
                }

                $this->scanResults[] = [
                    'status' => $status,
                    'item' => $item['item_name'],
                    'price' => (float) $item['price'],
                    'currency' => $item['currency'] ?? 'UGX',
                    'supplier' => $item['supplier'] ?? null,
                ];
            }

            session()->flash(
                'modal_success',
                'Price scan completed. '.count($this->scanResults).' items processed.'
            );
        } catch (Throwable $exception) {
            report($exception);

            session()->flash(
                'modal_error',
                'Price scan failed. '.$this->safeError($exception)
            );
        } finally {
            $this->isScanning = false;
        }
    }

    public function importCsv(): void
    {
        $this->validate([
            'csvFile' => [
                'required',
                'file',
                'max:5120',
                'mimes:csv,txt',
            ],
        ]);

        $organisationId = auth()->user()?->organisation_id;

        if (! $organisationId) {
            session()->flash(
                'modal_error',
                'Your user account is not assigned to an organisation.'
            );

            return;
        }

        $this->importResults = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        $handle = null;

        try {
            $filePath = $this->csvFile->getRealPath();
            $handle = fopen($filePath, 'rb');

            if ($handle === false) {
                throw new \RuntimeException('The uploaded CSV file could not be opened.');
            }

            $header = fgetcsv($handle);

            if (! is_array($header) || $header === []) {
                throw new \RuntimeException('The CSV file has no header row.');
            }

            $header = array_map(function ($value) {
                $value = trim((string) $value);

                // Remove UTF-8 BOM from the first column.
                return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
            }, $header);

            $required = [
                'item_name',
                'category',
                'unit',
                'price',
                'currency',
                'supplier',
            ];

            foreach ($required as $column) {
                if (! in_array($column, $header, true)) {
                    throw new \RuntimeException(
                        "Missing required column: {$column}"
                    );
                }
            }

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                if (count($row) !== count($header)) {
                    $this->importResults['errors']++;
                    continue;
                }

                $data = array_combine($header, $row);

                if (! is_array($data)) {
                    $this->importResults['errors']++;
                    continue;
                }

                try {
                    $categoryName = trim((string) $data['category']);

                    $category = HardwareCategory::query()
                        ->firstOrCreate(
                            ['name' => $categoryName],
                            [
                                'is_active' => true,
                                'sort_order' => 100,
                                'created_by' => auth()->id(),
                                'updated_by' => auth()->id(),
                            ]
                        );

                    $price = filter_var(
                        str_replace(',', '', (string) $data['price']),
                        FILTER_VALIDATE_FLOAT
                    );

                    if ($price === false || $price < 0) {
                        throw new \RuntimeException('Invalid price.');
                    }

                    $itemName = trim((string) $data['item_name']);
                    $unit = trim((string) $data['unit']);
                    $supplier = trim((string) $data['supplier']);
                    $location = $this->nullable($data['location'] ?? null);

                    if ($itemName === '' || $unit === '' || $supplier === '') {
                        throw new \RuntimeException('Required values are blank.');
                    }

                    $existing = HardwarePrice::query()
                        ->where('organisation_id', $organisationId)
                        ->where('item_name', $itemName)
                        ->where('category', $categoryName)
                        ->where('unit', $unit)
                        ->where('supplier', $supplier)
                        ->where(function ($query) use ($location) {
                            if ($location === null) {
                                $query->whereNull('location')
                                    ->orWhere('location', '');
                            } else {
                                $query->where('location', $location);
                            }
                        })
                        ->first();

                    $payload = [
                        'organisation_id' => $organisationId,
                        'hardware_category_id' => $category->id,
                        'item_name' => $itemName,
                        'brand' => $this->nullable($data['brand'] ?? null),
                        'category' => $categoryName,
                        'specification' => $this->nullable($data['specification'] ?? null),
                        'unit' => $unit,
                        'price' => $price,
                        'currency' => strtoupper(trim((string) $data['currency'])),
                        'supplier' => $supplier,
                        'location' => $location,
                        'source_url' => $this->nullable($data['source_url'] ?? null),
                        'source_reference' => $this->nullable($data['source_reference'] ?? null),
                        'fetched_at' => now(),
                        'is_active' => true,
                    ];

                    if ($existing) {
                        $existing->update($payload);
                        $this->importResults['updated']++;
                    } else {
                        HardwarePrice::create($payload);
                        $this->importResults['created']++;
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $this->importResults['errors']++;
                }
            }

            session()->flash(
                'modal_success',
                sprintf(
                    'Import completed: %d created, %d updated, %d errors.',
                    $this->importResults['created'],
                    $this->importResults['updated'],
                    $this->importResults['errors']
                )
            );
        } catch (Throwable $exception) {
            report($exception);

            session()->flash(
                'modal_error',
                'Import failed. '.$this->safeError($exception)
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            $this->reset('csvFile');
        }
    }

    public function downloadCsvTemplate(): StreamedResponse
    {
        $fileName = 'hardware-price-import-template.csv';

        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'wb');

            fputcsv($stream, [
                'item_name',
                'brand',
                'category',
                'specification',
                'unit',
                'price',
                'currency',
                'supplier',
                'location',
                'source_url',
                'source_reference',
            ]);

            fputcsv($stream, [
                'Portland Cement',
                'Hima',
                'Cement',
                '42.5N 50kg',
                'bag',
                '38000',
                'UGX',
                'Sample Hardware Ltd',
                'Kampala',
                '',
                'Supplier price list',
            ]);

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function runFetchCommand(): void
    {
        $organisationId = auth()->user()?->organisation_id;

        if (! $organisationId) {
            session()->flash(
                'modal_error',
                'Your user account is not assigned to an organisation.'
            );

            return;
        }

        $this->isRunningDailyFetch = true;
        $this->dailyFetchResults = [];

        try {
            $exitCode = Artisan::call('hardware:fetch-daily', [
                '--organisation' => (string) $organisationId,
                '--location' => $this->scanForm['location'] ?: 'Kampala',
                '--limit' => 3,
            ]);

            $output = trim(Artisan::output());

            $this->dailyFetchResults = [
                'exit_code' => $exitCode,
                'output' => $output,
            ];

            if ($exitCode === 0) {
                session()->flash(
                    'modal_success',
                    'Daily hardware price fetch completed successfully.'
                );
            } else {
                session()->flash(
                    'modal_error',
                    'Daily fetch completed with errors. See the command output below.'
                );
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->dailyFetchResults = [
                'exit_code' => 1,
                'output' => $this->safeError($exception),
            ];

            session()->flash(
                'modal_error',
                'Daily fetch failed. '.$this->safeError($exception)
            );
        } finally {
            $this->isRunningDailyFetch = false;
        }
    }

    public function createCategory(): void
    {
        $this->resetCategoryForm();
        $this->showCategoryModal = true;
    }

    public function editCategory(int $id): void
    {
        $category = HardwareCategory::query()->findOrFail($id);

        $this->editingCategoryId = $category->id;

        $this->categoryForm = [
            'name' => $category->name,
            'description' => (string) ($category->description ?? ''),
            'default_items' => implode(', ', $category->default_items ?? []),
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];

        $this->showCategoryModal = true;
        $this->resetValidation();
    }

    public function saveCategory(): void
    {
        $validated = $this->validate([
            'categoryForm.name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('hardware_categories', 'name')
                    ->ignore($this->editingCategoryId),
            ],
            'categoryForm.description' => ['nullable', 'string', 'max:1000'],
            'categoryForm.default_items' => ['nullable', 'string', 'max:5000'],
            'categoryForm.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'categoryForm.is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            $category = $this->editingCategoryId
                ? HardwareCategory::query()->findOrFail($this->editingCategoryId)
                : new HardwareCategory();

            $oldName = $category->exists ? $category->name : null;

            $items = array_values(array_unique(array_filter(array_map(
                static fn ($item) => trim((string) $item),
                explode(',', (string) ($validated['categoryForm']['default_items'] ?? ''))
            ))));

            $category->fill([
                'name' => trim($validated['categoryForm']['name']),
                'description' => $this->nullable($validated['categoryForm']['description'] ?? null),
                'default_items' => $items,
                'sort_order' => (int) $validated['categoryForm']['sort_order'],
                'is_active' => (bool) $validated['categoryForm']['is_active'],
                'updated_by' => auth()->id(),
            ]);

            if (! $category->exists) {
                $category->created_by = auth()->id();
            }

            $category->save();

            if ($oldName && $oldName !== $category->name) {
                HardwarePrice::query()
                    ->where('hardware_category_id', $category->id)
                    ->update(['category' => $category->name]);
            }
        });

        session()->flash(
            'modal_success',
            $this->editingCategoryId
                ? 'Hardware category updated.'
                : 'Hardware category added.'
        );

        $this->cancelCategory();
        $this->ensureSelectedCategory();
    }

    public function toggleCategory(int $id): void
    {
        $category = HardwareCategory::query()->findOrFail($id);

        $category->update([
            'is_active' => ! $category->is_active,
            'updated_by' => auth()->id(),
        ]);

        $this->ensureSelectedCategory();

        session()->flash(
            'modal_success',
            'Hardware category status updated.'
        );
    }

    public function confirmDeleteCategory(int $id): void
    {
        $this->deleteCategoryId = $id;
        $this->showDeleteCategoryModal = true;
    }

    public function deleteCategory(): void
    {
        if (! $this->deleteCategoryId) {
            $this->showDeleteCategoryModal = false;
            return;
        }

        $category = HardwareCategory::query()
            ->withCount('hardwarePrices')
            ->findOrFail($this->deleteCategoryId);

        if ($category->hardware_prices_count > 0) {
            session()->flash(
                'modal_error',
                'This category is already used by hardware prices. Deactivate it instead of deleting it.'
            );
        } else {
            $category->delete();

            session()->flash(
                'modal_success',
                'Hardware category deleted.'
            );
        }

        $this->deleteCategoryId = null;
        $this->showDeleteCategoryModal = false;
        $this->ensureSelectedCategory();
    }

    public function cancelCategory(): void
    {
        $this->showCategoryModal = false;
        $this->editingCategoryId = null;
        $this->resetCategoryForm();
        $this->resetValidation();
    }

    private function resetCategoryForm(): void
    {
        $this->categoryForm = [
            'name' => '',
            'description' => '',
            'default_items' => '',
            'sort_order' => 100,
            'is_active' => true,
        ];
    }

    private function ensureSelectedCategory(): void
    {
        $activeCategories = HardwareCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        if (
            $this->scanForm['category'] === ''
            || ! $activeCategories->contains($this->scanForm['category'])
        ) {
            $this->scanForm['category'] = (string) ($activeCategories->first() ?? '');
        }
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function safeError(Throwable $exception): string
    {
        $message = preg_replace(
            '/(?:api[_ -]?key|token|secret|authorization)[=: ]+\S+/i',
            '$1=***',
            $exception->getMessage()
        );

        return Str::limit((string) $message, 300);
    }

    public function render()
    {
        return view('livewire.admin.hardware-scanner', [
            'categories' => HardwareCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'activeCategories' => HardwareCategory::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'stats' => [
                'categories' => HardwareCategory::query()->count(),
                'active_categories' => HardwareCategory::query()->active()->count(),
                'prices' => HardwarePrice::query()->count(),
                'active_prices' => HardwarePrice::query()->active()->count(),
            ],
        ]);
    }
}
