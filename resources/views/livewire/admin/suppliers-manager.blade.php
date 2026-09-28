<div class="boq-page-stack" x-data="{ confirmDeactivate: false, deactivateId: null, deactivateName: '' }">
    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title"><i class="fas fa-truck"></i> {{ __('Suppliers & Factories') }}</h1>
            <p class="boq-page-subtitle">{{ __('Hardware suppliers and manufacturers, their websites for AI price research, price lists and quotations.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="openImport" class="boq-btn-secondary"><i class="fas fa-file-csv"></i> {{ __('Import CSV') }}</button>
            <button type="button" wire:click="create('factory')" class="boq-btn-secondary"><i class="fas fa-industry"></i> {{ __('Add Factory') }}</button>
            <button type="button" wire:click="create('supplier')" class="boq-btn-primary"><i class="fas fa-plus"></i> {{ __('Add Supplier') }}</button>
        </div>
    </div>

    @if(session('message'))<div class="boq-flash"><i class="fas fa-circle-check"></i> {{ session('message') }}</div>@endif

    {{-- Statistics: supplier cards filter the list, price cards open the price lists --}}
    <div class="boq-stats-grid">
        <button type="button" wire:click="filterBy('supplier')" class="boq-stat-link" aria-label="{{ __('Show suppliers') }}">
            <x-stat-card :label="__('Total Suppliers')" :value="number_format($stats['suppliers'])" icon="fa-store" color="green" />
        </button>
        <button type="button" wire:click="filterBy('supplier', 'active')" class="boq-stat-link" aria-label="{{ __('Show active suppliers') }}">
            <x-stat-card :label="__('Active Suppliers')" :value="number_format($stats['active_suppliers'])" icon="fa-circle-check" color="blue" />
        </button>
        <button type="button" wire:click="filterBy('factory')" class="boq-stat-link" aria-label="{{ __('Show factories') }}">
            <x-stat-card :label="__('Total Factories')" :value="number_format($stats['factories'])" icon="fa-industry" color="purple" />
        </button>
        <button type="button" wire:click="filterBy('factory', 'active')" class="boq-stat-link" aria-label="{{ __('Show active factories') }}">
            <x-stat-card :label="__('Active Factories')" :value="number_format($stats['active_factories'])" icon="fa-circle-check" color="amber" />
        </button>

        <a href="{{ route('hardware-prices.index', ['priceType' => 'hardware']) }}" class="boq-stat-link">
            <x-stat-card :label="__('Total Hardware Items')" :value="number_format($stats['hardware_items'])" icon="fa-screwdriver-wrench" color="green" />
        </a>
        <a href="{{ route('hardware-prices.index', ['priceType' => 'factory']) }}" class="boq-stat-link">
            <x-stat-card :label="__('Total Factory Items')" :value="number_format($stats['factory_items'])" icon="fa-boxes-stacked" color="purple" />
        </a>
        <a href="{{ route('hardware-prices.index') }}" class="boq-stat-link">
            <x-stat-card :label="__('Average Price').' ('.$stats['currency'].')'" :value="\App\Support\Format::money($stats['average'], $stats['currency'])" icon="fa-scale-balanced" color="blue" />
        </a>
        <a href="{{ $stats['lowest'] ? route('hardware-prices.show', $stats['lowest']->id) : route('hardware-prices.index') }}" class="boq-stat-link">
            <x-stat-card :label="__('Lowest Price Available')" :value="$stats['lowest'] ? \App\Support\Format::money($stats['lowest']->price, $stats['lowest']->currency) : '—'" :hint="$stats['lowest']?->item_name" icon="fa-arrow-down" color="green" />
        </a>
        <a href="{{ $stats['highest'] ? route('hardware-prices.show', $stats['highest']->id) : route('hardware-prices.index') }}" class="boq-stat-link">
            <x-stat-card :label="__('Highest Price Available')" :value="$stats['highest'] ? \App\Support\Format::money($stats['highest']->price, $stats['highest']->currency) : '—'" :hint="$stats['highest']?->item_name" icon="fa-arrow-up" color="red" />
        </a>
        <a href="{{ route('hardware-prices.index', ['updated' => 'today']) }}" class="boq-stat-link">
            <x-stat-card :label="__('Prices Updated Today')" :value="number_format($stats['updated_today'])" icon="fa-calendar-day" color="amber" />
        </a>
        <a href="{{ route('hardware-prices.index', ['updated' => 'week']) }}" class="boq-stat-link">
            <x-stat-card :label="__('Prices Updated This Week')" :value="number_format($stats['updated_week'])" icon="fa-calendar-week" color="blue" />
        </a>
    </div>

    <div class="boq-panel overflow-hidden">
        <div class="boq-tabs" role="tablist" aria-label="{{ __('Supplier type') }}">
            @foreach(['' => [__('All'), 'fa-list'], 'supplier' => [__('Suppliers'), 'fa-store'], 'factory' => [__('Factories'), 'fa-industry']] as $value => [$label, $icon])
                <button type="button" role="tab" wire:click="filterBy('{{ $value }}', '{{ $statusFilter }}')" class="boq-tab {{ $typeFilter === $value ? 'is-active' : '' }}" aria-selected="{{ $typeFilter === $value ? 'true' : 'false' }}">
                    <i class="fas {{ $icon }}"></i> {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="boq-admin-filter-row">
            <div class="boq-input-icon-wrap">
                <i class="fas fa-magnifying-glass boq-input-icon"></i>
                <input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name, contact, phone, email or website...') }}" class="boq-field boq-field-with-icon">
            </div>
            <select wire:model.live="statusFilter" class="boq-field" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </select>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-circle-check"></i> {{ __('Activate') }}</button>
            <button type="button" wire:click="bulkSetActive(false)" wire:confirm="{{ __('Deactivate the selected suppliers? Historical quotations are kept.') }}" class="boq-btn-secondary"><i class="fas fa-ban"></i> {{ __('Deactivate') }}</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected suppliers? Suppliers with rates or quotations are skipped.') }}" class="boq-btn-danger"><i class="fas fa-trash"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>

        <div class="boq-table-wrapper">
            <table class="boq-table">
                <thead>
                    <tr>
                        <th class="boq-check-col"><x-select-all :ids="$suppliers->pluck('id')" :selected="$selected" /></th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Contact') }}</th>
                        <th>{{ __('Website') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Rates') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr wire:key="supplier-{{ $supplier->id }}">
                            <td class="boq-check-col"><x-select-row :id="$supplier->id" /></td>
                            <td><div class="boq-table-title">{{ $supplier->name }}</div><div class="boq-table-subtitle">{{ $supplier->code }}</div></td>
                            <td>
                                <span class="boq-badge {{ $supplier->type === 'factory' ? 'boq-badge-factory' : 'boq-badge-info' }}">
                                    <i class="fas {{ $supplier->type === 'factory' ? 'fa-industry' : 'fa-store' }}"></i>
                                    {{ $supplier->type === 'factory' ? __('Factory') : __('Supplier') }}
                                </span>
                            </td>
                            <td class="text-sm"><div>{{ $supplier->contact_name ?: '—' }}</div><div class="text-xs text-slate-500">{{ $supplier->phone }}{{ $supplier->phone && $supplier->email ? ' · ' : '' }}{{ $supplier->email }}</div></td>
                            <td class="text-sm">
                                @if($supplier->website_url)
                                    <a href="{{ $supplier->website_url }}" target="_blank" rel="noopener noreferrer" class="boq-table-link">
                                        {{ parse_url($supplier->website_url, PHP_URL_HOST) ?: $supplier->website_url }} <i class="fas fa-arrow-up-right-from-square text-xs"></i>
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ collect([$supplier->location, $supplier->country ? ($countries[$supplier->country] ?? $supplier->country) : null])->filter()->implode(', ') ?: '—' }}</td>
                            <td class="text-sm">{{ $supplier->rates_count }}</td>
                            <td><span class="boq-badge {{ $supplier->is_active ? 'boq-badge-success' : 'boq-badge-danger' }}">{{ $supplier->is_active ? __('Active') : __('Inactive') }}</span></td>
                            <td>
                                <div class="boq-table-actions justify-end">
                                    <button type="button" wire:click="edit({{ $supplier->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen"></i></button>
                                    <button type="button" wire:click="toggleActive({{ $supplier->id }})" class="boq-icon-btn" title="{{ $supplier->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $supplier->is_active ? __('Deactivate') : __('Activate') }}"><i class="fas {{ $supplier->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="boq-table-empty">{{ __('No suppliers found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())<div class="boq-pagination">{{ $suppliers->links() }}</div>@endif
    </div>

    {{-- Add / edit --}}
    @if($showForm)
        <div class="boq-modal-backdrop" wire:key="supplier-form-modal" x-data @keydown.escape.window="$wire.cancel()" role="dialog" aria-modal="true" aria-labelledby="supplier-form-title">
            <form wire:submit="save" class="boq-modal boq-modal-lg">
                <div class="boq-modal-head">
                    <h2 id="supplier-form-title">
                        <i class="fas {{ ($form['type'] ?? '') === 'factory' ? 'fa-industry' : 'fa-store' }}"></i>
                        {{ $editingId ? __('Edit') : __('Add') }} {{ ($form['type'] ?? '') === 'factory' ? __('Factory') : __('Supplier') }}
                    </h2>
                    <button type="button" wire:click="cancel" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="boq-modal-body boq-form-grid">
                    <div class="boq-form-span-2">
                        <label for="sup-name" class="boq-field-label">{{ __('Name') }} *</label>
                        <input id="sup-name" wire:model="form.name" class="boq-field" placeholder="{{ __('e.g. Example Hardware Ltd') }}">
                        @error('form.name') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sup-type" class="boq-field-label">{{ __('Type') }} *</label>
                        <select id="sup-type" wire:model.live="form.type" class="boq-field">
                            <option value="supplier">{{ __('Supplier / hardware shop') }}</option>
                            <option value="factory">{{ __('Factory / manufacturer') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="sup-currency" class="boq-field-label">{{ __('Preferred currency') }}</label>
                        <x-currency-select id="sup-currency" wire:model="form.currency" :current="$form['currency'] ?? null" />
                    </div>
                    <div class="boq-form-span-2">
                        <label for="sup-website" class="boq-field-label">{{ __('Website URL') }}</label>
                        <input id="sup-website" type="url" wire:model="form.website_url" class="boq-field" placeholder="https://example.com">
                        <p class="boq-field-help">{{ __('The AI price scanner uses this website as a price source.') }}</p>
                        @error('form.website_url') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sup-contact" class="boq-field-label">{{ __('Contact person') }}</label>
                        <input id="sup-contact" wire:model="form.contact_name" class="boq-field" placeholder="{{ __('e.g. full name') }}">
                    </div>
                    <div>
                        <label for="sup-phone" class="boq-field-label">{{ __('Phone') }}</label>
                        <input id="sup-phone" type="tel" wire:model="form.phone" class="boq-field" placeholder="+1 202 555 0143">
                        @error('form.phone') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sup-email" class="boq-field-label">{{ __('Email') }}</label>
                        <input id="sup-email" type="email" wire:model="form.email" class="boq-field" placeholder="name@example.com">
                        @error('form.email') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sup-country" class="boq-field-label">{{ __('Country') }}</label>
                        <select id="sup-country" wire:model="form.country" class="boq-field">
                            <option value="">{{ __('Select...') }}</option>
                            @foreach($countries as $iso => $name)<option value="{{ $iso }}">{{ $name }}</option>@endforeach
                        </select>
                        @error('form.country') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sup-location" class="boq-field-label">{{ __('District/City') }}</label>
                        <input id="sup-location" wire:model="form.location" class="boq-field" placeholder="{{ __('e.g. city, town or market') }}">
                    </div>
                    <div>
                        <label for="sup-region" class="boq-field-label">{{ __('Region') }}</label>
                        <input id="sup-region" wire:model="form.region" class="boq-field" placeholder="{{ __('e.g. Central region') }}">
                    </div>
                    <div class="boq-form-span-2">
                        <label for="sup-address" class="boq-field-label">{{ __('Physical Address') }}</label>
                        <input id="sup-address" wire:model="form.address" class="boq-field" placeholder="{{ __('e.g. Plot 1, Main Street') }}">
                    </div>
                    <div class="boq-form-span-2">
                        <label for="sup-materials" class="boq-field-label">{{ __('Materials / services supplied') }}</label>
                        <input id="sup-materials" wire:model="form.materials_text" class="boq-field" placeholder="{{ __('e.g. cement, steel, roofing') }}">
                    </div>
                    <div class="boq-form-span-2">
                        <label for="sup-notes" class="boq-field-label">{{ __('Notes') }}</label>
                        <textarea id="sup-notes" wire:model="form.notes" rows="2" class="boq-field boq-textarea" placeholder="{{ __('Add notes...') }}"></textarea>
                    </div>
                </div>

                <div class="boq-modal-foot">
                    <button type="button" wire:click="cancel" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="boq-btn-primary"><i class="fas fa-floppy-disk"></i> {{ __('Save') }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- CSV import: upload -> preview -> confirm --}}
    @if($showImport)
        <div class="boq-modal-backdrop" wire:key="supplier-import-modal" role="dialog" aria-modal="true" aria-labelledby="supplier-import-title">
            <div class="boq-modal boq-modal-xl">
                <div class="boq-modal-head">
                    <h2 id="supplier-import-title"><i class="fas fa-file-csv"></i> {{ __('Import Suppliers from CSV') }}</h2>
                    <button type="button" wire:click="closeImport" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="boq-modal-body space-y-4">
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                        <li>{{ __('Download the template and fill in one supplier or factory per row.') }}</li>
                        <li>{{ __('Upload the file and preview: every row is validated and duplicates are detected by name, email, phone or website.') }}</li>
                        <li>{{ __('Confirm to import the valid rows. Download the error report to fix any skipped rows.') }}</li>
                    </ol>

                    <div class="flex flex-wrap items-end gap-3">
                        <button type="button" wire:click="downloadTemplate" class="boq-btn-secondary"><i class="fas fa-download"></i> {{ __('Download CSV Template') }}</button>

                        <label class="boq-btn-secondary cursor-pointer">
                            <i class="fas fa-upload"></i> {{ __('Upload CSV') }}
                            <input type="file" wire:model="importFile" accept=".csv,text/csv" class="hidden">
                        </label>

                        <span wire:loading wire:target="importFile" class="text-sm text-slate-500"><i class="fas fa-spinner fa-spin"></i> {{ __('Uploading file...') }}</span>
                        @if($importFile && ! $errors->has('importFile'))
                            <span class="text-sm text-slate-600"><i class="fas fa-file"></i> {{ $importFile->getClientOriginalName() }}</span>
                        @endif

                        <button type="button" wire:click="previewImport" wire:loading.attr="disabled" wire:target="previewImport,importFile" class="boq-btn-primary" @disabled(! $importFile)>
                            <i class="fas fa-eye"></i> {{ __('Preview Import') }}
                        </button>
                    </div>
                    @error('importFile') <p class="boq-field-error">{{ $message }}</p> @enderror
                    @if($importError)<div class="boq-flash boq-flash-error"><i class="fas fa-circle-exclamation"></i> {{ $importError }}</div>@endif

                    @if($importPreview)
                        <div class="boq-stats-grid">
                            <x-stat-card :label="__('Ready to import')" :value="count($importPreview['valid'])" icon="fa-circle-check" color="green" />
                            <x-stat-card :label="__('Duplicates')" :value="count($importPreview['duplicates'])" icon="fa-clone" color="amber" />
                            <x-stat-card :label="__('Failed validation')" :value="count($importPreview['failed'])" icon="fa-circle-xmark" color="red" />
                            <x-stat-card :label="__('Rows in file')" :value="count($importPreview['valid']) + count($importPreview['duplicates']) + count($importPreview['failed'])" icon="fa-table-list" color="blue" />
                        </div>

                        <div class="boq-table-wrapper max-h-96 overflow-y-auto rounded-lg border border-slate-200">
                            <table class="boq-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Line') }}</th>
                                        <th>{{ __('Result') }}</th>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Email') }}</th>
                                        <th>{{ __('Phone') }}</th>
                                        <th>{{ __('Website') }}</th>
                                        <th>{{ __('Details') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(collect($importPreview['failed'])->map(fn ($r) => $r + ['result' => 'failed'])
                                        ->merge(collect($importPreview['duplicates'])->map(fn ($r) => $r + ['result' => 'duplicate']))
                                        ->merge(collect($importPreview['valid'])->map(fn ($r) => $r + ['result' => 'valid']))
                                        ->sortBy('line') as $row)
                                        <tr wire:key="preview-{{ $row['line'] }}">
                                            <td>{{ $row['line'] }}</td>
                                            <td>
                                                <span class="boq-badge {{ ['valid' => 'boq-badge-success', 'duplicate' => 'boq-badge-warning', 'failed' => 'boq-badge-danger'][$row['result']] }}">
                                                    {{ ['valid' => __('Ready'), 'duplicate' => __('Duplicate'), 'failed' => __('Failed')][$row['result']] }}
                                                </span>
                                            </td>
                                            <td>{{ $row['name'] ?: '—' }}</td>
                                            <td>{{ $row['type'] }}</td>
                                            <td>{{ $row['email'] ?: '—' }}</td>
                                            <td>{{ $row['phone'] ?: '—' }}</td>
                                            <td class="max-w-xs truncate">{{ $row['website_url'] ?: '—' }}</td>
                                            <td class="text-xs {{ $row['result'] === 'valid' ? 'text-slate-400' : 'text-red-700' }}">{{ implode(' ', $row['errors'] ?? []) ?: __('OK') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="boq-modal-foot">
                    @if($importPreview && (count($importPreview['failed']) || count($importPreview['duplicates'])))
                        <button type="button" wire:click="downloadImportErrors" class="boq-btn-secondary mr-auto"><i class="fas fa-file-arrow-down"></i> {{ __('Download Import Errors') }}</button>
                    @endif
                    <button type="button" wire:click="closeImport" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport" class="boq-btn-primary" @disabled(! $importPreview || count($importPreview['valid']) === 0)>
                        <i class="fas fa-check"></i> {{ __('Confirm Import') }}{{ $importPreview ? ' ('.count($importPreview['valid']).')' : '' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div x-show="confirmDeactivate" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmDeactivate=false">
        <div class="boq-modal boq-modal-sm" @click.stop>
            <div class="boq-modal-head"><h2>{{ __('Deactivate supplier?') }}</h2><button type="button" @click="confirmDeactivate=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button></div>
            <div class="boq-modal-body text-sm text-slate-600">{{ __('Historical quotations are preserved.') }}</div>
            <div class="boq-modal-foot"><button type="button" @click="confirmDeactivate=false" class="boq-btn-secondary">{{ __('Cancel') }}</button><button type="button" @click="$wire.deactivate(deactivateId); confirmDeactivate=false" class="boq-btn-danger">{{ __('Deactivate') }}</button></div>
        </div>
    </div>
</div>
