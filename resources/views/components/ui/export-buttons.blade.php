<x-ui.button type="button" variant="secondary" icon="fa-file-csv" wire:click="exportTables('csv')" loading="exportTables" :title="__('Export all matching records')">{{ __('CSV') }}</x-ui.button>
<x-ui.button type="button" variant="secondary" icon="fa-file-pdf" wire:click="exportTables('pdf')" loading="exportTables" :title="__('Export all matching records')">{{ __('PDF') }}</x-ui.button>
