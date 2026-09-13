<?php

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
    use WithFileUploads, WithPagination;

    public string $search = '';

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

    public function editPrice(int $id): void
    {
        $user = $this->managementUser();
        $price = HardwarePrice::query()
            ->where('organisation_id', $user->organisation_id)
            ->findOrFail($id);

        $this->editingId = $price->id;
        $this->form = [
            'item_name' => $price->item_name,
            'brand' => $price->brand,
            'category' => $price->category,
            'specification' => $price->specification,
            'unit' => $price->unit,
            'price' => $price->price,
            'currency' => $price->currency,
            'supplier' => $price->supplier,
            'location' => $price->location,
            'source_url' => $price->source_url,
            'source_reference' => $price->source_reference,
            'fetched_at' => $price->fetched_at?->format('Y-m-d\TH:i'),
            'is_active' => $price->is_active,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(HardwarePriceManager $manager): void
    {
        $user = $this->managementUser();

        try {
            if ($this->editingId) {
                $price = HardwarePrice::query()
                    ->where('organisation_id', $user->organisation_id)
                    ->findOrFail($this->editingId);
                $manager->update($user->organisation_id, $price, $this->form);
                session()->flash('hardware-price-message', 'Hardware price updated.');
            } else {
                $manager->create($user->organisation_id, $this->form);
                session()->flash('hardware-price-message', 'Hardware price created.');
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError("form.{$field}", $messages[0]);
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

    public function toggleActive(int $id, HardwarePriceManager $manager): void
    {
        $user = $this->managementUser();
        $price = HardwarePrice::query()
            ->where('organisation_id', $user->organisation_id)
            ->findOrFail($id);

        $price->is_active
            ? $manager->deactivate($user->organisation_id, $price)
            : $manager->activate($user->organisation_id, $price);

        session()->flash('hardware-price-message', $price->is_active ? 'Hardware price deactivated.' : 'Hardware price activated.');
    }

    public function importCsv(HardwarePriceCsvImporter $importer): void
    {
        $user = $this->managementUser();
        $this->validate([
            'csvFile' => ['required', 'file', 'max:5120', 'extensions:csv,txt'],
        ]);

        try {
            $this->importSummary = $importer->import($this->csvFile->getRealPath(), $user->organisation_id);
            $this->csvFile = null;
        } catch (RuntimeException $exception) {
            $this->addError('csvFile', $exception->getMessage());
        }
    }

    public function downloadCsvTemplate()
    {
        $this->managementUser();
        $header = 'item_name,brand,category,specification,unit,price,currency,supplier,location,source_url,source_reference,fetched_at,is_active';

        return response()->streamDownload(fn () => print $header."\n", 'hardware-prices-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        $user = auth()->user()->fresh();
        $organisationId = $user->organisation_id;
        $canManage = $user->hasPermission('hardware-prices.manage');

        $prices = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->when(! $canManage || $this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($canManage && $this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->search !== '', fn ($q) => $q->search($this->search))
            ->when($this->category, fn ($q) => $q->byCategory($this->category))
            ->when($this->supplier, fn ($q) => $q->bySupplier($this->supplier))
            ->when($this->location, fn ($q) => $q->byLocation($this->location))
            ->orderBy('fetched_at', 'desc')
            ->paginate(20);

        $categories = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        $suppliers = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('supplier')
            ->filter()
            ->values();

        $locations = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('location')
            ->filter()
            ->values();

        return view('livewire.hardware-prices.index', [
            'prices' => $prices,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'locations' => $locations,
            'canManage' => $canManage,
        ]);
    }

    private function managementUser()
    {
        $user = auth()->user()?->fresh();
        abort_unless($user && $user->organisation_id && $user->hasPermission('hardware-prices.manage'), 403);

        return $user;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'item_name' => '',
            'brand' => '',
            'category' => '',
            'specification' => '',
            'unit' => '',
            'price' => '',
            'currency' => 'UGX',
            'supplier' => '',
            'location' => '',
            'source_url' => '',
            'source_reference' => '',
            'fetched_at' => now()->format('Y-m-d\TH:i'),
            'is_active' => true,
        ];
    }
}
