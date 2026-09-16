<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
                <i class="fas fa-screwdriver-wrench text-[#05645b]"></i>
                Hardware Prices
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Track current construction material and hardware prices.
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

    @if(session('hardware-price-message'))

        <div class="boq-flash">

            <i class="fas fa-circle-check mr-2"></i>

            {{ session('hardware-price-message') }}

        </div>

    @endif

    {{-- Statistics --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

        <div class="boq-stat-card boq-stat-green">

            <div>
                <p class="boq-stat-label">
                    Total Prices
                </p>

                <p class="boq-stat-value">
                    {{ $stats['total'] ?? $prices->total() }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-tags"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-blue">

            <div>
                <p class="boq-stat-label">
                    Active Prices
                </p>

                <p class="boq-stat-value">
                    {{ $stats['active'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-circle-check"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-amber">

            <div>
                <p class="boq-stat-label">
                    Categories
                </p>

                <p class="boq-stat-value">
                    {{ $stats['categories']
                        ?? count($categories)
                    }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-layer-group"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-purple">

            <div>
                <p class="boq-stat-label">
                    Suppliers
                </p>

                <p class="boq-stat-value">
                    {{ $stats['suppliers']
                        ?? count($suppliers)
                    }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-truck"></i>
            </span>

        </div>

    </div>

    {{-- Filters --}}
    <div class="boq-panel p-4">

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(260px,1.5fr)_180px_180px_180px] xl:items-end">

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
                        placeholder="Search item, brand or supplier..."
                    >

                </div>

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
                        All categories
                    </option>

                    @foreach($categories as $categoryOption)

                        <option value="{{ is_object($categoryOption)
                            ? $categoryOption->name
                            : $categoryOption
                        }}">

                            {{ is_object($categoryOption)
                                ? $categoryOption->name
                                : $categoryOption
                            }}

                        </option>

                    @endforeach

                </select>

            </div>

            <div>

                <label class="boq-field-label">
                    Supplier
                </label>

                <select
                    wire:model.live="supplier"
                    class="boq-field"
                >
                    <option value="">
                        All suppliers
                    </option>

                    @foreach($suppliers as $supplierOption)

                        <option value="{{ $supplierOption }}">
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
                        All locations
                    </option>

                    @foreach($locations as $locationOption)

                        <option value="{{ $locationOption }}">
                            {{ $locationOption }}
                        </option>

                    @endforeach

                </select>

            </div>

        </div>

        @if($canManage)

            <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-3">

                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">

                    <input
                        type="radio"
                        wire:model.live="status"
                        value="active"
                    >

                    Active

                </label>

                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">

                    <input
                        type="radio"
                        wire:model.live="status"
                        value="inactive"
                    >

                    Inactive

                </label>

                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">

                    <input
                        type="radio"
                        wire:model.live="status"
                        value="all"
                    >

                    All

                </label>

            </div>

        @endif

    </div>

    {{-- Hardware Table --}}
    <div class="boq-panel overflow-hidden">

        <div class="overflow-x-auto">

            <table class="boq-table min-w-full">

                <thead>

                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Price</th>
                        <th>Supplier</th>
                        <th>Location</th>
                        <th>Updated</th>
                        <th class="text-right">Actions</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse($prices as $price)

                        <tr wire:key="hardware-price-{{ $price->id }}">

                            <td>

                                <a
                                    href="{{ url('/hardware-prices/'.$price->id) }}"
                                    class="font-semibold text-[#05645b] hover:underline"
                                >
                                    {{ $price->item_name }}
                                </a>

                                @if($price->brand)

                                    <div class="mt-0.5 text-xs text-slate-500">
                                        {{ $price->brand }}
                                    </div>

                                @endif

                            </td>

                            <td>

                                <span class="boq-badge">

                                    <i class="fas fa-boxes-stacked mr-1"></i>

                                    {{ $price->hardwareCategory?->name
                                        ?? $price->category
                                    }}

                                </span>

                            </td>

                            <td>
                                {{ $price->unit }}
                            </td>

                            <td class="text-right font-semibold text-slate-900">

                                {{ $price->currency }}

                                {{ number_format(
                                    (float) $price->price,
                                    0
                                ) }}

                            </td>

                            <td>
                                {{ $price->supplier }}
                            </td>

                            <td>
                                {{ $price->location ?: '—' }}
                            </td>

                            <td>
                                {{ $price->fetched_at?->format('d M Y')
                                    ?? '—'
                                }}
                            </td>

                            <td class="text-right">

                                <div class="inline-flex items-center gap-2">

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
                                            class="boq-icon-btn {{
                                                $price->is_active
                                                    ? 'text-red-600'
                                                    : 'text-emerald-600'
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
                                colspan="8"
                                class="p-10 text-center text-sm text-slate-500"
                            >

                                <i class="fas fa-box-open mb-2 block text-2xl text-slate-300"></i>

                                No hardware prices found.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if($prices->hasPages())

            <div class="border-t border-slate-200 p-4">
                {{ $prices->links() }}
            </div>

        @endif

    </div>

    {{-- Add/Edit modal --}}
    @if($showForm)

        <div
            class="boq-modal-backdrop"
            wire:key="hardware-price-modal"
        >

            <div class="boq-modal boq-modal-lg">

                <div class="boq-modal-head">

                    <div>

                        <h2 class="text-lg font-bold text-slate-900">

                            <i class="fas {{
                                $editingId
                                    ? 'fa-pen'
                                    : 'fa-plus'
                            }} mr-2 text-[#05645b]"></i>

                            {{ $editingId
                                ? 'Edit Hardware Price'
                                : 'Add Hardware Price'
                            }}

                        </h2>

                        <p class="mt-1 text-xs text-slate-500">
                            Enter the current market price details.
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

                <div class="boq-modal-body">

                    <div class="grid gap-4 md:grid-cols-2">

                        <div class="md:col-span-2">

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
                                Brand
                            </label>

                            <input
                                wire:model="form.brand"
                                class="boq-field"
                                placeholder="e.g. Hima Cement"
                            >

                        </div>

                        <div>

                            <label class="boq-field-label">
                                Category *
                            </label>

                            <input
                                wire:model="form.category"
                                class="boq-field"
                                placeholder="e.g. Cement & Concrete"
                            >

                            @error('form.category')
                                <p class="boq-field-error">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        <div>

                            <label class="boq-field-label">
                                Unit *
                            </label>

                            <input
                                wire:model="form.unit"
                                class="boq-field"
                                placeholder="e.g. bag, piece, metre"
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
                                    'EUR'
                                ] as $currencyCode)

                                    <option value="{{ $currencyCode }}">
                                        {{ $currencyCode }}
                                    </option>

                                @endforeach

                            </select>

                        </div>

                        <div>

                            <label class="boq-field-label">
                                Supplier *
                            </label>

                            <input
                                wire:model="form.supplier"
                                class="boq-field"
                                placeholder="Supplier name"
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

                        <div class="md:col-span-2">

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
                                placeholder="Quotation, invoice or reference"
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

                        <div class="flex items-end">

                            <label class="inline-flex h-10 items-center gap-2 text-sm font-medium text-slate-700">

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
                        <i class="fas fa-arrow-left"></i>
                        Cancel
                    </button>

                    <button
                        type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="boq-btn-primary"
                    >
                        <i class="fas fa-floppy-disk"></i>
                        Save Price
                    </button>

                </div>

            </div>

        </div>

    @endif

</div>
