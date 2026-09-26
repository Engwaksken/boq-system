<div class="boq-page-stack">

    <div class="boq-page-header">

        <div>

            <h1 class="boq-page-title">
                <i class="fas fa-magnifying-glass-dollar"></i>
                Price Scanner
            </h1>

            <p class="boq-page-subtitle">
                Scan hardware market prices or factory/manufacturer prices for any construction category.
            </p>

        </div>

        <div class="flex flex-wrap gap-2">

            <button
                type="button"
                wire:click="createCategory"
                class="boq-btn-secondary"
            >
                <i class="fas fa-folder-plus"></i>
                Add Category
            </button>

            <button
                type="button"
                wire:click="downloadCsvTemplate"
                class="boq-btn-secondary"
            >
                <i class="fas fa-file-arrow-down"></i>
                CSV Template
            </button>

        </div>

    </div>

    @include(
        'livewire.admin._tabs'
    )

    @if(
        session()
            ->has(
                'modal_success'
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
                    'modal_success'
                )
            }}
        </div>

    @endif

    @if(
        session()
            ->has(
                'modal_error'
            )
    )

        <div
            class="boq-flash boq-flash-error"
        >
            <i class="fas fa-circle-xmark"></i>

            {{
                session(
                    'modal_error'
                )
            }}
        </div>

    @endif

    <div class="boq-stats-grid">

        <div class="boq-stat-card boq-stat-green">

            <div>
                <p class="boq-stat-label">
                    Categories
                </p>

                <p class="boq-stat-value">
                    {{ $stats['categories'] }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-folder-tree"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-blue">

            <div>
                <p class="boq-stat-label">
                    Hardware Prices
                </p>

                <p class="boq-stat-value">
                    {{ $stats['hardware_prices'] }}
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
                    {{ $stats['factory_prices'] }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-industry"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-amber">

            <div>
                <p class="boq-stat-label">
                    Active Prices
                </p>

                <p class="boq-stat-value">
                    {{ $stats['active_prices'] }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-circle-check"></i>
            </span>

        </div>

    </div>

    <section
        class="boq-panel hardware-admin-card"
    >

        <div class="hardware-card-head">

            <div>

                <h2>
                    <i class="fas fa-robot"></i>
                    AI Price Scanner
                </h2>

                <p>
                    Select whether the AI should research hardware supplier prices or direct factory/manufacturer prices.
                </p>

            </div>

        </div>

        <form
            wire:submit.prevent="scanPrices"
        >

            <div class="boq-form-grid">

                <div>

                    <label class="boq-field-label">
                        Price Type
                    </label>

                    <select
                        wire:model="scanForm.price_type"
                        class="boq-field"
                    >
                        <option value="hardware">
                            Hardware Prices
                        </option>

                        <option value="factory">
                            Factory Prices
                        </option>
                    </select>

                    @error('scanForm.price_type')
                        <div class="boq-field-error">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

                <div>

                    <label class="boq-field-label">
                        Category
                    </label>

                    <select
                        wire:model="scanForm.category"
                        class="boq-field"
                    >
                        <option value="">
                            Select category...
                        </option>

                        @foreach(
                            $activeCategories
                            as $category
                        )

                            <option
                                value="{{ $category->name }}"
                            >
                                {{ $category->name }}
                            </option>

                        @endforeach
                    </select>

                    @error('scanForm.category')
                        <div class="boq-field-error">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

                <div>

                    <label class="boq-field-label">
                        Location
                    </label>

                    <input
                        wire:model="scanForm.location"
                        class="boq-field"
                        placeholder="e.g. Kampala"
                    >

                    @error('scanForm.location')
                        <div class="boq-field-error">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

                <div>

                    <label class="boq-field-label">
                        Items to Scan
                    </label>

                    <input
                        type="number"
                        min="1"
                        max="50"
                        wire:model="scanForm.limit"
                        class="boq-field"
                    >

                    @error('scanForm.limit')
                        <div class="boq-field-error">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

            </div>

            <div class="hardware-card-actions">

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="scanPrices"
                    class="boq-btn-primary"
                >

                    <i
                        wire:loading.remove
                        wire:target="scanPrices"
                        class="fas fa-magnifying-glass-dollar"
                    ></i>

                    <i
                        wire:loading
                        wire:target="scanPrices"
                        class="fas fa-spinner fa-spin"
                    ></i>

                    <span
                        wire:loading.remove
                        wire:target="scanPrices"
                    >
                        Scan Prices
                    </span>

                    <span
                        wire:loading
                        wire:target="scanPrices"
                    >
                        Scanning...
                    </span>

                </button>

            </div>

        </form>

        @if($scanResults)

            <div class="hardware-result-block">

                <h3>
                    Scan Results
                    ({{ count($scanResults) }})
                </h3>

                <div class="boq-table-wrapper">

                    <table class="boq-table">

                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Supplier / Factory</th>
                                <th>Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach(
                                $scanResults
                                as $result
                            )

                                <tr>

                                    <td>
                                        {{ $result['item'] }}
                                    </td>

                                    <td>

                                        <span
                                            class="boq-badge {{
                                                $result['price_type'] === 'factory'
                                                    ? 'boq-badge-factory'
                                                    : 'boq-badge-info'
                                            }}"
                                        >
                                            <i class="fas {{
                                                $result['price_type'] === 'factory'
                                                    ? 'fa-industry'
                                                    : 'fa-store'
                                            }}"></i>

                                            {{
                                                $result['price_type'] === 'factory'
                                                    ? 'Factory'
                                                    : 'Hardware'
                                            }}
                                        </span>

                                    </td>

                                    <td>
                                        {{
                                            $result['supplier']
                                            ?: '—'
                                        }}
                                    </td>

                                    <td>
                                        {{
                                            $result['currency']
                                        }}

                                        {{
                                            number_format(
                                                $result['price'],
                                                0
                                            )
                                        }}
                                    </td>

                                    <td>

                                        <span
                                            class="boq-badge {{
                                                $result['status'] === 'created'
                                                    ? 'boq-badge-success'
                                                    : 'boq-badge-info'
                                            }}"
                                        >
                                            {{
                                                ucfirst(
                                                    $result['status']
                                                )
                                            }}
                                        </span>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        @endif

    </section>

    <section class="boq-panel">

        <div class="hardware-category-header">

            <div>

                <h2>
                    <i class="fas fa-folder-tree"></i>
                    Price Categories
                </h2>

                <p>
                    Categories are shared by Hardware Prices and Factory Prices.
                </p>

            </div>

            <button
                type="button"
                wire:click="createCategory"
                class="boq-btn-primary"
            >
                <i class="fas fa-plus"></i>
                Add Category
            </button>

        </div>

        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Default Items</th>
                        <th>Prices</th>
                        <th>Status</th>
                        <th>Order</th>
                        <th class="text-right">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>

                    @forelse(
                        $categories
                        as $category
                    )

                        <tr
                            wire:key="hardware-category-{{ $category->id }}"
                        >

                            <td>

                                <div class="boq-table-title">
                                    {{ $category->name }}
                                </div>

                                <div class="boq-table-subtitle">
                                    {{
                                        $category->description
                                        ?: 'No description'
                                    }}
                                </div>

                            </td>

                            <td>
                                {{
                                    count(
                                        $category->default_items
                                        ?? []
                                    )
                                }}
                            </td>

                            <td>
                                {{
                                    $category
                                        ->hardwarePrices()
                                        ->count()
                                }}
                            </td>

                            <td>

                                <span
                                    class="boq-badge {{
                                        $category->is_active
                                            ? 'boq-badge-success'
                                            : ''
                                    }}"
                                >
                                    {{
                                        $category->is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>

                            </td>

                            <td>
                                {{ $category->sort_order }}
                            </td>

                            <td class="text-right">

                                <div class="boq-table-actions">

                                    <button
                                        type="button"
                                        wire:click="editCategory({{ $category->id }})"
                                        class="boq-icon-btn"
                                        title="Edit"
                                    >
                                        <i class="fas fa-pen"></i>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="toggleCategory({{ $category->id }})"
                                        class="boq-icon-btn"
                                        title="{{
                                            $category->is_active
                                                ? 'Deactivate'
                                                : 'Activate'
                                        }}"
                                    >
                                        <i class="fas {{
                                            $category->is_active
                                                ? 'fa-toggle-on'
                                                : 'fa-toggle-off'
                                        }}"></i>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="confirmDeleteCategory({{ $category->id }})"
                                        class="boq-icon-btn boq-icon-danger"
                                        title="Delete"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="6"
                                class="boq-empty-table"
                            >
                                <i class="fas fa-folder-open"></i>
                                No price categories configured.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    @if($showCategoryModal)

        <div
            class="boq-modal-backdrop"
            wire:key="hardware-category-modal"
        >

            <div class="boq-modal boq-modal-lg">

                <div class="boq-modal-head">

                    <div>

                        <h2>
                            {{
                                $editingCategoryId
                                    ? 'Edit Price Category'
                                    : 'Add Price Category'
                            }}
                        </h2>

                        <p class="boq-table-subtitle">
                            Default items are used by both hardware and factory price scans.
                        </p>

                    </div>

                    <button
                        type="button"
                        wire:click="cancelCategory"
                        class="boq-modal-close"
                    >
                        <i class="fas fa-xmark"></i>
                    </button>

                </div>

                <form
                    wire:submit.prevent="saveCategory"
                >

                    <div class="boq-modal-body">

                        <div class="boq-form-grid">

                            <div>

                                <label class="boq-field-label">
                                    Category Name
                                </label>

                                <input
                                    wire:model="categoryForm.name"
                                    class="boq-field"
                                    placeholder="e.g. Doors & Windows"
                                >

                                @error('categoryForm.name')
                                    <div class="boq-field-error">
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>

                            <div>

                                <label class="boq-field-label">
                                    Sort Order
                                </label>

                                <input
                                    type="number"
                                    min="0"
                                    wire:model="categoryForm.sort_order"
                                    class="boq-field"
                                >

                            </div>

                            <div class="boq-form-span-2">

                                <label class="boq-field-label">
                                    Description
                                </label>

                                <textarea
                                    wire:model="categoryForm.description"
                                    class="boq-field"
                                    placeholder="Short description"
                                ></textarea>

                            </div>

                            <div class="boq-form-span-2">

                                <label class="boq-field-label">
                                    Default Items
                                </label>

                                <textarea
                                    wire:model="categoryForm.default_items"
                                    class="boq-field"
                                    placeholder="Door frames, Timber doors, Aluminium windows"
                                ></textarea>

                            </div>

                            <div class="boq-form-span-2 boq-check-row">

                                <label>
                                    <input
                                        type="checkbox"
                                        wire:model="categoryForm.is_active"
                                    >

                                    Active category
                                </label>

                            </div>

                        </div>

                    </div>

                    <div class="boq-modal-foot">

                        <button
                            type="button"
                            wire:click="cancelCategory"
                            class="boq-btn-secondary"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="boq-btn-primary"
                        >
                            <i class="fas fa-save"></i>
                            Save Category
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

    @if(
        $showDeleteCategoryModal
    )

        <div
            class="boq-modal-backdrop"
            wire:key="hardware-category-delete-modal"
        >

            <div class="boq-modal boq-modal-sm">

                <div class="boq-modal-head">
                    <h2>
                        Delete price category?
                    </h2>
                </div>

                <div class="boq-modal-body">

                    <p class="boq-modal-message">
                        This category can only be deleted when no hardware or factory prices use it. Otherwise deactivate it.
                    </p>

                </div>

                <div class="boq-modal-foot">

                    <button
                        type="button"
                        wire:click="$set('showDeleteCategoryModal', false)"
                        class="boq-btn-secondary"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        wire:click="deleteCategory"
                        class="boq-btn-danger"
                    >
                        <i class="fas fa-trash"></i>
                        Delete
                    </button>

                </div>

            </div>

        </div>

    @endif

</div>