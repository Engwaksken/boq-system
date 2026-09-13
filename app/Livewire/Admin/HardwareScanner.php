<?php

namespace App\Livewire\Admin;

use App\Models\HardwarePrice;
use App\Services\HardwarePriceFetchingService;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class HardwareScanner extends Component
{
    use WithFileUploads;

    public array $scanForm = [
        'category' => '',
        'location' => '',
        'limit' => 10,
    ];

    public array $importForm = [];
    public $csvFile;

    public bool $isScanning = false;
    public array $scanResults = [];
    public array $importResults = [];

    public array $categories = [
        'Cement', 'Aggregates', 'Steel', 'Timber', 'Roofing', 'Plumbing', 'Electrical', 'Paint', 'Hardware', 'Tools'
    ];

    public function mount(): void
    {
        $this->categories = HardwarePrice::active()
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();
    }

    public function scanPrices(): void
    {
        $this->validate([
            'scanForm.category' => ['required', 'string', 'max:100'],
            'scanForm.location' => ['required', 'string', 'max:150'],
            'scanForm.limit' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $this->isScanning = true;
        $this->scanResults = [];

        try {
            $service = app(HardwarePriceFetchingService::class);
            $items = $service->fetchPricesForCategory(
                $this->scanForm['category'],
                $this->scanForm['location'],
                $this->scanForm['limit']
            );

            foreach ($items as $item) {
                $existing = HardwarePrice::where('organisation_id', auth()->user()->organisation_id)
                    ->where('item_name', $item['item_name'])
                    ->where('category', $item['category'])
                    ->where('location', $item['location'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'price' => $item['price'],
                        'currency' => $item['currency'],
                        'supplier' => $item['supplier'],
                        'fetched_at' => now(),
                        'is_active' => true,
                    ]);
                    $this->scanResults[] = ['status' => 'updated', 'item' => $item['item_name'], 'price' => $item['price']];
                } else {
                    HardwarePrice::create([
                        'organisation_id' => auth()->user()->organisation_id,
                        ...$item,
                        'fetched_at' => now(),
                        'is_active' => true,
                    ]);
                    $this->scanResults[] = ['status' => 'created', 'item' => $item['item_name'], 'price' => $item['price']];
                }
            }

            session()->flash('modal_success', 'Price scan completed. ' . count($this->scanResults) . ' items processed.');
        } catch (\Throwable $e) {
            session()->flash('modal_error', 'Scan failed: ' . $e->getMessage());
        } finally {
            $this->isScanning = false;
        }
    }

    public function importCsv(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'max:5120', 'extensions:csv,txt'],
        ]);

        $this->importResults = [];

        try {
            $file = $this->csvFile->getRealPath();
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);

            $required = ['item_name', 'category', 'unit', 'price', 'currency', 'supplier'];
            foreach ($required as $col) {
                if (!in_array($col, $header)) {
                    throw new \RuntimeException("Missing required column: $col");
                }
            }

            $created = 0;
            $updated = 0;
            $errors = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);

                try {
                    $existing = HardwarePrice::where('organisation_id', auth()->user()->organisation_id)
                        ->where('item_name', $data['item_name'])
                        ->where('category', $data['category'])
                        ->where('unit', $data['unit'])
                        ->where('supplier', $data['supplier'])
                        ->where('location', $data['location'] ?? '')
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'price' => $data['price'],
                            'currency' => $data['currency'],
                            'supplier' => $data['supplier'],
                            'fetched_at' => now(),
                            'is_active' => true,
                        ]);
                        $updated++;
                    } else {
                        HardwarePrice::create([
                            'organisation_id' => auth()->user()->organisation_id,
                            'item_name' => $data['item_name'],
                            'brand' => $data['brand'] ?? null,
                            'category' => $data['category'],
                            'specification' => $data['specification'] ?? null,
                            'unit' => $data['unit'],
                            'price' => $data['price'],
                            'currency' => $data['currency'],
                            'supplier' => $data['supplier'],
                            'location' => $data['location'] ?? null,
                            'source_url' => $data['source_url'] ?? null,
                            'source_reference' => $data['source_reference'] ?? null,
                            'fetched_at' => now(),
                            'is_active' => true,
                        ]);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                }
            }

            fclose($handle);

            $this->importResults = compact('created', 'updated', 'errors');
            session()->flash('modal_success', "Import completed: $created created, $updated updated, $errors errors.");
        } catch (\Throwable $e) {
            session()->flash('modal_error', 'Import failed: ' . $e->getMessage());
        }

        $this->csvFile = null;
    }

    public function runFetchCommand(): void
    {
        Artisan::call('hardware:fetch-daily');
        session()->flash('modal_success', 'Daily hardware price fetch command executed.');
    }

    public function render()
    {
        return view('livewire.admin.hardware-scanner');
    }
}