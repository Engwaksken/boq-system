<?php

namespace App\Livewire\HardwarePrices;

use App\Services\HardwarePriceChanges;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Recent price changes across every item the user can see (general market
 * prices and their organisation's own), newest or biggest first. Each item
 * links to its page for the full month-by-month history.
 */
#[Layout('layouts.app')]
class Changes extends Component
{
    use \App\Livewire\Concerns\ExportsTables;
    use \App\Livewire\Concerns\UsesPreferredPerPage;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** Days to look back, or "all". */
    #[Url(except: '30')]
    public string $period = '30';

    /** '' = both, 'up' = increases, 'down' = decreases. */
    #[Url(except: '')]
    public string $direction = '';

    #[Url(except: '')]
    public string $category = '';

    /** '' = all, 'general' = shared market prices, 'mine' = my organisation's. */
    #[Url(except: '')]
    public string $source = '';

    /** recent = newest change first, up = biggest rise first, down = biggest fall first. */
    #[Url(except: 'recent')]
    public string $sort = 'recent';

    public int $perPage = 20;

    public function updated(string $property): void
    {
        // Ignore tampered values instead of building a broken query.
        $valid = HardwarePriceChanges::filters($this->filterValues());
        foreach (['period', 'direction', 'source', 'sort'] as $key) {
            $this->{$key} = $valid[$key];
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'period', 'direction', 'category', 'source', 'sort']);
        $this->resetPage();
    }

    private function filterValues(): array
    {
        return [
            'search' => $this->search,
            'period' => $this->period,
            'direction' => $this->direction,
            'category' => $this->category,
            'source' => $this->source,
            'sort' => $this->sort,
        ];
    }

    public function render(HardwarePriceChanges $changes)
    {
        $organisationId = auth()->user()->organisation_id;
        $filters = HardwarePriceChanges::filters($this->filterValues());
        $base = $changes->base($organisationId, $filters);

        return view('livewire.hardware-prices.changes', [
            'changes' => $changes->list($base, $filters)->paginate($this->exportPageSize($this->perPage)),
            // Summary for the chosen period, source, category and search (both directions).
            'stats' => $changes->stats($base),
            'categories' => $changes->categories($organisationId),
            'hasOwnPrices' => $organisationId !== null,
        ]);
    }
}
