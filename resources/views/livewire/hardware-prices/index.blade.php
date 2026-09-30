@php
    $hasFilters = $search !== '' || $priceType !== '' || filled($category) || filled($supplier) || filled($location) || filled($region) || filled($brand) || $updated !== '' || $sort !== 'latest';
@endphp

<div class="boq-page-stack">

    <x-ui.page-header
        :title="__('Market Prices')"
        icon="fa-tags"
        :subtitle="__('Track hardware supplier prices and manufacturer/factory prices across all construction categories.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-scale-balanced" :href="route('hardware-prices.compare')">{{ __('Compare') }}</x-ui.button>
            <x-ui.button variant="secondary" icon="fa-lightbulb" :href="route('hardware-prices.recommendations')">{{ __('Recommendations') }}</x-ui.button>
            @if($canManage)
                <x-ui.button icon="fa-plus" wire:click="createPrice">{{ __('Add Price') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['hardware-price-message', 'status', 'message']" />

    <div class="boq-stats-grid">
        <x-stat-card
            :label="__('Total Prices')"
            :value="\App\Support\Format::number($stats['total'] ?? 0, 0)"
            icon="fa-tags"
            color="green"
            :active="$priceType === ''"
            wire:click="$set('priceType', '')"
        />
        <x-stat-card
            :label="__('Categories')"
            :value="\App\Support\Format::number($stats['categories'] ?? 0, 0)"
            icon="fa-layer-group"
            color="blue"
        />
        <x-stat-card
            :label="__('Hardware Prices')"
            :value="\App\Support\Format::number($stats['hardware'] ?? 0, 0)"
            icon="fa-store"
            color="amber"
            :active="$priceType === 'hardware'"
            wire:click="$set('priceType', 'hardware')"
        />
        <x-stat-card
            :label="__('Factory Prices')"
            :value="\App\Support\Format::number($stats['factory'] ?? 0, 0)"
            icon="fa-industry"
            color="purple"
            :active="$priceType === 'factory'"
            wire:click="$set('priceType', 'factory')"
        />
    </div>

    <div class="boq-panel">
        <div class="boq-card-body">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field :label="__('Search')" for="price-search" class="sm:col-span-2">
                    <div class="boq-input-icon-wrap">
                        <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                        <input
                            id="price-search"
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            class="boq-field boq-field-with-icon"
                            placeholder="{{ __('Search item, brand, specification or supplier...') }}"
                        >
                    </div>
                </x-ui.field>

                <x-ui.field :label="__('Price Type')" for="price-type">
                    <select id="price-type" wire:model.live="priceType" class="boq-field">
                        <option value="">{{ __('All Price Types') }}</option>
                        <option value="hardware">{{ __('Hardware Price') }}</option>
                        <option value="factory">{{ __('Factory Price') }}</option>
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Category')" for="price-category">
                    <select id="price-category" wire:model.live="category" class="boq-field">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Supplier / Factory')" for="price-supplier">
                    <select id="price-supplier" wire:model.live="supplier" class="boq-field">
                        <option value="">{{ __('All Sources') }}</option>
                        @foreach($suppliers as $supplierOption)
                            <option value="{{ $supplierOption }}">{{ $supplierOption }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Region')" for="price-region">
                    <select id="price-region" wire:model.live="region" class="boq-field">
                        <option value="">{{ __('All Regions') }}</option>
                        @foreach($regions as $regionOption)
                            <option value="{{ $regionOption }}">{{ $regionOption }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Location')" for="price-location">
                    <input id="price-location" type="search" list="price-location-options" wire:model.live.debounce.400ms="location" class="boq-field" placeholder="{{ __('Search location or region...') }}" autocomplete="off">
                    <datalist id="price-location-options">
                        @foreach($locations as $locationOption)
                            <option value="{{ $locationOption }}"></option>
                        @endforeach
                    </datalist>
                </x-ui.field>

                <x-ui.field :label="__('Brand')" for="price-brand">
                    <select id="price-brand" wire:model.live="brand" class="boq-field">
                        <option value="">{{ __('All Brands') }}</option>
                        @foreach($brands as $brandOption)
                            <option value="{{ $brandOption }}">{{ $brandOption }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Updated')" for="price-updated">
                    <select id="price-updated" wire:model.live="updated" class="boq-field">
                        <option value="">{{ __('Any time') }}</option>
                        <option value="today">{{ __('Today') }}</option>
                        <option value="week">{{ __('This week') }}</option>
                    </select>
                </x-ui.field>
            </div>

            <div class="mt-3 flex flex-wrap items-end justify-between gap-3 border-t border-slate-100 pt-3">
                <div class="flex flex-wrap items-center gap-3">
                    @if($canManage)
                        <div class="boq-segmented" role="radiogroup" aria-label="{{ __('Status') }}">
                            @foreach(['active' => __('Active'), 'inactive' => __('Inactive'), 'all' => __('All')] as $value => $label)
                                <label class="{{ $status === $value ? 'is-active' : '' }} cursor-pointer">
                                    <input type="radio" wire:model.live="status" value="{{ $value }}" class="sr-only">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    @endif

                    @if($hasFilters)
                        <button type="button" wire:click="clearFilters" class="boq-btn-ghost boq-btn-sm">
                            <i class="fas fa-filter-circle-xmark" aria-hidden="true"></i> {{ __('Clear filters') }}
                        </button>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <label for="price-sort" class="text-sm font-semibold text-slate-600">{{ __('Sort By') }}</label>
                    <select id="price-sort" wire:model.live="sort" class="boq-field w-auto">
                        <option value="latest">{{ __('Latest update') }}</option>
                        <option value="price_asc">{{ __('Lowest price first') }}</option>
                        <option value="price_desc">{{ __('Highest price first') }}</option>
                        <option value="name">{{ __('Item name') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="boq-panel">
        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkBookmark" class="boq-btn-secondary"><i class="fas fa-bookmark" aria-hidden="true"></i> {{ __('Bookmark') }}</button>
            @if($canManage)
                <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('Activate') }}</button>
                <button type="button" wire:click="bulkSetActive(false)" wire:confirm="{{ __('Deactivate the selected prices?') }}" class="boq-btn-secondary"><i class="fas fa-ban" aria-hidden="true"></i> {{ __('Deactivate') }}</button>
                <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected prices? Prices linked to BOQ items are skipped.') }}" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
            @endif
        </x-bulk-bar>

        <div class="boq-loading-bar" wire:loading.delay wire:target="search, priceType, category, supplier, location, brand, updated, sort, status, clearFilters, gotoPage, nextPage, previousPage"></div>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$prices->pluck('id')" :selected="$selected" /></th>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('Price Type') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th class="text-right">{{ __('Price') }}</th>
                    <th>{{ __('Supplier / Factory') }}</th>
                    <th>{{ __('Location') }}</th>
                    <th>{{ __('Updated') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse($prices as $price)
                    @php
                        $isFactory = $price->price_type === 'factory';
                        $isBookmarked = in_array($price->id, $bookmarkedIds);
                        $extreme = $extremes[mb_strtolower($price->item_name.'|'.$price->unit.'|'.$price->currency)] ?? null;
                    @endphp

                    <tr wire:key="price-{{ $price->id }}" @class(['opacity-60' => ! $price->is_active])>
                        <td class="boq-check-col"><x-select-row :id="$price->id" /></td>

                        <td>
                            <a href="{{ route('hardware-prices.show', $price->id) }}" class="boq-table-link">{{ $price->item_name }}</a>
                            @if($price->brand)
                                <div class="boq-table-subtitle">{{ $price->brand }}</div>
                            @endif
                            @if($price->specification)
                                <div class="boq-table-meta">{{ \Illuminate\Support\Str::limit($price->specification, 80) }}</div>
                            @endif
                            @if(! $price->is_active)
                                <x-ui.badge class="mt-1">{{ __('Inactive') }}</x-ui.badge>
                            @endif
                        </td>

                        <td>
                            <x-ui.badge :color="$isFactory ? 'purple' : 'info'" :icon="$isFactory ? 'fa-industry' : 'fa-store'">
                                {{ $isFactory ? __('Factory') : __('Hardware') }}
                            </x-ui.badge>
                        </td>

                        <td><x-ui.badge>{{ $price->hardwareCategory?->name ?? $price->category ?? '—' }}</x-ui.badge></td>

                        <td class="is-numeric">
                            <span class="font-semibold text-slate-900"><x-money :amount="$price->price" :currency="$price->currency" /></span>
                            @if($price->unit)
                                <div class="boq-table-meta">{{ __('per :unit', ['unit' => $price->unit]) }}</div>
                            @endif

                            @if($extreme && $extreme->offers > 1)
                                @if((float) $price->price <= (float) $extreme->min_price)
                                    <div class="mt-1"><span class="boq-badge boq-badge-success" title="{{ __('Lowest available price for this item') }}"><i class="fas fa-arrow-down" aria-hidden="true"></i> {{ __('Lowest') }}</span></div>
                                @elseif((float) $price->price >= (float) $extreme->max_price)
                                    <div class="mt-1"><span class="boq-badge boq-badge-danger" title="{{ __('Highest available price for this item') }}"><i class="fas fa-arrow-up" aria-hidden="true"></i> {{ __('Highest') }}</span></div>
                                @endif
                            @endif
                        </td>

                        <td>
                            {{ $price->supplier ?: '—' }}
                            @if($price->source_url)
                                <a href="{{ $price->source_url }}" target="_blank" rel="noopener noreferrer" class="ml-1 text-slate-400 hover:text-brand-700" title="{{ __('View source') }}" aria-label="{{ __('View source') }}"><i class="fas fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i></a>
                            @endif
                        </td>

                        <td>
                            @if($price->location)
                                <span class="boq-cell-with-icon"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $price->location }}</span>
                                @if($price->region && strcasecmp($price->region, $price->location) !== 0)
                                    <div class="boq-table-subtitle">{{ $price->region }}</div>
                                @endif
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap">
                            <x-date :value="$price->last_verified_at ?? $price->fetched_at" />
                            @if($price->last_verified_at)
                                <div class="text-xs text-emerald-600"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('verified') }}</div>
                            @endif
                        </td>

                        <td class="text-right">
                            <div class="boq-table-actions">
                                <a href="{{ route('hardware-prices.show', $price->id) }}" class="boq-icon-btn" title="{{ __('View') }}" aria-label="{{ __('View') }}">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </a>

                                <button
                                    type="button"
                                    wire:click="toggleBookmark({{ $price->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleBookmark({{ $price->id }})"
                                    class="boq-icon-btn {{ $isBookmarked ? 'is-bookmarked' : '' }}"
                                    title="{{ $isBookmarked ? __('Remove bookmark') : __('Bookmark') }}"
                                    aria-label="{{ $isBookmarked ? __('Remove bookmark') : __('Bookmark') }}"
                                    aria-pressed="{{ $isBookmarked ? 'true' : 'false' }}"
                                >
                                    <i class="{{ $isBookmarked ? 'fas' : 'far' }} fa-bookmark" aria-hidden="true"></i>
                                </button>

                                @if($canManage)
                                    <button type="button" wire:click="editPrice({{ $price->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="toggleActive({{ $price->id }})"
                                        class="boq-icon-btn {{ $price->is_active ? 'boq-icon-danger' : 'boq-icon-success' }}"
                                        title="{{ $price->is_active ? __('Deactivate') : __('Activate') }}"
                                        aria-label="{{ $price->is_active ? __('Deactivate') : __('Activate') }}"
                                    >
                                        <i class="fas {{ $price->is_active ? 'fa-ban' : 'fa-circle-check' }}" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-0">
                            <x-ui.empty-state
                                icon="fa-box-open"
                                :title="__('No prices found.')"
                                :description="$hasFilters ? __('No prices match these filters.') : __('Prices appear here once they are added or collected by the price scanner.')"
                            >
                                @if($hasFilters)
                                    <x-ui.button variant="secondary" size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
                                @elseif($canManage)
                                    <x-ui.button size="sm" icon="fa-plus" wire:click="createPrice">{{ __('Add Price') }}</x-ui.button>
                                @endif
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($prices->hasPages())
            <div class="boq-pagination">
                {{ $prices->links() }}
            </div>
        @endif
    </div>

    @if($showForm)
        <x-ui.modal
            wire:key="market-price-modal"
            id="market-price"
            :title="$editingId ? __('Edit Price') : __('Add Price')"
            :subtitle="__('Record either a hardware supplier price or a factory/manufacturer price.')"
            :icon="$editingId ? 'fa-pen' : 'fa-plus'"
            size="lg"
            close="cancelForm"
            submit="save"
        >
            <div class="boq-form-grid">
                <x-ui.field :label="__('Price Type')" for="form-price-type" error="form.price_type" required>
                    <select id="form-price-type" wire:model="form.price_type" class="boq-field @error('form.price_type') has-error @enderror">
                        <option value="hardware">{{ __('Hardware Price') }}</option>
                        <option value="factory">{{ __('Factory Price') }}</option>
                    </select>
                </x-ui.field>

                <x-select-with-other
                    :label="__('Category')"
                    choice="categoryChoice"
                    value="form.category"
                    :current="$categoryChoice"
                    :options="$categoryOptions"
                    :placeholder="__('Select category...')"
                    :other-placeholder="__('e.g. Cement, Steel, Roofing')"
                    error="form.category"
                    required
                />

                <x-select-with-other
                    class="boq-form-span-2"
                    :label="__('Item Name')"
                    choice="itemChoice"
                    value="form.item_name"
                    :current="$itemChoice"
                    :options="$itemOptions"
                    :placeholder="$categoryChoice === '' ? __('Select a category first...') : __('Select item...')"
                    :other-placeholder="__('e.g. Portland Cement 50kg')"
                    error="form.item_name"
                    required
                />

                <x-ui.field :label="__('Brand / Manufacturer')" for="form-brand" error="form.brand">
                    <input id="form-brand" wire:model="form.brand" class="boq-field @error('form.brand') has-error @enderror" placeholder="{{ __('e.g. Hima Cement') }}">
                </x-ui.field>

                <x-ui.field :label="__('Unit')" for="form-unit" error="form.unit" required>
                    <input id="form-unit" wire:model="form.unit" class="boq-field @error('form.unit') has-error @enderror" placeholder="{{ __('bag, kg, tonne, metre...') }}">
                </x-ui.field>

                <x-ui.field :label="__('Price')" for="form-price" error="form.price" required>
                    <input id="form-price" type="number" step="0.01" min="0" inputmode="decimal" wire:model="form.price" class="boq-field @error('form.price') has-error @enderror" placeholder="0">
                </x-ui.field>

                <x-ui.field :label="__('Currency')" for="form-currency" error="form.currency" required>
                    <x-currency-select id="form-currency" wire:model="form.currency" :current="$form['currency'] ?? null" />
                </x-ui.field>

                <x-ui.field :label="__('Supplier / Factory')" for="form-supplier" error="form.supplier" required>
                    <input id="form-supplier" wire:model="form.supplier" class="boq-field @error('form.supplier') has-error @enderror" placeholder="{{ __('Supplier or manufacturer name') }}">
                </x-ui.field>

                <x-ui.field :label="__('Location')" for="form-location" error="form.location">
                    <input id="form-location" wire:model="form.location" class="boq-field @error('form.location') has-error @enderror" placeholder="{{ __('e.g. city, town or market') }}">
                </x-ui.field>

                <x-ui.field :label="__('Region')" for="form-region" error="form.region" :hint="__('Leave empty to take the region from the supplier or the location.')">
                    <input id="form-region" wire:model="form.region" list="price-region-options" class="boq-field @error('form.region') has-error @enderror" placeholder="{{ __('e.g. Central region') }}">
                    <datalist id="price-region-options">@foreach($regions as $regionOption)<option value="{{ $regionOption }}"></option>@endforeach</datalist>
                </x-ui.field>

                <x-ui.field :label="__('Specification')" for="form-specification" error="form.specification" class="boq-form-span-2">
                    <textarea id="form-specification" wire:model="form.specification" class="boq-field @error('form.specification') has-error @enderror" placeholder="{{ __('Size, grade, thickness, standard, packaging...') }}"></textarea>
                </x-ui.field>

                <x-ui.field :label="__('Source URL')" for="form-source-url" error="form.source_url">
                    <input id="form-source-url" type="url" wire:model="form.source_url" class="boq-field @error('form.source_url') has-error @enderror" placeholder="https://...">
                </x-ui.field>

                <x-ui.field :label="__('Source Reference')" for="form-source-reference" error="form.source_reference">
                    <input id="form-source-reference" wire:model="form.source_reference" class="boq-field @error('form.source_reference') has-error @enderror" placeholder="{{ __('Price list, quotation, market survey...') }}">
                </x-ui.field>

                <x-ui.field :label="__('Price Date')" for="form-fetched-at" error="form.fetched_at" required>
                    <input id="form-fetched-at" type="datetime-local" wire:model="form.fetched_at" class="boq-field @error('form.fetched_at') has-error @enderror">
                </x-ui.field>

                <div class="flex items-end pb-2">
                    <label class="boq-check">
                        <input type="checkbox" wire:model="form.is_active">
                        {{ __('Active price') }}
                    </label>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancelForm">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ __('Save Price') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
