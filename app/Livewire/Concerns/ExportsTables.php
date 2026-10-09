<?php

namespace App\Livewire\Concerns;

use App\Services\TableExportService;
use Illuminate\Contracts\Pagination\Paginator;

trait ExportsTables
{
    // Server-only state: clients cannot request unpaginated rendering themselves.
    protected bool $exportingTables = false;

    public function canExportTables(): bool
    {
        return true;
    }

    protected function exportPageSize(int $normalSize): int
    {
        return $this->exportingTables ? PHP_INT_MAX : $normalSize;
    }

    protected function exportViewData(): array
    {
        return app()->call([$this, 'render'])->getData();
    }

    public function exportTables(string $format)
    {
        abort_unless(auth()->check(), 401);
        if (str_starts_with(static::class, 'App\\Livewire\\Admin\\')) {
            $tenantAdminPages = [\App\Livewire\Admin\AiProviders::class, \App\Livewire\Admin\CategoriesManager::class];
            abort_unless(auth()->user()->isSuperAdmin() || (in_array(static::class, $tenantAdminPages, true) && auth()->user()->hasAnyRole(['administrator', 'admin'])), 403);
        }
        if (str_starts_with(static::class, 'App\\Livewire\\HardwarePrices\\') || static::class === \App\Livewire\SupplierRatings\Index::class) {
            abort_unless(auth()->user()->hasPermission('hardware-prices.view'), 403);
        }
        abort_unless(in_array($format, ['csv', 'pdf'], true), 422);
        $definitions = config('page-exports')[static::class] ?? [];
        abort_if($definitions === [], 404);
        if (property_exists($this, 'activeTab') && isset($definitions[$this->activeTab])) {
            $definitions = [$this->activeTab => $definitions[$this->activeTab]];
        }
        $paginators = property_exists($this, 'paginators') ? $this->paginators : null;
        $page = property_exists($this, 'page') ? $this->page : null;
        try {
            $this->exportingTables = true;
            if ($paginators !== null) {
                $this->paginators = array_fill_keys(array_keys($paginators), 1);
            }
            if ($page !== null) {
                $this->page = 1;
            }
            $data = $this->exportViewData();
            $sections = [];
            foreach ($definitions as $key => $columns) {
                $source = $data[$key] ?? null;
                if ($source === null) {
                    continue;
                }
                $rows = $source instanceof Paginator ? $source->items() : $source;
                $sections[] = ['title' => __(ucwords(str_replace('_', ' ', $key))), 'columns' => $columns,
                    'rows' => collect($rows)->map(fn ($row) => array_map(fn ($field) => data_get($row, $field), array_keys($columns)))->all()];
            }
        } finally {
            $this->exportingTables = false;
            if ($paginators !== null) {
                $this->paginators = $paginators;
            }
            if ($page !== null) {
                $this->page = $page;
            }
        }
        abort_if($sections === [], 403);

        return app(TableExportService::class)->download($sections, $format);
    }
}
