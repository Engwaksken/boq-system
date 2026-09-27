<?php

namespace App\Livewire\Concerns;

/**
 * Checkbox selection for paginated tables, shared by list pages with bulk actions.
 */
trait WithBulkSelection
{
    /** @var list<string> */
    public array $selected = [];

    /**
     * Select every row on the current page, or clear them when all are already selected.
     *
     * @param  list<int|string>  $ids
     */
    public function togglePageSelection(array $ids): void
    {
        $ids = array_map('strval', $ids);

        $this->selected = array_diff($ids, $this->selected) === []
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique(array_merge($this->selected, $ids)));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /**
     * Selection resets when the page changes so hidden rows are never acted on.
     */
    public function updatedPaginators(): void
    {
        $this->clearSelection();
    }

    /** @return list<int> */
    protected function selectedIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $this->selected))));
    }

    /**
     * Clear the selection and flash a summary of a finished bulk action.
     */
    protected function finishBulkAction(int $count, string $verb, string $flashKey = 'message'): void
    {
        $this->clearSelection();

        session()->flash($flashKey, $count === 1 ? "1 item {$verb}." : "{$count} items {$verb}.");
    }
}
