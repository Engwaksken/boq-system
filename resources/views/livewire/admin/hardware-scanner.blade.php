<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Price Scanner')"
        icon="fa-magnifying-glass-dollar"
        :subtitle="__('Scan hardware market prices or factory/manufacturer prices for any construction category.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-file-arrow-down" wire:click="downloadCsvTemplate">{{ __('CSV Template') }}</x-ui.button>
            <x-ui.button variant="secondary" icon="fa-folder-plus" wire:click="createCategory">{{ __('Add Category') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['modal_success', 'modal_error', 'message', 'status', 'error']" :types="['modal_success' => 'success', 'modal_error' => 'error']" />

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Categories')" :value="\App\Support\Format::number($stats['categories'] ?? 0, 0)" icon="fa-folder-tree" color="green" />
        <x-stat-card :label="__('Hardware Prices')" :value="\App\Support\Format::number($stats['hardware_prices'] ?? 0, 0)" icon="fa-store" color="blue" :href="route('hardware-prices.index', ['priceType' => 'hardware'])" />
        <x-stat-card :label="__('Factory Prices')" :value="\App\Support\Format::number($stats['factory_prices'] ?? 0, 0)" icon="fa-industry" color="purple" :href="route('hardware-prices.index', ['priceType' => 'factory'])" />
        <x-stat-card :label="__('Active Prices')" :value="\App\Support\Format::number($stats['active_prices'] ?? 0, 0)" icon="fa-circle-check" color="amber" :href="route('hardware-prices.index')" />
    </div>

    <x-ui.card
        :title="__('Automatic Price Scanner')"
        icon="fa-clock-rotate-left"
        :subtitle="__('Choose when the server automatically refreshes market prices. Manual scanning remains available when automatic scanning is disabled.')"
    >
        <form wire:submit.prevent="saveAutoScanSettings">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field :label="__('Automatic Scanning')" for="auto-scan-enabled" error="autoScanForm.enabled">
                    <label class="boq-check min-h-[42px]">
                        <input id="auto-scan-enabled" type="checkbox" wire:model="autoScanForm.enabled">
                        {{ __('Enable automatic price scanning') }}
                    </label>
                </x-ui.field>

                <x-ui.field :label="__('Scan Time')" for="auto-scan-time" error="autoScanForm.time">
                    <input id="auto-scan-time" type="time" wire:model="autoScanForm.time"
                           class="boq-field @error('autoScanForm.time') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Items per Category')" for="auto-scan-limit" error="autoScanForm.limit">
                    <input id="auto-scan-limit" type="number" min="1" max="20"
                           wire:model="autoScanForm.limit"
                           class="boq-field @error('autoScanForm.limit') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Timezone')">
                    <div class="boq-field flex items-center gap-2">
                        <i class="fas fa-globe-africa" aria-hidden="true"></i>
                        <span>{{ $autoScanStatus['timezone'] ?? config('app.timezone') }}</span>
                    </div>
                </x-ui.field>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Last Run') }}</div>
                    <div class="mt-1 font-semibold text-slate-900">
                        {{ filled($autoScanStatus['last_run_at'] ?? null) ? $autoScanStatus['last_run_at'] : __('Never') }}
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Last Status') }}</div>
                    <div class="mt-1 font-semibold text-slate-900">
                        {{ __(str_replace('_', ' ', ucfirst((string) ($autoScanStatus['last_status'] ?? 'never_run')))) }}
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Last Summary') }}</div>
                    <div class="mt-1 text-sm text-slate-700">
                        {{ ($autoScanStatus['last_summary'] ?? '') ?: __('No automatic scan has completed yet.') }}
                    </div>
                </div>
            </div>

            <div class="hardware-card-actions">
                <button type="submit" class="boq-btn-primary" wire:loading.attr="disabled" wire:target="saveAutoScanSettings">
                    <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                    <span wire:loading.remove wire:target="saveAutoScanSettings">{{ __('Save Scanner Schedule') }}</span>
                    <span wire:loading wire:target="saveAutoScanSettings">{{ __('Saving...') }}</span>
                </button>
            </div>
        </form>
    </x-ui.card>
    <x-ui.card :title="__('AI Price Scanner')" icon="fa-robot" :subtitle="__('Select whether the AI should research hardware supplier prices or direct factory/manufacturer prices.')">
        <form wire:submit.prevent="scanPrices">
            <div class="mb-4">
                <span class="boq-field-label">{{ __('Scan by') }}</span>
                <x-ui.tabs :label="__('Scan by')">
                    <x-ui.tab wire:click="$set('scanForm.scope', 'location')" icon="fa-location-dot" :active="($scanForm['scope'] ?? 'location') === 'location'">{{ __('Location') }}</x-ui.tab>
                    <x-ui.tab wire:click="$set('scanForm.scope', 'region')" icon="fa-map" :active="($scanForm['scope'] ?? '') === 'region'">{{ __('Region') }}</x-ui.tab>
                </x-ui.tabs>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field :label="__('Price Type')" for="scan-price-type" error="scanForm.price_type">
                    <select id="scan-price-type" wire:model="scanForm.price_type" class="boq-field">
                        <option value="hardware">{{ __('Hardware Prices') }}</option>
                        <option value="factory">{{ __('Factory Prices') }}</option>
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Category')" for="scan-category" error="scanForm.category" required>
                    <select id="scan-category" wire:model="scanForm.category" class="boq-field @error('scanForm.category') has-error @enderror">
                        <option value="">{{ __('Select category...') }}</option>
                        @foreach($activeCategories as $category)
                            <option value="{{ $category->name }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                @if(($scanForm['scope'] ?? 'location') === 'region')
                    <x-ui.field :label="__('Region')" for="scan-region" error="scanForm.region" required>
                        <input id="scan-region" list="scan-region-options" wire:model.live.debounce.400ms="scanForm.region" class="boq-field @error('scanForm.region') has-error @enderror" placeholder="{{ __('e.g. Central, Northern...') }}" autocomplete="off">
                        <datalist id="scan-region-options">@foreach($regions as $regionOption)<option value="{{ $regionOption }}"></option>@endforeach</datalist>
                    </x-ui.field>
                @else
                    <x-ui.field :label="__('Location')" for="scan-location" error="scanForm.location">
                        <input id="scan-location" wire:model="scanForm.location" class="boq-field @error('scanForm.location') has-error @enderror" placeholder="{{ __('e.g. city, town or market') }}">
                    </x-ui.field>
                @endif

                <x-ui.field :label="__('Items to Scan')" for="scan-limit" error="scanForm.limit">
                    <input id="scan-limit" placeholder="10" type="number" min="1" max="50" wire:model="scanForm.limit" class="boq-field @error('scanForm.limit') has-error @enderror">
                </x-ui.field>
            </div>

            @if(($scanForm['scope'] ?? 'location') === 'region')
                <div class="mt-4 space-y-3 rounded-lg border border-slate-200 p-4">
                    @if(blank($scanForm['region'] ?? null))
                        <p class="text-sm text-slate-500">{{ __('Choose a region. The scan collects prices from different hardware shops and factories across its towns, so you can compare them.') }}</p>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="boq-field-label !mb-0">{{ __('Towns to cover') }}</span>
                            @if($regionTowns !== [])
                                <span class="flex gap-2">
                                    <button type="button" wire:click="selectAllTowns" class="boq-btn-ghost boq-btn-sm">{{ __('Select all') }}</button>
                                    <button type="button" wire:click="clearTowns" class="boq-btn-ghost boq-btn-sm">{{ __('Clear') }}</button>
                                </span>
                            @endif
                        </div>
                        @if($regionTowns === [])
                            <p class="text-sm text-slate-500">{{ __('No towns are recorded for this region yet, so the main towns of the region will be scanned. Add suppliers with this region to choose towns.') }}</p>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach($regionTowns as $town)
                                    <label class="boq-check rounded-full border border-slate-200 px-3 py-1" wire:key="town-{{ md5($town) }}">
                                        <input type="checkbox" value="{{ $town }}" wire:model.live="scanForm.towns">
                                        {{ $town }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-slate-500">{{ count($scanForm['towns'] ?? []) ? trans_choice(':count town selected.|:count towns selected.', count($scanForm['towns']), ['count' => count($scanForm['towns'])]) : __('None selected: all towns of the region are covered.') }}</p>
                        @endif
                        @error('scanForm.towns') <p class="boq-field-error">{{ $message }}</p> @enderror

                        <label class="boq-check">
                            <input type="checkbox" wire:model="scanForm.scan_websites" @disabled($regionWebsites === 0)>
                            {{ trans_choice('Also scan the website of the :count :type in this region|Also scan the websites of the :count :types in this region', $regionWebsites, ['count' => $regionWebsites, 'type' => ($scanForm['price_type'] ?? '') === 'factory' ? __('factory') : __('supplier'), 'types' => ($scanForm['price_type'] ?? '') === 'factory' ? __('factories') : __('suppliers')]) }}
                        </label>
                    @endif
                </div>
            @endif

            <div class="hardware-card-actions">
                <button type="submit" wire:loading.attr="disabled" wire:target="scanPrices" class="boq-btn-primary">
                    <i wire:loading.remove wire:target="scanPrices" class="fas fa-magnifying-glass-dollar" aria-hidden="true"></i>
                    <i wire:loading wire:target="scanPrices" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <span wire:loading.remove wire:target="scanPrices">{{ __('Scan Prices') }}</span>
                    <span wire:loading wire:target="scanPrices">{{ __('Scanning...') }}</span>
                </button>
            </div>
        </form>

        @if($scanResults)
            <div class="hardware-result-block">
                <h3>{{ __('Scan Results') }} <x-ui.badge>{{ count($scanResults) }}</x-ui.badge></h3>

                <div class="boq-table-wrapper rounded-lg border border-slate-200">
                    <table class="boq-table">
                        <thead>
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Supplier / Factory') }}</th>
                                <th>{{ __('Location') }}</th>
                                <th class="text-right">{{ __('Price') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($scanResults as $index => $result)
                                @php $isFactory = ($result['price_type'] ?? null) === 'factory'; @endphp
                                <tr wire:key="scan-result-{{ $index }}">
                                    <td class="font-semibold text-slate-900">{{ $result['item'] ?? 'â€”' }}</td>
                                    <td>
                                        <x-ui.badge :color="$isFactory ? 'purple' : 'info'" :icon="$isFactory ? 'fa-industry' : 'fa-store'">
                                            {{ $isFactory ? __('Factory') : __('Hardware') }}
                                        </x-ui.badge>
                                    </td>
                                    <td>{{ ($result['supplier'] ?? null) ?: 'â€”' }}</td>
                                    <td>{{ ($result['location'] ?? null) ?: 'â€”' }}</td>
                                    <td class="is-numeric font-semibold"><x-money :amount="$result['price'] ?? 0" :currency="$result['currency'] ?? null" /></td>
                                    <td><x-ui.badge :color="($result['status'] ?? '') === 'created' ? 'success' : 'info'">{{ __(ucfirst((string) ($result['status'] ?? ''))) }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Price Categories')" icon="fa-folder-tree" :subtitle="__('Categories are shared by Hardware Prices and Factory Prices.')" :padded="false">
        <x-slot:actions>
            <x-ui.button size="sm" icon="fa-plus" wire:click="createCategory">{{ __('Add Category') }}</x-ui.button>
        </x-slot:actions>

        <x-ui.table>
            <thead>
                <tr>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Default Items') }}</th>
                    <th>{{ __('Prices') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Order') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr wire:key="hardware-category-{{ $category->id }}">
                        <td>
                            <div class="boq-table-title">{{ $category->name }}</div>
                            <div class="boq-table-subtitle">{{ $category->description ?: __('No description') }}</div>
                        </td>
                        <td>{{ $category->items_count ?? count($category->default_items ?? []) }}</td>
                        <td>{{ \App\Support\Format::number($category->hardware_prices_count ?? $category->hardwarePrices()->count(), 0) }}</td>
                        <td><x-ui.status :status="$category->is_active ? 'active' : 'inactive'" /></td>
                        <td>{{ $category->sort_order }}</td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button type="button" wire:click="editCategory({{ $category->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen" aria-hidden="true"></i></button>
                                <button type="button" wire:click="toggleCategory({{ $category->id }})" class="boq-icon-btn" title="{{ $category->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $category->is_active ? __('Deactivate') : __('Activate') }}">
                                    <i class="fas {{ $category->is_active ? 'fa-toggle-on text-brand-600' : 'fa-toggle-off' }}" aria-hidden="true"></i>
                                </button>
                                <button type="button" wire:click="confirmDeleteCategory({{ $category->id }})" class="boq-icon-btn boq-icon-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-0"><x-ui.empty-state icon="fa-folder-open" :title="__('No price categories configured.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    @if($showCategoryModal)
        <x-ui.modal
            wire:key="hardware-category-modal"
            id="hardware-category"
            :title="$editingCategoryId ? __('Edit Price Category') : __('Add Price Category')"
            :subtitle="__('Default items are used by both hardware and factory price scans.')"
            icon="fa-folder-tree"
            size="lg"
            close="cancelCategory"
            submit="saveCategory"
        >
            <div class="boq-form-grid">
                <x-ui.field :label="__('Category Name')" for="category-name" error="categoryForm.name" required>
                    <input id="category-name" wire:model="categoryForm.name" class="boq-field @error('categoryForm.name') has-error @enderror" placeholder="{{ __('e.g. Doors & Windows') }}">
                </x-ui.field>

                <x-ui.field :label="__('Sort Order')" for="category-order" error="categoryForm.sort_order">
                    <input id="category-order" placeholder="10" type="number" min="0" wire:model="categoryForm.sort_order" class="boq-field">
                </x-ui.field>

                <x-ui.field :label="__('Description')" for="category-description" error="categoryForm.description" class="boq-form-span-2">
                    <textarea id="category-description" wire:model="categoryForm.description" class="boq-field" placeholder="{{ __('Short description') }}"></textarea>
                </x-ui.field>

                <x-ui.field :label="__('Default Items')" for="category-items" error="categoryForm.default_items" :hint="__('Comma separated.')" class="boq-form-span-2">
                    <textarea id="category-items" wire:model="categoryForm.default_items" class="boq-field" placeholder="{{ __('Door frames, Timber doors, Aluminium windows') }}"></textarea>
                </x-ui.field>

                <label class="boq-check boq-form-span-2">
                    <input type="checkbox" wire:model="categoryForm.is_active">
                    {{ __('Active category') }}
                </label>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancelCategory">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="saveCategory">{{ __('Save Category') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if($showDeleteCategoryModal)
        <x-ui.modal wire:key="hardware-category-delete-modal" id="hardware-category-delete" :title="__('Delete price category?')" icon="fa-trash" size="sm" close="$set('showDeleteCategoryModal', false)">
            <p class="boq-modal-message">{{ __('This category can only be deleted when no hardware or factory prices use it. Otherwise deactivate it.') }}</p>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('showDeleteCategoryModal', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" icon="fa-trash" wire:click="deleteCategory" loading="deleteCategory">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
