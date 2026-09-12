<?php

namespace App\Livewire\HardwarePrices;

use App\Http\Controllers\Api\HardwarePriceController;
use Illuminate\Http\Request;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Recommendations extends Component
{
    public ?string $category = null;

    public ?string $location = null;

    public int $limit = 10;

    public $recommendations = [];

    public function load(): void
    {
        $request = Request::create('/api/v1/hardware-prices/recommendations', 'GET', [
            'category' => $this->category,
            'location' => $this->location,
            'limit' => $this->limit,
        ]);
        $request->setUserResolver(fn () => auth()->user());

        $response = app(HardwarePriceController::class)->recommendations($request);
        $payload = json_decode($response->getContent(), true);

        $this->recommendations = $payload['data'] ?? [];
    }

    public function mount(): void
    {
        $this->load();
    }

    public function render()
    {
        return view('livewire.hardware-prices.recommendations');
    }
}