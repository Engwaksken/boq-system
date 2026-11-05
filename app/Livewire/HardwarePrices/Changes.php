<?php

namespace App\Livewire\HardwarePrices;

use App\Models\HardwarePrice;
use Illuminate\Database\Eloquent\Builder;
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

    public const PERIODS = ['7', '30', '90', '365', 'all'];

    public const SORTS = ['recent', 'up', 'down'];

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
        $allowed = [
            'period' => self::PERIODS,
            'direction' => ['', 'up', 'down'],
            'source' => ['', 'general', 'mine'],
            'sort' => self::SORTS,
        ];
        if (isset($allowed[$property]) && ! in_array($this->{$property}, $allowed[$property], true)) {
            $this->reset($property);
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'period', 'direction', 'category', 'source', 'sort']);
        $this->resetPage();
    }

    private function baseQuery(?int $organisationId): Builder
    {
        return HardwarePrice::query()
            ->visibleTo($organisationId)
            ->where('is_active', true)
            ->whereNotNull('price_change')
            ->where('price_change', '!=', 0)
            ->when($this->period !== 'all', fn ($q) => $q->where('price_changed_at', '>=', now()->subDays((int) $this->period)))
            ->when($this->source === 'general', fn ($q) => $q->whereNull('organisation_id'))
            ->when($this->source === 'mine' && $organisationId !== null, fn ($q) => $q->where('organisation_id', $organisationId))
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->when(trim($this->search) !== '', fn ($q) => $q->search(trim($this->search)));
    }

    public function render()
    {
        $organisationId = auth()->user()->organisation_id;
        $base = $this->baseQuery($organisationId);

        $changes = (clone $base)
            ->when($this->direction === 'up', fn ($q) => $q->where('price_change', '>', 0))
            ->when($this->direction === 'down', fn ($q) => $q->where('price_change', '<', 0))
            ->when($this->sort === 'recent', fn ($q) => $q->orderByDesc('price_changed_at'))
            ->when($this->sort === 'up', fn ($q) => $q->orderByDesc('price_change_percent'))
            ->when($this->sort === 'down', fn ($q) => $q->orderBy('price_change_percent'))
            ->orderByDesc('id')
            ->paginate($this->exportPageSize($this->perPage));

        // Summary for the chosen period, source, category and search (both directions).
        $stats = [
            'total' => (clone $base)->count(),
            'up' => (clone $base)->where('price_change', '>', 0)->count(),
            'down' => (clone $base)->where('price_change', '<', 0)->count(),
            'average' => (clone $base)->avg('price_change_percent'),
        ];

        $categories = HardwarePrice::query()
            ->visibleTo($organisationId)
            ->whereNotNull('price_change')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('livewire.hardware-prices.changes', [
            'changes' => $changes,
            'stats' => $stats,
            'categories' => $categories,
            'hasOwnPrices' => $organisationId !== null,
        ]);
    }
}
