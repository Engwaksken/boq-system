<?php

declare(strict_types=1);

namespace App\Livewire\HardwarePrices;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\HardwareCategory;
use App\Models\HardwareItem;
use App\Models\HardwarePrice;
use App\Services\HardwarePriceCsvImporter;
use App\Services\HardwarePriceManager;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithBulkSelection;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $priceType = '';

    public ?string $category = null;

    public ?string $supplier = null;

    public ?string $location = null;

    public string $status = 'active';

    public ?int $editingId = null;

    public bool $showForm = false;

    public array $form = [];

    public $csvFile;

    public ?array $importSummary = null;

    public string $categoryChoice = '';

    public string $itemChoice = '';

    private const OTHER = '__other__';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPriceType(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSupplier(): void
    {
        $this->resetPage();
    }

    public function updatedLocation(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryChoice(string $value): void
    {
        $this->form['category'] = $value === self::OTHER ? '' : $value;
        $this->itemChoice = '';
        $this->form['item_name'] = '';
    }

    public function updatedItemChoice(string $value): void
    {
        $this->form['item_name'] = $value === self::OTHER ? '' : $value;
    }

    /**
     * Categories offered in the price form: managed categories plus any already in use.
     *
     * @return list<string>
     */
    private function categoryOptions(?int $organisationId): array
    {
        return HardwareCategory::active()
            ->where(fn ($q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $organisationId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->merge(
                HardwarePrice::query()
                    ->where('organisation_id', $organisationId)
                    ->distinct()
                    ->pluck('category')
            )
            ->filter()
            ->unique(fn ($name) => mb_strtolower(trim($name)))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Items offered for a category: the category's default items plus recorded items.
     *
     * @return list<string>
     */
    private function itemOptions(?int $organisationId, string $category): array
    {
        if ($category === '') {
            return [];
        }

        $defaults = HardwareItem::query()
            ->active()
            ->whereHas('category', fn ($q) => $q
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($category)])
                ->where(fn ($w) => $w->whereNull('organisation_id')->orWhere('organisation_id', $organisationId)))
            ->orderBy('sort_order')
            ->pluck('name');

        return $defaults
            ->merge(
                HardwarePrice::query()
                    ->where('organisation_id', $organisationId)
                    ->where('category', $category)
                    ->distinct()
                    ->pluck('item_name')
            )
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->unique(fn ($name) => mb_strtolower(trim($name)))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function syncChoicesFromForm(?int $organisationId): void
    {
        $category = (string) ($this->form['category'] ?? '');
        $item = (string) ($this->form['item_name'] ?? '');

        $this->categoryChoice = $category === ''
            ? ''
            : (in_array($category, $this->categoryOptions($organisationId), true) ? $category : self::OTHER);

        $this->itemChoice = $item === ''
            ? ''
            : (in_array($item, $this->itemOptions($organisationId, $category), true) ? $item : self::OTHER);
    }

    public function createPrice(): void
    {
        $this->managementUser();

        $this->resetForm();

        $this->showForm = true;
    }

    public function editPrice(
        int $id
    ): void {
        $user =
            $this->managementUser();

        $price =
            HardwarePrice::query()
                ->where(
                    'organisation_id',
                    $user->organisation_id
                )
                ->findOrFail($id);

        $this->editingId =
            $price->id;

        $this->form = [
            'item_name' =>
                $price->item_name,

            'brand' =>
                $price->brand,

            'category' =>
                $price->category,

            'price_type' =>
                $price->price_type
                ?? HardwarePrice::TYPE_HARDWARE,

            'specification' =>
                $price->specification,

            'unit' =>
                $price->unit,

            'price' =>
                $price->price,

            'currency' =>
                $price->currency,

            'supplier' =>
                $price->supplier,

            'location' =>
                $price->location,

            'source_url' =>
                $price->source_url,

            'source_reference' =>
                $price->source_reference,

            'fetched_at' =>
                $price
                    ->fetched_at
                    ?->format(
                        'Y-m-d\TH:i'
                    ),

            'is_active' =>
                $price->is_active,
        ];

        $this->syncChoicesFromForm($user->organisation_id);

        $this->resetValidation();

        $this->showForm = true;
    }

    public function save(
        HardwarePriceManager $manager
    ): void {
        $user =
            $this->managementUser();

        try {
            if ($this->editingId) {
                $price =
                    HardwarePrice::query()
                        ->where(
                            'organisation_id',
                            $user->organisation_id
                        )
                        ->findOrFail(
                            $this->editingId
                        );

                $manager->update(
                    $user->organisation_id,
                    $price,
                    $this->form
                );

                session()->flash(
                    'hardware-price-message',
                    'Price updated successfully.'
                );
            } else {
                $manager->create(
                    $user->organisation_id,
                    $this->form
                );

                session()->flash(
                    'hardware-price-message',
                    'Price created successfully.'
                );
            }
        } catch (
            ValidationException $exception
        ) {
            foreach (
                $exception->errors()
                as $field => $messages
            ) {
                $this->addError(
                    "form.{$field}",
                    $messages[0]
                );
            }

            return;
        }

        $this->cancelForm();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;

        $this->resetForm();

        $this->resetValidation();
    }

    public function bulkDelete(): void
    {
        $this->deleteSelectedUnlessInUse(HardwarePrice::class, ['boqItems'], 'hardware-price-message', fn ($query) => $query->where('organisation_id', $this->managementUser()->organisation_id));
    }

    public function bulkSetActive(
        bool $active,
        HardwarePriceManager $manager
    ): void {
        $user = $this->managementUser();

        $prices = HardwarePrice::query()
            ->where('organisation_id', $user->organisation_id)
            ->whereKey($this->selectedIds())
            ->where('is_active', ! $active)
            ->get();

        foreach ($prices as $price) {
            $active
                ? $manager->activate($user->organisation_id, $price)
                : $manager->deactivate($user->organisation_id, $price);
        }

        $this->finishBulkAction($prices->count(), $active ? 'activated' : 'deactivated', 'hardware-price-message');
    }

    public function bulkBookmark(): void
    {
        $user = auth()->user();

        $prices = HardwarePrice::query()
            ->where('organisation_id', $user->organisation_id)
            ->whereKey($this->selectedIds())
            ->get();

        foreach ($prices as $price) {
            $user->hardwareBookmarks()->firstOrCreate([
                'hardware_price_id' => $price->id,
                'location' => $price->location ?: 'Any location',
            ]);
        }

        $this->finishBulkAction($prices->count(), 'bookmarked', 'hardware-price-message');
    }

    public function toggleActive(
        int $id,
        HardwarePriceManager $manager
    ): void {
        $user =
            $this->managementUser();

        $price =
            HardwarePrice::query()
                ->where(
                    'organisation_id',
                    $user->organisation_id
                )
                ->findOrFail($id);

        $wasActive =
            (bool) $price->is_active;

        if ($wasActive) {
            $manager->deactivate(
                $user->organisation_id,
                $price
            );
        } else {
            $manager->activate(
                $user->organisation_id,
                $price
            );
        }

        session()->flash(
            'hardware-price-message',
            $wasActive
                ? 'Price deactivated.'
                : 'Price activated.'
        );
    }

    public function importCsv(
        HardwarePriceCsvImporter $importer
    ): void {
        $user =
            $this->managementUser();

        $this->validate([
            'csvFile' => [
                'required',
                'file',
                'max:5120',
                'extensions:csv,txt',
            ],
        ]);

        try {
            $this->importSummary =
                $importer->import(
                    $this
                        ->csvFile
                        ->getRealPath(),
                    $user->organisation_id
                );

            $this->csvFile = null;
        } catch (
            RuntimeException $exception
        ) {
            $this->addError(
                'csvFile',
                $exception->getMessage()
            );
        }
    }

    public function downloadCsvTemplate()
    {
        $this->managementUser();

        $header =
            'item_name,brand,category,price_type,specification,unit,price,currency,supplier,location,source_url,source_reference,fetched_at,is_active';

        return response()->streamDownload(
            fn () =>
                print $header."\n",
            'hardware-factory-prices-template.csv',
            [
                'Content-Type' =>
                    'text/csv',
            ]
        );
    }

    /**
     * Bookmark a hardware price at its own location, or remove that bookmark.
     */
    public function toggleBookmark(int $priceId): void
    {
        $user = auth()->user();

        $price = HardwarePrice::query()
            ->where('organisation_id', $user->organisation_id)
            ->findOrFail($priceId);

        $location = $price->location ?: 'Any location';

        $bookmark = $user->hardwareBookmarks()
            ->where('hardware_price_id', $price->id)
            ->where('location', $location)
            ->first();

        if ($bookmark) {
            $bookmark->delete();

            session()->flash('hardware-price-message', 'Bookmark removed.');

            return;
        }

        $user->hardwareBookmarks()->create([
            'hardware_price_id' => $price->id,
            'location' => $location,
        ]);

        session()->flash('hardware-price-message', 'Hardware price bookmarked. View it under Profile → Hardware Bookmarks.');
    }

    public function render()
    {
        $user =
            auth()
                ->user()
                ->fresh();

        $organisationId =
            $user->organisation_id;

        $canManage =
            $user->hasPermission(
                'hardware-prices.manage'
            );

        $baseQuery =
            HardwarePrice::query()
                ->where(
                    'organisation_id',
                    $organisationId
                );

        $prices =
            (clone $baseQuery)
                ->when(
                    ! $canManage
                    || $this->status === 'active',
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                )
                ->when(
                    $canManage
                    && $this->status === 'inactive',
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            false
                        )
                )
                ->when(
                    $this->priceType !== '',
                    fn ($query) =>
                        $query->where(
                            'price_type',
                            $this->priceType
                        )
                )
                ->when(
                    $this->search !== '',
                    fn ($query) =>
                        $query->search(
                            $this->search
                        )
                )
                ->when(
                    $this->category,
                    fn ($query) =>
                        $query->byCategory(
                            $this->category
                        )
                )
                ->when(
                    $this->supplier,
                    fn ($query) =>
                        $query->bySupplier(
                            $this->supplier
                        )
                )
                ->when(
                    $this->location,
                    fn ($query) =>
                        $query->byLocation(
                            $this->location
                        )
                )
                ->orderByDesc(
                    'fetched_at'
                )
                ->paginate(20);

        $categories =
            (clone $baseQuery)
                ->where(
                    'is_active',
                    true
                )
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->filter()
                ->values();

        $suppliers =
            (clone $baseQuery)
                ->where(
                    'is_active',
                    true
                )
                ->distinct()
                ->orderBy('supplier')
                ->pluck('supplier')
                ->filter()
                ->values();

        $locations =
            (clone $baseQuery)
                ->where(
                    'is_active',
                    true
                )
                ->distinct()
                ->orderBy('location')
                ->pluck('location')
                ->filter()
                ->values();

        // Hardware + factory add up to "active"; total only counts what this user can see.
        $stats = [
            'total' =>
                (clone $baseQuery)
                    ->when(! $canManage, fn ($query) => $query->where('is_active', true))
                    ->count(),

            'active' =>
                (clone $baseQuery)
                    ->where(
                        'is_active',
                        true
                    )
                    ->count(),

            'hardware' =>
                (clone $baseQuery)
                    ->where('is_active', true)
                    ->where(
                        'price_type',
                        HardwarePrice::TYPE_HARDWARE
                    )
                    ->count(),

            'factory' =>
                (clone $baseQuery)
                    ->where('is_active', true)
                    ->where(
                        'price_type',
                        HardwarePrice::TYPE_FACTORY
                    )
                    ->count(),

            'categories' =>
                $categories->count(),

            'suppliers' =>
                $suppliers->count(),
        ];

        return view(
            'livewire.hardware-prices.index',
            [
                'prices' =>
                    $prices,

                'categories' =>
                    $categories,

                'suppliers' =>
                    $suppliers,

                'locations' =>
                    $locations,

                'canManage' =>
                    $canManage,

                'stats' =>
                    $stats,

                'categoryOptions' =>
                    $this->showForm
                        ? $this->categoryOptions($organisationId)
                        : [],

                'itemOptions' =>
                    $this->showForm
                        ? $this->itemOptions($organisationId, (string) ($this->form['category'] ?? ''))
                        : [],

                'bookmarkedIds' =>
                    $user->hardwareBookmarks()
                        ->whereIn('hardware_price_id', $prices->pluck('id'))
                        ->pluck('hardware_price_id')
                        ->all(),
            ]
        );
    }

    private function managementUser()
    {
        $user =
            auth()
                ->user()
                ?->fresh();

        abort_unless(
            $user
            && $user->organisation_id
            && $user->hasPermission(
                'hardware-prices.manage'
            ),
            403
        );

        return $user;
    }

    private function resetForm(): void
    {
        $this->editingId = null;

        $this->categoryChoice = '';

        $this->itemChoice = '';

        $this->form = [
            'item_name' => '',
            'brand' => '',
            'category' => '',
            'price_type' =>
                HardwarePrice::TYPE_HARDWARE,
            'specification' => '',
            'unit' => '',
            'price' => '',
            'currency' => \App\Support\Regional::currency(),
            'supplier' => '',
            'location' => '',
            'source_url' => '',
            'source_reference' => '',
            'fetched_at' =>
                now()->format(
                    'Y-m-d\TH:i'
                ),
            'is_active' => true,
        ];
    }
}