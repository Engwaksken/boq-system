<?php

declare(strict_types=1);

namespace App\Livewire\HardwarePrices;

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

        $stats = [
            'total' =>
                (clone $baseQuery)
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
                    ->where(
                        'price_type',
                        HardwarePrice::TYPE_HARDWARE
                    )
                    ->count(),

            'factory' =>
                (clone $baseQuery)
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

        $this->form = [
            'item_name' => '',
            'brand' => '',
            'category' => '',
            'price_type' =>
                HardwarePrice::TYPE_HARDWARE,
            'specification' => '',
            'unit' => '',
            'price' => '',
            'currency' => 'UGX',
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