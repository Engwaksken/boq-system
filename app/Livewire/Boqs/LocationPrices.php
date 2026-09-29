<?php

namespace App\Livewire\Boqs;

use App\Jobs\ProcessBoqJob;
use App\Models\Boq;
use App\Services\BoqLocationPricing;
use App\Services\BoqProcessingService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Prices of one BOQ for several locations: price it for another location,
 * compare locations side by side and use one location's prices.
 */
class LocationPrices extends Component
{
    public Boq $boq;

    /** Location keys ticked for comparison. */
    public array $compareKeys = [];

    public bool $showCompare = false;

    public string $newLocation = '';

    /** Shown inside the BOQ page, which already checks who may open it. */
    public function mount(Boq $boq): void
    {
        $this->boq = $boq;
    }

    private function editableBoq(): Boq
    {
        $user = auth()->user();
        abort_unless($user?->can('update', $this->boq) && $user->hasPermission('boq.edit'), 403);

        return $this->boq->fresh(['project']);
    }

    /** Prices every item that is not approved for another location. */
    public function priceForLocation(BoqProcessingService $processor): void
    {
        $boq = $this->editableBoq();
        $location = trim($this->validate(
            ['newLocation' => ['required', 'string', 'max:255']],
            [],
            ['newLocation' => __('location')],
        )['newLocation']);

        $ids = $boq->items()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'approved'))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            $this->addError('newLocation', __('This BOQ has no items to price.'));

            return;
        }

        $user = auth()->user();
        $batch = $processor->start($boq, $user->id, $user->organisation_id, $ids, $location);

        if (in_array($batch->status, ['queued', 'running'], true)) {
            set_time_limit(120);
            ignore_user_abort(true);
            try {
                ProcessBoqJob::dispatchSync($batch->id, ProcessBoqJob::PAGE_BUDGET_SECONDS);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $this->newLocation = '';
        session()->flash('status', __('Pricing this BOQ for :location. Both locations\' prices are kept, so you can compare them.', ['location' => $location]));
        $this->dispatch('boq-prices-updated');
    }

    public function openCompare(): void
    {
        $this->compareKeys = array_values(array_slice(array_unique(array_filter($this->compareKeys)), 0, 4));
        if (count($this->compareKeys) < 2) {
            $this->addError('compareKeys', __('Tick two to four locations to compare.'));

            return;
        }
        $this->resetErrorBag('compareKeys');
        $this->showCompare = true;
    }

    public function closeCompare(): void
    {
        $this->showCompare = false;
    }

    /** Uses one location's saved prices as the BOQ's suggested prices. */
    public function useLocation(string $key, BoqLocationPricing $pricing): void
    {
        $boq = $this->editableBoq();
        $result = $pricing->apply($boq, $key);
        $name = collect($pricing->locations($boq))->firstWhere('key', $key)['location'] ?? $key;

        $this->showCompare = false;
        session()->flash('status', __(':count item prices switched to :location.', ['count' => $result['applied'], 'location' => $name])
            .($result['missing'] ? ' '.__(':count item(s) have no price for this location yet.', ['count' => $result['missing']]) : ''));
        $this->dispatch('boq-prices-updated');
    }

    #[On('boq-prices-updated')]
    public function refreshPrices(): void
    {
        $this->boq->refresh();
    }

    public function render(BoqLocationPricing $pricing)
    {
        $locations = $pricing->locations($this->boq);

        return view('livewire.boqs.location-prices', [
            'locations' => $locations,
            'comparison' => $this->showCompare ? $pricing->compare($this->boq, $this->compareKeys) : null,
            'canEdit' => auth()->user()?->can('update', $this->boq) && auth()->user()->hasPermission('boq.edit'),
            'currency' => $this->boq->currency,
        ]);
    }
}
