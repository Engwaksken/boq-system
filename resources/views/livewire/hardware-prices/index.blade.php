<div class="boq-page-stack">

    <div class="boq-page-header">

        <div>

            <h1 class="boq-page-title">
                <i class="fas fa-tags"></i>
                Market Prices
            </h1>

            <p class="boq-page-subtitle">
                Track hardware supplier prices and manufacturer/factory prices across all construction categories.
            </p>

        </div>

        <div class="flex flex-wrap gap-2">

            <a
                href="{{ url('/hardware-prices/compare') }}"
                class="boq-btn-secondary"
            >
                <i class="fas fa-scale-balanced"></i>
                Compare
            </a>

            <a
                href="{{ url('/hardware-prices/recommendations') }}"
                class="boq-btn-secondary"
            >
                <i class="fas fa-lightbulb"></i>
                Recommendations
            </a>

            @if($canManage)

                <button
                    type="button"
                    wire:click="createPrice"
                    class="boq-btn-primary"
                >
                    <i class="fas fa-plus"></i>
                    Add Price
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
                    Total Prices
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
                    Active
                </p>

                <p class="boq-stat-value">
                    {{ $stats['active'] }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-circle-check"></i>
            </span>
        </div>

        <div class="boq-stat-card boq-stat-amber">
            <div>
                <p class="boq-stat-label">
                    Hardware Prices
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
                    Factory Prices
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
                    Search
                </label>

                <div class="boq-input-icon-wrap">

                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="Search item, brand, specification or supplier..."
                    >

                </div>

            </div>

            <div>

                <label class="boq-field-label">
                    Price Type
                </label>

                <select
                    wire:model.live="priceType"
                    class="boq-field"
                >
                    <option value="">
                        All Price Types
                    </option>

                    <option value="hardware">
                        Hardware Price
                    </option>

                    <option value="factory">
                        Factory Price
                    </option>
                </select>

            </div>

            <div>

                <label class="boq-field-label">
                    Category
                </label>

                <select
                    wire:model.live="category"
                    class="boq-field"
                >
                    <option value="">
                        All Categories
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
                    Supplier / Factory
                </label>

                <select
                    wire:model.live="supplier"
                    class="boq-field"
                >
                    <option value="">
                        All Sources
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
                    Location
                </label>

                <select
                    wire:model.live="location"
                    class="boq-field"
                >
                    <option value="">
                        All Locations
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

        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price Type</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Price</th>
                        <th>Supplier / Factory</th>
                        <th>Location</th>
                        <th>Updated</th>
                        <th class="text-right">
                            Actions
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

                            <td>

                                <a
                                    href="{{ url('/hardware-prices/'.$price->id) }}"
                                    class="font-semibold text-[#05645b] hover:underline"
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
                                    number_format(
                                        (float) $price->price,
                                        0
                                    )
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
                                        title="View"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @if($canManage)

                                        <button
                                            type="button"
                                            wire:click="editPrice({{ $price->id }})"
                                            class="boq-icon-btn"
                                            title="Edit"
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
                                colspan="9"
                                class="boq-empty-table"
                            >
                                <i class="fas fa-box-open"></i>

                                <span>
                                    No prices found.
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
                            Record either a hardware supplier price or a factory/manufacturer price.
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
                                    Price Type *
                                </label>

                                <select
                                    wire:model="form.price_type"
                                    class="boq-field"
                                >
                                    <option value="hardware">
                                        Hardware Price
                                    </option>

                                    <option value="factory">
                                        Factory Price
                                    </option>
                                </select>

                                @error('form.price_type')
                                    <p class="boq-field-error">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Category *
                                </label>

                                <input
                                    wire:model="form.category"
                                    class="boq-field"
                                    placeholder="e.g. Cement, Steel, Roofing"
                                >

                                @error('form.category')
                                    <p class="boq-field-error">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                            <div class="boq-form-span-2">

                                <label class="boq-field-label">
                                    Item Name *
                                </label>

                                <input
                                    wire:model="form.item_name"
                                    class="boq-field"
                                    placeholder="e.g. Portland Cement 50kg"
                                >

                                @error('form.item_name')
                                    <p class="boq-field-error">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Brand / Manufacturer
                                </label>

                                <input
                                    wire:model="form.brand"
                                    class="boq-field"
                                    placeholder="e.g. Hima Cement"
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Unit *
                                </label>

                                <input
                                    wire:model="form.unit"
                                    class="boq-field"
                                    placeholder="bag, kg, tonne, metre..."
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Price *
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
                                    Currency *
                                </label>

                                <select
                                    wire:model="form.currency"
                                    class="boq-field"
                                >
                                    @foreach([
                                        'UGX',
                                        'USD',
                                        'KES',
                                        'TZS',
                                        'RWF',
                                        'GBP',
                                        'EUR',
                                    ] as $currencyCode)

                                        <option
                                            value="{{ $currencyCode }}"
                                        >
                                            {{ $currencyCode }}
                                        </option>

                                    @endforeach
                                </select>

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Supplier / Factory *
                                </label>

                                <input
                                    wire:model="form.supplier"
                                    class="boq-field"
                                    placeholder="Supplier or manufacturer name"
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Location
                                </label>

                                <input
                                    wire:model="form.location"
                                    class="boq-field"
                                    placeholder="e.g. Kampala"
                                >

                            </div>

                            <div class="boq-form-span-2">

                                <label class="boq-field-label">
                                    Specification
                                </label>

                                <textarea
                                    wire:model="form.specification"
                                    class="boq-field"
                                    placeholder="Size, grade, thickness, standard, packaging..."
                                ></textarea>

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Source URL
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
                                    Source Reference
                                </label>

                                <input
                                    wire:model="form.source_reference"
                                    class="boq-field"
                                    placeholder="Price list, quotation, market survey..."
                                >

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Price Date *
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

                                    Active price
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
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="boq-btn-primary"
                        >
                            <i class="fas fa-save"></i>
                            Save Price
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>