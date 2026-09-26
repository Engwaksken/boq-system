<div class="boq-page-stack">

    <div class="boq-page-header">

        <div>
            <h1 class="boq-page-title">
                <i class="fas fa-book"></i>
                Rate Library
            </h1>

            <p class="boq-page-subtitle">
                Central verified construction rates used to price BOQ items.
            </p>
        </div>

        <button
            type="button"
            wire:click="create"
            class="boq-btn-primary"
        >
            <i class="fas fa-plus"></i>
            Add Rate
        </button>

    </div>


    @include('livewire.admin._tabs')


    @if(session('message'))
        <div
            class="boq-flash"
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
        >
            <i class="fas fa-circle-check"></i>
            {{ session('message') }}
        </div>
    @endif


    <div class="boq-panel">

        <div class="boq-rate-filter-grid">

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
                        placeholder="Search item, description or code..."
                    >

                </div>

            </div>


            <div>

                <label class="boq-field-label">
                    Verification
                </label>

                <select
                    wire:model.live="verification"
                    class="boq-field"
                >
                    <option value="">
                        All statuses
                    </option>

                    <option value="approved">
                        Approved
                    </option>

                    <option value="pending">
                        Pending
                    </option>

                    <option value="draft">
                        Draft
                    </option>

                    <option value="rejected">
                        Rejected
                    </option>

                    <option value="expired">
                        Expired
                    </option>
                </select>

            </div>


            <div>

                <label class="boq-field-label">
                    Currency
                </label>

                <select
                    wire:model.live="currency"
                    class="boq-field"
                >
                    <option value="">
                        All currencies
                    </option>

                    @foreach([
                        'UGX',
                        'USD',
                        'KES',
                        'TZS',
                        'RWF',
                        'EUR',
                    ] as $currencyCode)

                        <option value="{{ $currencyCode }}">
                            {{ $currencyCode }}
                        </option>

                    @endforeach
                </select>

            </div>

        </div>

    </div>


    <div class="boq-panel">

        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Unit</th>
                        <th class="text-right">Rate</th>
                        <th>Currency</th>
                        <th>Region</th>
                        <th>Supplier</th>
                        <th>Verification</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>


                <tbody>

                    @forelse($rates as $rate)

                        @php
                            $statusClass = match(
                                $rate->verification_status
                            ) {
                                'approved' =>
                                    'boq-badge-success',

                                'pending' =>
                                    'boq-badge-warning',

                                'rejected',
                                'expired' =>
                                    'boq-badge-danger',

                                default => '',
                            };
                        @endphp

                        <tr wire:key="rate-{{ $rate->id }}">

                            <td>
                                <div class="boq-table-title">
                                    {{ $rate->item }}
                                </div>

                                @if($rate->description)
                                    <div class="boq-table-subtitle">
                                        {{ $rate->description }}
                                    </div>
                                @endif

                                @if($rate->code)
                                    <div class="boq-table-meta">
                                        {{ $rate->code }}
                                    </div>
                                @endif
                            </td>


                            <td>
                                {{ $rate->unit }}
                            </td>


                            <td class="text-right">
                                <strong>
                                    {{ number_format(
                                        (float) $rate->rate,
                                        2
                                    ) }}
                                </strong>
                            </td>


                            <td>
                                <span class="boq-currency-badge">
                                    {{ $rate->currency }}
                                </span>
                            </td>


                            <td>
                                {{ $rate->region ?: '—' }}
                            </td>


                            <td>
                                {{ $rate->supplier?->name ?: '—' }}
                            </td>


                            <td>
                                <span class="boq-badge {{ $statusClass }}">
                                    {{ ucfirst(
                                        $rate->verification_status
                                    ) }}
                                </span>
                            </td>


                            <td class="text-right">

                                <div class="boq-table-actions">

                                    @if(
                                        in_array(
                                            $rate->verification_status,
                                            [
                                                'pending',
                                                'draft',
                                                'rejected',
                                            ],
                                            true
                                        )
                                    )

                                        <button
                                            type="button"
                                            wire:click="approve({{ $rate->id }})"
                                            class="boq-icon-btn boq-icon-success"
                                            title="Approve rate"
                                        >
                                            <i class="fas fa-check"></i>
                                        </button>

                                    @endif


                                    @if(
                                        ! in_array(
                                            $rate->verification_status,
                                            [
                                                'rejected',
                                                'approved',
                                            ],
                                            true
                                        )
                                    )

                                        <button
                                            type="button"
                                            wire:click="reject({{ $rate->id }})"
                                            class="boq-icon-btn boq-icon-danger"
                                            title="Reject rate"
                                        >
                                            <i class="fas fa-xmark"></i>
                                        </button>

                                    @endif


                                    <button
                                        type="button"
                                        wire:click="edit({{ $rate->id }})"
                                        class="boq-icon-btn"
                                        title="Edit rate"
                                    >
                                        <i class="fas fa-pen"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>


                    @empty

                        <tr>
                            <td
                                colspan="8"
                                class="boq-empty-table"
                            >
                                <i class="fas fa-book"></i>

                                <span>
                                    No rates found.
                                </span>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        @if($rates->hasPages())
            <div class="boq-pagination">
                {{ $rates->links() }}
            </div>
        @endif

    </div>


    @if($showForm)

        <div
            class="boq-modal-backdrop"
            wire:key="rate-form-modal"
            x-data
            @keydown.escape.window="$wire.cancel()"
        >

            <div
                class="boq-modal boq-modal-lg"
                @click.stop
            >

                <div class="boq-modal-head">

                    <div>

                        <h2>
                            {{ $editingId
                                ? 'Edit Rate'
                                : 'Add Rate'
                            }}
                        </h2>

                        <p class="boq-page-subtitle">
                            Rates become effective after approval.
                        </p>

                    </div>

                    <button
                        type="button"
                        wire:click="cancel"
                        class="boq-modal-close"
                    >
                        <i class="fas fa-xmark"></i>
                    </button>

                </div>


                <form wire:submit="save">

                    <div class="boq-modal-body">

                        <div class="boq-form-grid">

                            <div class="boq-form-span-2">

                                <label class="boq-field-label">
                                    Item
                                </label>

                                <input
                                    wire:model="form.item"
                                    class="boq-field"
                                >

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Category
                                </label>

                                <input
                                    wire:model="form.category"
                                    class="boq-field"
                                >

                            </div>


                            <div class="boq-form-span-2">

                                <label class="boq-field-label">
                                    Description
                                </label>

                                <textarea
                                    wire:model="form.description"
                                    rows="3"
                                    class="boq-field boq-textarea"
                                ></textarea>

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Unit
                                </label>

                                <input
                                    wire:model="form.unit"
                                    class="boq-field"
                                >

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Rate
                                </label>

                                <input
                                    wire:model="form.rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="boq-field"
                                >

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Currency
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
                                        'EUR',
                                    ] as $currencyCode)

                                        <option value="{{ $currencyCode }}">
                                            {{ $currencyCode }}
                                        </option>

                                    @endforeach
                                </select>

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Region
                                </label>

                                <input
                                    wire:model="form.region"
                                    class="boq-field"
                                >

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Supplier
                                </label>

                                <select
                                    wire:model="form.supplier_id"
                                    class="boq-field"
                                >
                                    <option value="">
                                        — None —
                                    </option>

                                    @foreach($this->suppliers as $supplier)

                                        <option value="{{ $supplier['id'] }}">
                                            {{ $supplier['name'] }}
                                        </option>

                                    @endforeach
                                </select>

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Source Type
                                </label>

                                <select
                                    wire:model="form.source_type"
                                    class="boq-field"
                                >
                                    @foreach([
                                        'previous_boq',
                                        'supplier_quotation',
                                        'supplier_price_list',
                                        'procurement',
                                        'market_survey',
                                        'reference_schedule',
                                        'external_feed',
                                        'manual',
                                    ] as $type)

                                        <option value="{{ $type }}">
                                            {{ ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $type
                                                )
                                            ) }}
                                        </option>

                                    @endforeach
                                </select>

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Source Reference
                                </label>

                                <input
                                    wire:model="form.source_reference"
                                    class="boq-field"
                                >

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Effective From
                                </label>

                                <input
                                    wire:model="form.effective_from"
                                    type="date"
                                    class="boq-field"
                                >

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Effective Until
                                </label>

                                <input
                                    wire:model="form.effective_until"
                                    type="date"
                                    class="boq-field"
                                >

                            </div>


                            <div>

                                <label class="boq-field-label">
                                    Verification
                                </label>

                                <select
                                    wire:model="form.verification_status"
                                    class="boq-field"
                                >
                                    <option value="draft">
                                        Draft
                                    </option>

                                    <option value="pending">
                                        Pending
                                    </option>

                                    <option value="approved">
                                        Approved
                                    </option>
                                </select>

                            </div>

                        </div>


                        @if($errors->any())

                            <div class="boq-form-error-summary">
                                <i class="fas fa-circle-exclamation"></i>

                                {{ $errors->first() }}
                            </div>

                        @endif

                    </div>


                    <div class="boq-modal-foot">

                        <button
                            type="button"
                            wire:click="cancel"
                            class="boq-btn-secondary"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="boq-btn-primary"
                        >
                            <i class="fas fa-save"></i>
                            Save Rate
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>