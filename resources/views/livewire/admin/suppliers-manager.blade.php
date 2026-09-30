<div class="boq-page-stack" x-data="{ confirmDeactivate: false, deactivateId: null, deactivateName: '' }">
    <x-ui.page-header
        :title="__('Suppliers & Factories')"
        icon="fa-truck"
        :subtitle="__('Hardware suppliers and manufacturers, their websites for AI price research, price lists and quotations.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-location-dot" wire:click="openLocationScan">{{ __('Scan by Location') }}</x-ui.button>
            <x-ui.button variant="secondary" icon="fa-file-csv" wire:click="openImport">{{ __('Import CSV') }}</x-ui.button>
            <x-ui.button variant="secondary" icon="fa-table-list" wire:click="openBulkAdd">{{ __('Add Many') }}</x-ui.button>
            <x-ui.button variant="secondary" icon="fa-industry" wire:click="create('factory')">{{ __('Add Factory') }}</x-ui.button>
            <x-ui.button icon="fa-plus" wire:click="create('supplier')">{{ __('Add Supplier') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    @if($pendingScans > 0)
        {{-- Runs scans the queue worker has not picked up and refreshes the list. --}}
        <div wire:poll.10s="runQueuedScans" class="boq-panel flex items-center gap-3 px-4 py-3 text-sm text-slate-700" role="status">
            <i class="fas fa-spinner fa-spin text-brand-600" aria-hidden="true"></i>
            <span>{{ trans_choice(':count supplier website is being scanned for prices. Keep this page open to speed it up.|:count supplier websites are being scanned for prices. Keep this page open to speed it up.', $pendingScans, ['count' => $pendingScans]) }}</span>
        </div>
    @endif

    {{-- Statistics: supplier cards filter the list, price cards open the price lists --}}
    <div class="boq-stats-grid">
        <x-stat-card wire:click="filterBy('supplier')" :active="$typeFilter === 'supplier' && $statusFilter === ''" :aria-label="__('Show suppliers')" :label="__('Total Suppliers')" :value="\App\Support\Format::number($stats['suppliers'], 0)" icon="fa-store" color="green" />
        <x-stat-card wire:click="filterBy('supplier', 'active')" :active="$typeFilter === 'supplier' && $statusFilter === 'active'" :aria-label="__('Show active suppliers')" :label="__('Active Suppliers')" :value="\App\Support\Format::number($stats['active_suppliers'], 0)" icon="fa-circle-check" color="blue" />
        <x-stat-card wire:click="filterBy('factory')" :active="$typeFilter === 'factory' && $statusFilter === ''" :aria-label="__('Show factories')" :label="__('Total Factories')" :value="\App\Support\Format::number($stats['factories'], 0)" icon="fa-industry" color="purple" />
        <x-stat-card wire:click="filterBy('factory', 'active')" :active="$typeFilter === 'factory' && $statusFilter === 'active'" :aria-label="__('Show active factories')" :label="__('Active Factories')" :value="\App\Support\Format::number($stats['active_factories'], 0)" icon="fa-circle-check" color="amber" />

        <a href="{{ route('hardware-prices.index', ['priceType' => 'hardware']) }}" class="boq-stat-link">
            <x-stat-card :label="__('Total Hardware Items')" :value="\App\Support\Format::number($stats['hardware_items'], 0)" icon="fa-screwdriver-wrench" color="green" />
        </a>
        <a href="{{ route('hardware-prices.index', ['priceType' => 'factory']) }}" class="boq-stat-link">
            <x-stat-card :label="__('Total Factory Items')" :value="\App\Support\Format::number($stats['factory_items'], 0)" icon="fa-boxes-stacked" color="purple" />
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
            <x-stat-card :label="__('Prices Updated Today')" :value="\App\Support\Format::number($stats['updated_today'], 0)" icon="fa-calendar-day" color="amber" />
        </a>
        <a href="{{ route('hardware-prices.index', ['updated' => 'week']) }}" class="boq-stat-link">
            <x-stat-card :label="__('Prices Updated This Week')" :value="\App\Support\Format::number($stats['updated_week'], 0)" icon="fa-calendar-week" color="blue" />
        </a>
    </div>

    <div class="boq-panel">
        <x-ui.tabs :label="__('Supplier type')">
            @foreach(['' => [__('All'), 'fa-list'], 'supplier' => [__('Suppliers'), 'fa-store'], 'factory' => [__('Factories'), 'fa-industry']] as $value => [$label, $icon])
                <x-ui.tab wire:click="filterBy('{{ $value }}', '{{ $statusFilter }}')" wire:key="supplier-tab-{{ $value ?: 'all' }}" :icon="$icon" :active="$typeFilter === $value">{{ $label }}</x-ui.tab>
            @endforeach
        </x-ui.tabs>

        <div class="boq-toolbar border-b border-slate-200">
            <div class="boq-input-icon-wrap boq-toolbar-grow">
                <i class="fas fa-magnifying-glass boq-input-icon" aria-hidden="true"></i>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name, contact, phone, email or website...') }}" aria-label="{{ __('Search') }}" class="boq-field boq-field-with-icon">
            </div>
            <select wire:model.live="locationFilter" class="boq-field w-full sm:w-52" aria-label="{{ __('Location') }}">
                <option value="">{{ __('All locations') }}</option>
                @foreach($locations as $place)<option value="{{ $place }}">{{ $place }}</option>@endforeach
                @if($locationFilter !== '' && ! in_array($locationFilter, $locations, true))<option value="{{ $locationFilter }}">{{ $locationFilter }}</option>@endif
            </select>
            <select wire:model.live="statusFilter" class="boq-field w-full sm:w-44" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </select>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkScan" wire:loading.attr="disabled" wire:target="bulkScan" class="boq-btn-secondary"><i class="fas fa-magnifying-glass-dollar"></i> {{ __('Scan prices') }}</button>
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
                        <th>{{ __('Prices') }}</th>
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
                            <td>{{ \App\Support\Format::number($supplier->rates_count ?? 0, 0) }}</td>
                            <td>
                                {{ \App\Support\Format::number($supplier->hardware_prices_count ?? 0, 0) }}
                                @php $scanState = data_get($supplier->metadata, 'price_scan.status'); @endphp
                                @if(in_array($scanState, ['queued', 'running'], true))
                                    <div class="boq-table-subtitle text-brand-700"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> {{ $scanState === 'running' ? __('Scanning...') : __('Waiting to scan') }}</div>
                                @elseif($scanState === 'failed')
                                    <div class="boq-table-subtitle text-red-700" title="{{ data_get($supplier->metadata, 'price_scan.error') }}"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __('Scan failed') }}</div>
                                @elseif($scanned = data_get($supplier->metadata, 'last_price_scan.at'))
                                    <div class="boq-table-subtitle">{{ __('Scanned') }} {{ \Illuminate\Support\Carbon::parse($scanned)->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td><x-ui.status :status="$supplier->is_active ? 'active' : 'inactive'" /></td>
                            <td>
                                <div class="boq-table-actions justify-end">
                                    @if($supplier->website_url)
                                        <button type="button" wire:click="scanPrices({{ $supplier->id }})" wire:loading.attr="disabled" wire:target="scanPrices({{ $supplier->id }})" class="boq-icon-btn" title="{{ __('Scan website prices') }}" aria-label="{{ __('Scan website prices') }}">
                                            <i class="fas fa-magnifying-glass-dollar" wire:loading.remove wire:target="scanPrices({{ $supplier->id }})" aria-hidden="true"></i>
                                            <i class="fas fa-spinner fa-spin" wire:loading wire:target="scanPrices({{ $supplier->id }})" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="edit({{ $supplier->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen"></i></button>
                                    <button type="button" wire:click="toggleActive({{ $supplier->id }})" class="boq-icon-btn" title="{{ $supplier->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $supplier->is_active ? __('Deactivate') : __('Activate') }}"><i class="fas {{ $supplier->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="p-0"><x-ui.empty-state icon="fa-truck" :title="__('No suppliers found.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())<div class="boq-pagination">{{ $suppliers->links() }}</div>@endif
    </div>

    {{-- Add / edit --}}
    @if($showForm)
        <div class="boq-modal-backdrop" wire:key="supplier-form-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.cancel()" role="dialog" aria-modal="true" aria-labelledby="supplier-form-title">
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
                        <label for="sup-name" class="boq-field-label">{{ __('Name') }} <span class="boq-field-required" aria-hidden="true">*</span></label>
                        <input id="sup-name" wire:model="form.name" class="boq-field" placeholder="{{ __('e.g. Example Hardware Ltd') }}">
                        @error('form.name') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sup-type" class="boq-field-label">{{ __('Type') }} <span class="boq-field-required" aria-hidden="true">*</span></label>
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
                        <label class="boq-check mt-2">
                            <input type="checkbox" wire:model="scanAfterSave">
                            {{ __('Scan this website for prices after saving and add them to the general prices') }}
                        </label>
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
                    <button type="submit" class="boq-btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="fas fa-floppy-disk" wire:loading.remove wire:target="save"></i><i class="fas fa-spinner fa-spin" wire:loading wire:target="save"></i> {{ __('Save') }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Scan every supplier with a website in a location --}}
    @if($showLocationScan)
        <div class="boq-modal-backdrop" wire:key="supplier-location-scan-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.closeLocationScan()" role="dialog" aria-modal="true" aria-labelledby="supplier-location-scan-title">
            <form wire:submit="scanLocationSuppliers" class="boq-modal">
                <div class="boq-modal-head">
                    <h2 id="supplier-location-scan-title"><i class="fas fa-location-dot"></i> {{ __('Scan Suppliers by Location') }}</h2>
                    <button type="button" wire:click="closeLocationScan" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="boq-modal-body space-y-4">
                    <p class="text-sm text-slate-600">{{ __('Scans the websites of the active suppliers and factories in a location for their prices and adds them to the general prices for that location.') }}</p>
                    <div>
                        <label for="scan-location" class="boq-field-label">{{ __('Location') }}</label>
                        <input id="scan-location" list="supplier-locations" wire:model.live.debounce.400ms="scanLocation" class="boq-field" placeholder="{{ __('e.g. city, district or region (empty = all locations)') }}">
                        <datalist id="supplier-locations">@foreach($locations as $place)<option value="{{ $place }}"></option>@endforeach</datalist>
                        @error('scanLocation') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="boq-form-grid">
                        <div>
                            <label for="scan-type" class="boq-field-label">{{ __('Type') }}</label>
                            <select id="scan-type" wire:model.live="scanType" class="boq-field">
                                <option value="">{{ __('Suppliers and factories') }}</option>
                                <option value="supplier">{{ __('Suppliers only') }}</option>
                                <option value="factory">{{ __('Factories only') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="scan-limit" class="boq-field-label">{{ __('Items per supplier') }}</label>
                            <input id="scan-limit" type="number" min="1" max="40" wire:model="scanLimit" class="boq-field">
                        </div>
                    </div>
                    <x-ui.alert :type="$scanCount ? 'info' : 'warning'" :autohide="false">
                        {{ trans_choice(':count supplier with a website will be scanned.|:count suppliers with a website will be scanned.', $scanCount, ['count' => $scanCount]) }}
                    </x-ui.alert>
                </div>
                <div class="boq-modal-foot">
                    <button type="button" wire:click="closeLocationScan" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="boq-btn-primary" wire:loading.attr="disabled" wire:target="scanLocationSuppliers" @disabled($scanCount === 0)>
                        <i class="fas fa-magnifying-glass-dollar" wire:loading.remove wire:target="scanLocationSuppliers"></i><i class="fas fa-spinner fa-spin" wire:loading wire:target="scanLocationSuppliers"></i> {{ __('Scan Prices') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Add many suppliers at once: type in the table or paste from a spreadsheet --}}
    @if($showBulkAdd)
        <div class="boq-modal-backdrop" wire:key="supplier-bulk-add-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.closeBulkAdd()" role="dialog" aria-modal="true" aria-labelledby="supplier-bulk-add-title">
            <form wire:submit="saveBulk" class="boq-modal boq-modal-xl">
                <div class="boq-modal-head">
                    <h2 id="supplier-bulk-add-title"><i class="fas fa-table-list"></i> {{ __('Add Many Suppliers') }}</h2>
                    <button type="button" wire:click="closeBulkAdd" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="boq-modal-body space-y-4">
                    <details class="rounded-lg border border-slate-200 p-3" @if(count(array_filter(array_column($bulkRows, 'name'))) === 0) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700"><i class="fas fa-paste"></i> {{ __('Paste a list') }}</summary>
                        <p class="mt-2 text-xs text-slate-500">{{ __('Copy rows from Excel or Google Sheets, or type one supplier per line with the columns separated by commas: name, contact person, phone, email, website, country, district/city, address, type (supplier or factory). A header row may set a different order.') }}</p>
                        <textarea wire:model="bulkPaste" rows="4" class="boq-field boq-textarea mt-2 font-mono text-xs" aria-label="{{ __('Paste a list') }}" placeholder="Example Hardware Ltd, Jane Doe, +256 700 000 001, sales@example.com, example.com, UG, Kampala"></textarea>
                        @error('bulkPaste') <p class="boq-field-error">{{ $message }}</p> @enderror
                        <button type="button" wire:click="fillFromPaste" class="boq-btn-secondary mt-2"><i class="fas fa-table-cells"></i> {{ __('Add to table') }}</button>
                    </details>

                    @error('bulkRows') <x-ui.alert type="warning" :autohide="false">{{ $message }}</x-ui.alert> @enderror

                    <div class="boq-table-wrapper max-h-[28rem] overflow-y-auto rounded-lg border border-slate-200">
                        <table class="boq-table boq-table-compact">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('Name') }} <span class="boq-field-required" aria-hidden="true">*</span></th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Contact person') }}</th>
                                    <th>{{ __('Phone') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Website') }}</th>
                                    <th>{{ __('District/City') }}</th>
                                    <th>{{ __('Country') }}</th>
                                    <th>{{ __('Materials') }}</th>
                                    <th><span class="sr-only">{{ __('Remove') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bulkRows as $i => $row)
                                    <tr wire:key="bulk-row-{{ $i }}" @class(['bg-red-50' => ! empty($bulkErrors[$i])])>
                                        <td class="text-xs text-slate-500">{{ $i + 1 }}</td>
                                        <td class="min-w-44">
                                            <input wire:model="bulkRows.{{ $i }}.name" class="boq-field" aria-label="{{ __('Name') }} {{ $i + 1 }}">
                                            @if(! empty($bulkErrors[$i]))<p class="boq-field-error">{{ implode(' ', $bulkErrors[$i]) }}</p>@endif
                                        </td>
                                        <td class="min-w-32">
                                            <select wire:model="bulkRows.{{ $i }}.type" class="boq-field" aria-label="{{ __('Type') }} {{ $i + 1 }}">
                                                <option value="supplier">{{ __('Supplier') }}</option>
                                                <option value="factory">{{ __('Factory') }}</option>
                                            </select>
                                        </td>
                                        <td class="min-w-36"><input wire:model="bulkRows.{{ $i }}.contact_name" class="boq-field" aria-label="{{ __('Contact person') }} {{ $i + 1 }}"></td>
                                        <td class="min-w-36"><input type="tel" wire:model="bulkRows.{{ $i }}.phone" class="boq-field" aria-label="{{ __('Phone') }} {{ $i + 1 }}"></td>
                                        <td class="min-w-44"><input type="email" wire:model="bulkRows.{{ $i }}.email" class="boq-field" aria-label="{{ __('Email') }} {{ $i + 1 }}"></td>
                                        <td class="min-w-44"><input wire:model="bulkRows.{{ $i }}.website_url" class="boq-field" placeholder="example.com" aria-label="{{ __('Website') }} {{ $i + 1 }}"></td>
                                        <td class="min-w-36"><input list="supplier-locations-bulk" wire:model="bulkRows.{{ $i }}.location" class="boq-field" aria-label="{{ __('District/City') }} {{ $i + 1 }}"></td>
                                        <td class="min-w-36">
                                            <select wire:model="bulkRows.{{ $i }}.country" class="boq-field" aria-label="{{ __('Country') }} {{ $i + 1 }}">
                                                <option value="">—</option>
                                                @foreach($countries as $iso => $name)<option value="{{ $iso }}">{{ $name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="min-w-40"><input wire:model="bulkRows.{{ $i }}.materials" class="boq-field" placeholder="{{ __('cement, steel') }}" aria-label="{{ __('Materials') }} {{ $i + 1 }}"></td>
                                        <td><button type="button" wire:click="removeBulkRow({{ $i }})" class="boq-icon-btn" title="{{ __('Remove') }}" aria-label="{{ __('Remove row :number', ['number' => $i + 1]) }}"><i class="fas fa-xmark"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <datalist id="supplier-locations-bulk">@foreach($locations as $place)<option value="{{ $place }}"></option>@endforeach</datalist>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button type="button" wire:click="addBulkRow" class="boq-btn-secondary"><i class="fas fa-plus"></i> {{ __('Add row') }}</button>
                        <label class="boq-check">
                            <input type="checkbox" wire:model="bulkScan">
                            {{ __('Scan the websites for prices after adding') }}
                        </label>
                    </div>
                </div>
                <div class="boq-modal-foot">
                    <button type="button" wire:click="closeBulkAdd" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="boq-btn-primary" wire:loading.attr="disabled" wire:target="saveBulk">
                        <i class="fas fa-floppy-disk" wire:loading.remove wire:target="saveBulk"></i><i class="fas fa-spinner fa-spin" wire:loading wire:target="saveBulk"></i> {{ __('Save All') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- CSV import: upload -> preview -> confirm --}}
    @if($showImport)
        <div class="boq-modal-backdrop" wire:key="supplier-import-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.closeImport()" role="dialog" aria-modal="true" aria-labelledby="supplier-import-title">
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

                        <input id="supplier-import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="peer sr-only">
                        <label for="supplier-import-file" class="boq-btn-secondary cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
                            <i class="fas fa-upload" aria-hidden="true"></i> {{ __('Upload CSV') }}
                        </label>

                        <span wire:loading wire:target="importFile" class="text-sm text-slate-500"><i class="fas fa-spinner fa-spin"></i> {{ __('Uploading file...') }}</span>
                        @if($importFile && ! $errors->has('importFile'))
                            <span class="text-sm text-slate-600"><i class="fas fa-file"></i> {{ $importFile->getClientOriginalName() }}</span>
                        @endif

                        <button type="button" wire:click="previewImport" wire:loading.attr="disabled" wire:target="previewImport,importFile" class="boq-btn-primary" @disabled(! $importFile)>
                            <i class="fas fa-eye" wire:loading.remove wire:target="previewImport"></i><i class="fas fa-spinner fa-spin" wire:loading wire:target="previewImport"></i> {{ __('Preview Import') }}
                        </button>
                    </div>
                    @error('importFile') <p class="boq-field-error">{{ $message }}</p> @enderror
                    @if($importError)<x-ui.alert type="error">{{ $importError }}</x-ui.alert>@endif

                    @if($importPreview)
                        <div class="boq-stats-grid boq-stats-compact">
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
                        <i class="fas fa-check" wire:loading.remove wire:target="confirmImport"></i><i class="fas fa-spinner fa-spin" wire:loading wire:target="confirmImport"></i> {{ __('Confirm Import') }}{{ $importPreview ? ' ('.count($importPreview['valid']).')' : '' }}
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
