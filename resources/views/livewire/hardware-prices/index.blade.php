<div class="boq-page-stack">

    <div class="boq-page-header">

        <div>

            <h1 class="boq-page-title">
                <i class="fas fa-tags"></i>
                {{ __('Market Prices') }}
            </h1>

            <p class="boq-page-subtitle">
                {{ __('Track hardware supplier prices and manufacturer/factory prices across all construction categories.') }}
            </p>

        </div>

        <div class="flex flex-wrap gap-2">

            <a
                href="{{ url('/hardware-prices/compare') }}"
                class="boq-btn-secondary"
            >
                <i class="fas fa-scale-balanced"></i>
                {{ __('Compare') }}
            </a>

            <a
                href="{{ url('/hardware-prices/recommendations') }}"
                class="boq-btn-secondary"
            >
                <i class="fas fa-lightbulb"></i>
                {{ __('Recommendations') }}
            </a>

            @if($canManage)

                <button
                    type="button"
                    wire:click="createPrice"
                    class="boq-btn-primary"
                >
                    <i class="fas fa-plus"></i>
                    {{ __('Add Price') }}
                </button>

            @endif

        </div>

    </div>

    @if(
        session(
            'hardware-price-message'
        )
    )

        <div
            class="boq-flash"
            x-data="{ visible: true }"
            x-show="visible"
            x-init="
                setTimeout(
                    () => visible = false,
                    5000
                )
            "
        >
            <i class="fas fa-circle-check"></i>

            {{
                session(
                    'hardware-price-message'
                )
            }}
        </div>

    @endif

    <div class="boq-stats-grid">

        <div class="boq-stat-card boq-stat-green">
            <div>
                <p class="boq-stat-label">
                    {{ __('Total Prices') }}
                </p>

                <p class="boq-stat-value">
                    {{ $stats['total'] }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-tags"></i>
            </span>
        </div>

        <div class="boq-stat-card boq-stat-blue">
            <div>
                <p class="boq-stat-label">
                    {{ __('Categories') }}
                </p>

                <p class="boq-stat-value">
                    {{ $stats['categories'] }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-layer-group"></i>
            </span>
        </div>

        <div class="boq-stat-card boq-stat-amber">
            <div>
                <p class="boq-stat-label">
                    {{ __('Hardware Prices') }}
                </p>

                <p class="boq-stat-value">
                    {{ $stats['hardware'] }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-store"></i>
            </span>
        </div>

        <div class="boq-stat-card boq-stat-purple">
            <div>
                <p class="boq-stat-label">
                    {{ __('Factory Prices') }}
                </p>

                <p class="boq-stat-value">
                    {{ $stats['factory'] }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-industry"></i>
            </span>
        </div>

    </div>

    <div class="boq-panel p-4">

        <div class="hardware-price-filters">

            <div>

                <label class="boq-field-label">
                    {{ __('Search') }}
                </label>

                <div class="boq-input-icon-wrap">

                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="{{ __('Search item, brand, specification or supplier...') }}"
                    >

                </div>

            </div>

            <div>

                <label class="boq-field-label">
                    {{ __('Price Type') }}
                </label>

                <select
                    wire:model.live="priceType"
                    class="boq-field"
                >
                    <option value="">
                        {{ __('All Price Types') }}
                    </option>

                    <option value="hardware">
                        {{ __('Hardware Price') }}
                    </option>

                    <option value="factory">
                        {{ __('Factory Price') }}
                    </option>
                </select>

            </div>

            <div>

                <label class="boq-field-label">
                    {{ __('Category') }}
                </label>

                <select
                    wire:model.live="category"
                    class="boq-field"
                >
                    <option value="">
                        {{ __('All Categories') }}
                    </option>

                    @foreach(
                        $categories
                        as $categoryOption
                    )

                        <option
                            value="{{ $categoryOption }}"
                        >
                            {{ $categoryOption }}
                        </option>

                    @endforeach
                </select>

            </div>

            <div>

                <label class="boq-field-label">
                    {{ __('Supplier / Factory') }}
                </label>

                <select
                    wire:model.live="supplier"
                    class="boq-field"
                >
                    <option value="">
                        {{ __('All Sources') }}
                    </option>

                    @foreach(
                        $suppliers
                        as $supplierOption
                    )

                        <option
                            value="{{ $supplierOption }}"
                        >
                            {{ $supplierOption }}
                        </option>

                    @endforeach
                </select>

            </div>

            <div>

                <label class="boq-field-label">
                    {{ __('Location') }}
                </label>

                <select
                    wire:model.live="location"
                    class="boq-field"
                >
                    <option value="">
                        {{ __('All Locations') }}
                    </option>

                    @foreach(
                        $locations
                        as $locationOption
                    )

                        <option
                            value="{{ $locationOption }}"
                        >
                            {{ $locationOption }}
                        </option>

                    @endforeach
                </select>

            </div>

        </div>

        @if($canManage)

            <div
                class="mt-3 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-3"
            >

                @foreach([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'all' => 'All',
                ] as $value => $label)

                    <label
                        class="inline-flex items-center gap-2 text-sm font-medium text-slate-600"
                    >
                        <input
                            type="radio"
                            wire:model.live="status"
                            value="{{ $value }}"
                        >

                        {{ $label }}
                    </label>

                @endforeach

            </div>

        @endif

    </div>

    <div class="boq-panel overflow-hidden">

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkBookmark" class="boq-btn-secondary"><i class="fas fa-bookmark"></i> {{ __('Bookmark') }}</button>
            @if($canManage)
                <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-circle-check"></i> {{ __('Activate') }}</button>
                <button type="button" wire:click="bulkSetActive(false)" wire:confirm="Deactivate the selected prices?" class="boq-btn-secondary"><i class="fas fa-ban"></i> {{ __('Deactivate') }}</button>
                <button type="button" wire:click="bulkDelete" wire:confirm="Delete the selected prices? Prices linked to BOQ items are skipped." class="boq-btn-danger"><i class="fas fa-trash"></i> {{ __('Delete') }}</button>
            @endif
        </x-bulk-bar>

        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>
                    <tr>
                        <th class="boq-check-col"><x-select-all :ids="$prices->pluck('id')" :selected="$selected" /></th>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Price Type') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th>{{ __('Price') }}</th>
                        <th>{{ __('Supplier / Factory') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Updated') }}</th>
                        <th class="text-right">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>

                <tbody>

                    @forelse(
                        $prices
                        as $price
                    )

                        <tr
                            wire:key="price-{{ $price->id }}"
                        >

                            <td class="boq-check-col"><x-select-row :id="$price->id" /></td>

                            <td>

                                <a
                                    href="{{ url('/hardware-prices/'.$price->id) }}"
                                    class="font-semibold text-[#05645b] "
                                >
                                    {{ $price->item_name }}
                                </a>

                                @if($price->brand)
                                    <div
                                        class="boq-table-subtitle"
                                    >
                                        {{ $price->brand }}
                                    </div>
                                @endif

                                @if($price->specification)
                                    <div
                                        class="boq-table-subtitle"
                                    >
                                        {{ $price->specification }}
                                    </div>
                                @endif

                            </td>

                            <td>

                                <span
                                    class="boq-badge {{
                                        $price->price_type === 'factory'
                                            ? 'boq-badge-factory'
                                            : 'boq-badge-info'
                                    }}"
                                >

                                    <i class="fas {{
                                        $price->price_type === 'factory'
                                            ? 'fa-industry'
                                            : 'fa-store'
                                    }}"></i>

                                    {{
                                        $price->price_type === 'factory'
                                            ? 'Factory'
                                            : 'Hardware'
                                    }}

                                </span>

                            </td>

                            <td>

                                <span class="boq-badge">
                                    {{
                                        $price
                                            ->hardwareCategory
                                            ?->name
                                        ?? $price->category
                                    }}
                                </span>

                            </td>

                            <td>
                                {{ $price->unit }}
                            </td>

                            <td
                                class="font-semibold text-slate-900"
                            >
                                {{ $price->currency }}

                                {{
                                    \App\Support\Format::number((float) $price->price, 0)
                                }}
                            </td>

                            <td>
                                {{ $price->supplier }}
                            </td>

                            <td>
                                {{ $price->location ?: '—' }}
                            </td>

                            <td>
                                {{
                                    $price
                                        ->fetched_at
                                        ?->format(
                                            'd M Y'
                                        )
                                    ?? '—'
                                }}
                            </td>

                            <td class="text-right">

                                <div
                                    class="boq-table-actions"
                                >

                                    <a
                                        href="{{ url('/hardware-prices/'.$price->id) }}"
                                        class="boq-icon-btn"
                                        title="{{ __('View') }}"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @php($isBookmarked = in_array($price->id, $bookmarkedIds))

                                    <button
                                        type="button"
                                        wire:click="toggleBookmark({{ $price->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleBookmark({{ $price->id }})"
                                        class="boq-icon-btn {{ $isBookmarked ? 'is-bookmarked' : '' }}"
                                        title="{{ $isBookmarked ? 'Remove bookmark' : 'Bookmark' }}"
                                        aria-label="{{ $isBookmarked ? 'Remove bookmark' : 'Bookmark' }}"
                                        aria-pressed="{{ $isBookmarked ? 'true' : 'false' }}"
                                    >
                                        <i class="{{ $isBookmarked ? 'fas' : 'far' }} fa-bookmark"></i>
                                    </button>

                                    @if($canManage)

                                        <button
                                            type="button"
                                            wire:click="editPrice({{ $price->id }})"
                                            class="boq-icon-btn"
                                            title="{{ __('Edit') }}"
                                        >
                                            <i class="fas fa-pen"></i>
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="toggleActive({{ $price->id }})"
                                            class="boq-icon-btn"
                                            title="{{
                                                $price->is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'
                                            }}"
                                        >
                                            <i class="fas {{
                                                $price->is_active
                                                    ? 'fa-ban'
                                                    : 'fa-circle-check'
                                            }}"></i>
                                        </button>

                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="10"
                                class="boq-empty-table"
                            >
                                <i class="fas fa-box-open"></i>

                                <span>
                                    {{ __('No prices found.') }}
                                </span>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if(
            $prices->hasPages()
        )

            <div class="boq-pagination">
                {{ $prices->links() }}
            </div>

        @endif

    </div>

    @if($showForm)

        <div
            class="boq-modal-backdrop"
            wire:key="market-price-modal"
        >

            <div
                class="boq-modal boq-modal-lg"
            >

                <div class="boq-modal-head">

                    <div>

                        <h2>
                            <i class="fas {{
                                $editingId
                                    ? 'fa-pen'
                                    : 'fa-plus'
                            }}"></i>

                            {{
                                $editingId
                                    ? 'Edit Price'
                                    : 'Add Price'
                            }}
                        </h2>

                        <p class="boq-table-subtitle">
                            {{ __('Record either a hardware supplier price or a factory/manufacturer price.') }}
                        </p>

                    </div>

                    <button
                        type="button"
                        wire:click="cancelForm"
                        class="boq-modal-close"
                    >
                        <i class="fas fa-xmark"></i>
                    </button>

                </div>

                <form
                    wire:submit.prevent="save"
                >

                    <div class="boq-modal-body">

                        <div class="boq-form-grid">

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Price Type *') }}
                                </label>

                                <select
                                    wire:model="form.price_type"
                                    class="boq-field"
                                >
                                    <option value="hardware">
                                        {{ __('Hardware Price') }}
                                    </option>

                                    <option value="factory">
                                        {{ __('Factory Price') }}
                                    </option>
                                </select>

                                @error('form.price_type')
                                    <p class="boq-field-error">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                            <x-select-with-other
                                label="Category"
                                choice="categoryChoice"
                                value="form.category"
                                :current="$categoryChoice"
                                :options="$categoryOptions"
                                placeholder="{{ __('Select category...') }}"
                                other-placeholder="e.g. Cement, Steel, Roofing"
                                error="form.category"
                                required
                            />

                            <x-select-with-other
                                class="boq-form-span-2"
                                label="Item Name"
                                choice="itemChoice"
                                value="form.item_name"
                                :current="$itemChoice"
                                :options="$itemOptions"
                                :placeholder="$categoryChoice === '' ? 'Select a category first...' : 'Select item...'"
                                other-placeholder="e.g. Portland Cement 50kg"
                                error="form.item_name"
                                required
                            />

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Brand / Manufacturer') }}
                                </label>

                                <input
                                    wire:model="form.brand"
                                    class="boq-field"
                                    placeholder="{{ __('e.g. Hima Cement') }}"
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Unit *') }}
                                </label>

                                <input
                                    wire:model="form.unit"
                                    class="boq-field"
                                    placeholder="{{ __('bag, kg, tonne, metre...') }}"
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Price *') }}
                                </label>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model="form.price"
                                    class="boq-field"
                                    placeholder="0"
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Currency *') }}
                                </label>

                                <x-currency-select wire:model="form.currency" :current="$form['currency'] ?? null" />

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Supplier / Factory *') }}
                                </label>

                                <input
                                    wire:model="form.supplier"
                                    class="boq-field"
                                    placeholder="{{ __('Supplier or manufacturer name') }}"
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Location') }}
                                </label>

                                <input
                                    wire:model="form.location"
                                    class="boq-field"
                                    placeholder="{{ __('e.g. city, town or market') }}"
                                >

                            </div>

                            <div class="boq-form-span-2">

                                <label class="boq-field-label">
                                    {{ __('Specification') }}
                                </label>

                                <textarea
                                    wire:model="form.specification"
                                    class="boq-field"
                                    placeholder="{{ __('Size, grade, thickness, standard, packaging...') }}"
                                ></textarea>

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Source URL') }}
                                </label>

                                <input
                                    type="url"
                                    wire:model="form.source_url"
                                    class="boq-field"
                                    placeholder="https://..."
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Source Reference') }}
                                </label>

                                <input
                                    wire:model="form.source_reference"
                                    class="boq-field"
                                    placeholder="{{ __('Price list, quotation, market survey...') }}"
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    {{ __('Price Date *') }}
                                </label>

                                <input
                                    type="datetime-local"
                                    wire:model="form.fetched_at"
                                    class="boq-field"
                                >

                            </div>

                            <div
                                class="flex items-end"
                            >
                                <label
                                    class="inline-flex items-center gap-2 text-sm font-medium text-slate-600"
                                >
                                    <input
                                        type="checkbox"
                                        wire:model="form.is_active"
                                    >

                                    {{ __('Active price') }}
                                </label>
                            </div>

                        </div>

                    </div>

                    <div class="boq-modal-foot">

                        <button
                            type="button"
                            wire:click="cancelForm"
                            class="boq-btn-secondary"
                        >
                            {{ __('Cancel') }}
                        </button>

                        <button
                            type="submit"
                            class="boq-btn-primary"
                        >
                            <i class="fas fa-save"></i>
                            {{ __('Save Price') }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>