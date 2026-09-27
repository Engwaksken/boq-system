<?php

namespace App\Livewire\Concerns;

/**
 * Starts list pages at the user's "Rows per page" preference (Profile > Preferences).
 * Livewire calls mountUsesPreferredPerPage() automatically before the component's mount().
 */
trait UsesPreferredPerPage
{
    public function mountUsesPreferredPerPage(): void
    {
        $preferred = (int) (auth()->user()?->displayPreferences()['per_page'] ?? 0);

        if (! in_array($preferred, [10, 20, 50, 100], true)) {
            return;
        }

        // Respect components that restrict page sizes to their own option list.
        if (property_exists($this, 'perPageOptions') && is_array($this->perPageOptions) && ! in_array($preferred, $this->perPageOptions, true)) {
            return;
        }

        $this->perPage = $preferred;
    }
}
