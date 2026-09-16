<div class="boq-page-stack">

    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title">
                <i class="fas fa-magnifying-glass-dollar"></i>
                Hardware Price Scanner
            </h1>
            <p class="boq-page-subtitle">
                Manage hardware categories, scan current prices, import CSV files and run the scheduled daily fetch.
            </p>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button type="button" wire:click="createCategory" class="boq-btn-secondary">
                <i class="fas fa-folder-plus"></i>
                Add Category
            </button>

            <button type="button" wire:click="downloadCsvTemplate" class="boq-btn-secondary">
                <i class="fas fa-file-arrow-down"></i>
                CSV Template
            </button>
        </div>
    </div>

    @include('livewire.admin._tabs')

    @if(session()->has('modal_success'))
        <div class="boq-flash">
            <i class="fas fa-circle-check"></i>
            {{ session('modal_success') }}
        </div>
    @endif

    @if(session()->has('modal_error'))
        <div class="boq-flash boq-flash-error">
            <i class="fas fa-circle-xmark"></i>
            {{ session('modal_error') }}
        </div>
    @endif

    <div class="boq-stats-grid">
        <div class="boq-stat-card boq-stat-green">
            <div><p class="boq-stat-label">Categories</p><p class="boq-stat-value">{{ $stats['categories'] }}</p></div>
            <span class="boq-stat-icon"><i class="fas fa-folder-tree"></i></span>
        </div>
        <div class="boq-stat-card boq-stat-blue">
            <div><p class="boq-stat-label">Active Categories</p><p class="boq-stat-value">{{ $stats['active_categories'] }}</p></div>
            <span class="boq-stat-icon"><i class="fas fa-folder-open"></i></span>
        </div>
        <div class="boq-stat-card boq-stat-amber">
            <div><p class="boq-stat-label">Hardware Prices</p><p class="boq-stat-value">{{ $stats['prices'] }}</p></div>
            <span class="boq-stat-icon"><i class="fas fa-tags"></i></span>
        </div>
        <div class="boq-stat-card boq-stat-purple">
            <div><p class="boq-stat-label">Active Prices</p><p class="boq-stat-value">{{ $stats['active_prices'] }}</p></div>
            <span class="boq-stat-icon"><i class="fas fa-circle-check"></i></span>
        </div>
    </div>

    {{-- Scanner / CSV / Daily Fetch --}}
    <div class="hardware-admin-grid">

        <section class="boq-panel hardware-admin-card">
            <div class="hardware-card-head">
                <div>
                    <h2><i class="fas fa-robot"></i> AI Price Scanner</h2>
                    <p>Fetch current market estimates through the configured default AI provider.</p>
                </div>
            </div>

            <form wire:submit.prevent="scanPrices">
                <div class="boq-form-grid">
                    <div>
                        <label class="boq-field-label">Category</label>
                        <select wire:model="scanForm.category" class="boq-field">
                            <option value="">Select category...</option>
                            @foreach($activeCategories as $category)
                                <option value="{{ $category->name }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('scanForm.category')<div class="boq-field-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="boq-field-label">Location</label>
                        <input wire:model="scanForm.location" class="boq-field" placeholder="e.g. Kampala">
                        @error('scanForm.location')<div class="boq-field-error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="boq-field-label">Items to Fetch</label>
                        <input type="number" min="1" max="50" wire:model="scanForm.limit" class="boq-field">
                        @error('scanForm.limit')<div class="boq-field-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="hardware-card-actions">
                    <button type="submit" wire:loading.attr="disabled" wire:target="scanPrices" class="boq-btn-primary">
                        <i wire:loading.remove wire:target="scanPrices" class="fas fa-magnifying-glass-dollar"></i>
                        <i wire:loading wire:target="scanPrices" class="fas fa-spinner fa-spin"></i>
                        <span wire:loading.remove wire:target="scanPrices">Scan Prices</span>
                        <span wire:loading wire:target="scanPrices">Scanning...</span>
                    </button>
                </div>
            </form>

            @if($scanResults)
                <div class="hardware-result-block">
                    <h3>Scan Results ({{ count($scanResults) }})</h3>
                    <div class="boq-table-wrapper">
                        <table class="boq-table">
                            <thead><tr><th>Item</th><th>Supplier</th><th class="text-right">Price</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($scanResults as $result)
                                <tr>
                                    <td>{{ $result['item'] }}</td>
                                    <td>{{ $result['supplier'] ?: '—' }}</td>
                                    <td class="text-right">{{ $result['currency'] }} {{ number_format($result['price'], 0) }}</td>
                                    <td><span class="boq-badge {{ $result['status'] === 'created' ? 'boq-badge-success' : 'boq-badge-info' }}">{{ ucfirst($result['status']) }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        <section class="boq-panel hardware-admin-card">
            <div class="hardware-card-head">
                <div>
                    <h2><i class="fas fa-file-csv"></i> CSV Import</h2>
                    <p>Import hardware prices using the standard template.</p>
                </div>

                <button type="button" wire:click="downloadCsvTemplate" class="boq-btn-secondary">
                    <i class="fas fa-download"></i>
                    Download Template
                </button>
            </div>

            <form wire:submit.prevent="importCsv">
                <div>
                    <label class="boq-field-label">CSV File</label>
                    <input type="file" wire:model="csvFile" accept=".csv,.txt">
                    @error('csvFile')<div class="boq-field-error">{{ $message }}</div>@enderror
                </div>

                <p class="hardware-help">
                    Required: item_name, category, unit, price, currency, supplier.
                    Optional: brand, specification, location, source_url, source_reference.
                </p>

                <div class="hardware-card-actions">
                    <button type="submit" wire:loading.attr="disabled" wire:target="importCsv" class="boq-btn-primary">
                        <i wire:loading.remove wire:target="importCsv" class="fas fa-file-import"></i>
                        <i wire:loading wire:target="importCsv" class="fas fa-spinner fa-spin"></i>
                        <span wire:loading.remove wire:target="importCsv">Import CSV</span>
                        <span wire:loading wire:target="importCsv">Importing...</span>
                    </button>
                </div>
            </form>

            @if($importResults)
                <div class="hardware-mini-stats">
                    <div><strong>{{ $importResults['created'] }}</strong><span>Created</span></div>
                    <div><strong>{{ $importResults['updated'] }}</strong><span>Updated</span></div>
                    <div><strong>{{ $importResults['errors'] }}</strong><span>Errors</span></div>
                </div>
            @endif
        </section>

        <section class="boq-panel hardware-admin-card">
            <div class="hardware-card-head">
                <div>
                    <h2><i class="fas fa-clock-rotate-left"></i> Scheduled Fetch</h2>
                    <p>Manually execute the same command used by the daily scheduler.</p>
                </div>
            </div>

            <p class="hardware-help">
                Location: <strong>{{ $scanForm['location'] ?: 'Kampala' }}</strong>.
                The command fetches a small batch for every active category.
            </p>

            <div class="hardware-card-actions">
                <button type="button" wire:click="runFetchCommand" wire:loading.attr="disabled" wire:target="runFetchCommand" class="boq-btn-primary">
                    <i wire:loading.remove wire:target="runFetchCommand" class="fas fa-arrows-rotate"></i>
                    <i wire:loading wire:target="runFetchCommand" class="fas fa-spinner fa-spin"></i>
                    <span wire:loading.remove wire:target="runFetchCommand">Run Daily Fetch</span>
                    <span wire:loading wire:target="runFetchCommand">Running...</span>
                </button>
            </div>

            @if($dailyFetchResults)
                <div class="hardware-command-output">
                    <div class="boq-field-label">Command Output</div>
                    <pre>{{ $dailyFetchResults['output'] ?: 'No command output.' }}</pre>
                </div>
            @endif
        </section>
    </div>

    {{-- Categories --}}
    <section class="boq-panel">
        <div class="hardware-category-header">
            <div>
                <h2><i class="fas fa-folder-tree"></i> Hardware Categories</h2>
                <p>Add categories and the default items the AI scanner should request.</p>
            </div>

            <button type="button" wire:click="createCategory" class="boq-btn-primary">
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
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($categories as $category)
                    <tr wire:key="hardware-category-{{ $category->id }}">
                        <td>
                            <div class="boq-table-title">{{ $category->name }}</div>
                            <div class="boq-table-subtitle">{{ $category->description ?: 'No description' }}</div>
                        </td>
                        <td>{{ count($category->default_items ?? []) }}</td>
                        <td>{{ $category->hardwarePrices()->count() }}</td>
                        <td><span class="boq-badge {{ $category->is_active ? 'boq-badge-success' : '' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ $category->sort_order }}</td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button type="button" wire:click="editCategory({{ $category->id }})" class="boq-icon-btn" title="Edit"><i class="fas fa-pen"></i></button>
                                <button type="button" wire:click="toggleCategory({{ $category->id }})" class="boq-icon-btn" title="{{ $category->is_active ? 'Deactivate' : 'Activate' }}"><i class="fas {{ $category->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i></button>
                                <button type="button" wire:click="confirmDeleteCategory({{ $category->id }})" class="boq-icon-btn boq-icon-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="boq-empty-table"><i class="fas fa-folder-open"></i><span>No hardware categories configured.</span></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Category Add/Edit Modal --}}
    @if($showCategoryModal)
        <div class="boq-modal-backdrop" wire:key="hardware-category-modal">
            <div class="boq-modal boq-modal-lg">
                <div class="boq-modal-head">
                    <div>
                        <h2>{{ $editingCategoryId ? 'Edit Hardware Category' : 'Add Hardware Category' }}</h2>
                        <p class="boq-table-subtitle">Default items are comma-separated and used by AI scanning.</p>
                    </div>
                    <button type="button" wire:click="cancelCategory" class="boq-modal-close"><i class="fas fa-xmark"></i></button>
                </div>

                <form wire:submit.prevent="saveCategory">
                    <div class="boq-modal-body">
                        <div class="boq-form-grid">
                            <div>
                                <label class="boq-field-label">Category Name</label>
                                <input wire:model="categoryForm.name" class="boq-field" placeholder="e.g. Doors & Windows">
                                @error('categoryForm.name')<div class="boq-field-error">{{ $message }}</div>@enderror
                            </div>

                            <div>
                                <label class="boq-field-label">Sort Order</label>
                                <input type="number" min="0" wire:model="categoryForm.sort_order" class="boq-field">
                            </div>

                            <div class="boq-form-span-2">
                                <label class="boq-field-label">Description</label>
                                <textarea wire:model="categoryForm.description" class="boq-field boq-textarea" placeholder="Short description"></textarea>
                            </div>

                            <div class="boq-form-span-2">
                                <label class="boq-field-label">Default Items</label>
                                <textarea wire:model="categoryForm.default_items" class="boq-field boq-textarea" placeholder="Door frames, Timber doors, Aluminium windows"></textarea>
                            </div>

                            <div class="boq-form-span-2 boq-check-row">
                                <label><input type="checkbox" wire:model="categoryForm.is_active"> Active category</label>
                            </div>
                        </div>
                    </div>

                    <div class="boq-modal-foot">
                        <button type="button" wire:click="cancelCategory" class="boq-btn-secondary">Cancel</button>
                        <button class="boq-btn-primary"><i class="fas fa-save"></i> Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Delete Category Modal --}}
    @if($showDeleteCategoryModal)
        <div class="boq-modal-backdrop" wire:key="hardware-category-delete-modal">
            <div class="boq-modal boq-modal-sm">
                <div class="boq-modal-head"><h2>Delete hardware category?</h2></div>
                <div class="boq-modal-body">
                    <p class="boq-modal-message">
                        The category can only be deleted when no hardware prices use it. Otherwise, deactivate it.
                    </p>
                </div>
                <div class="boq-modal-foot">
                    <button type="button" wire:click="$set('showDeleteCategoryModal', false)" class="boq-btn-secondary">Cancel</button>
                    <button type="button" wire:click="deleteCategory" class="boq-btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
