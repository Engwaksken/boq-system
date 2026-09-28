@php
    $sourceTypes = ['previous_boq', 'supplier_quotation', 'supplier_price_list', 'procurement', 'market_survey', 'reference_schedule', 'external_feed', 'manual'];
@endphp

<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Rate Library')"
        icon="fa-book"
        :subtitle="__('Central verified construction rates used to price BOQ items.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-plus" wire:click="create">{{ __('Add Rate') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-panel">
        <div class="boq-rate-filter-grid">
            <x-ui.field :label="__('Search')" for="rate-search">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input id="rate-search" type="search" wire:model.live.debounce.300ms="search" class="boq-field boq-field-with-icon" placeholder="{{ __('Search item, description or code...') }}">
                </div>
            </x-ui.field>

            <x-ui.field :label="__('Verification')" for="rate-verification">
                <select id="rate-verification" wire:model.live="verification" class="boq-field">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="approved">{{ __('Approved') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="rejected">{{ __('Rejected') }}</option>
                    <option value="expired">{{ __('Expired') }}</option>
                </select>
            </x-ui.field>

            <x-ui.field :label="__('Currency')" for="rate-currency">
                <select id="rate-currency" wire:model.live="currency" class="boq-field">
                    <option value="">{{ __('All currencies') }}</option>
                    @foreach(\App\Models\Currency::activeCodes() as $currencyCode)
                        <option value="{{ $currencyCode }}">{{ $currencyCode }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>
    </div>

    <div class="boq-panel">
        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkApprove" class="boq-btn-secondary"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('Approve') }}</button>
            <button type="button" wire:click="bulkReject" wire:confirm="{{ __('Reject the selected rates?') }}" class="boq-btn-danger"><i class="fas fa-circle-xmark" aria-hidden="true"></i> {{ __('Reject') }}</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected rates? Rates used in quotations are skipped.') }}" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$rates->pluck('id')" :selected="$selected" /></th>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('Unit') }}</th>
                    <th class="text-right">{{ __('Rate') }}</th>
                    <th>{{ __('Region') }}</th>
                    <th>{{ __('Supplier') }}</th>
                    <th>{{ __('Verification') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rates as $rate)
                    <tr wire:key="rate-{{ $rate->id }}">
                        <td class="boq-check-col"><x-select-row :id="$rate->id" /></td>
                        <td>
                            <div class="boq-table-title">{{ $rate->item }}</div>
                            @if($rate->description)
                                <div class="boq-table-subtitle">{{ \Illuminate\Support\Str::limit($rate->description, 90) }}</div>
                            @endif
                            @if($rate->code)
                                <div class="boq-table-meta">{{ $rate->code }}</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">{{ $rate->unit ?: '—' }}</td>
                        <td class="is-numeric font-semibold text-slate-900"><x-money :amount="$rate->rate" :currency="$rate->currency" /></td>
                        <td>{{ $rate->region ?: '—' }}</td>
                        <td>{{ $rate->supplier?->name ?: '—' }}</td>
                        <td><x-ui.status :status="$rate->verification_status" /></td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                @if(in_array($rate->verification_status, ['pending', 'draft', 'rejected'], true))
                                    <button type="button" wire:click="approve({{ $rate->id }})" class="boq-icon-btn boq-icon-success" title="{{ __('Approve rate') }}" aria-label="{{ __('Approve rate') }}"><i class="fas fa-check" aria-hidden="true"></i></button>
                                @endif
                                @if(! in_array($rate->verification_status, ['rejected', 'approved'], true))
                                    <button type="button" wire:click="reject({{ $rate->id }})" class="boq-icon-btn boq-icon-danger" title="{{ __('Reject rate') }}" aria-label="{{ __('Reject rate') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                                @endif
                                <button type="button" wire:click="edit({{ $rate->id }})" class="boq-icon-btn" title="{{ __('Edit rate') }}" aria-label="{{ __('Edit rate') }}"><i class="fas fa-pen" aria-hidden="true"></i></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-0"><x-ui.empty-state icon="fa-book" :title="__('No rates found.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($rates->hasPages())
            <div class="boq-pagination">{{ $rates->links() }}</div>
        @endif
    </div>

    @if($showForm)
        <x-ui.modal
            wire:key="rate-form-modal"
            id="rate-form"
            :title="$editingId ? __('Edit Rate') : __('Add Rate')"
            :subtitle="__('Rates become effective after approval.')"
            icon="fa-book"
            size="lg"
            close="cancel"
            submit="save"
        >
            <div class="boq-form-grid">
                <x-select-with-other
                    :label="__('Category')"
                    choice="categoryChoice"
                    value="form.category"
                    :current="$categoryChoice"
                    :options="$categoryOptions"
                    :placeholder="__('Select category...')"
                    :other-placeholder="__('Type a new category')"
                    error="form.category"
                />

                <x-select-with-other
                    class="boq-form-span-2"
                    :label="__('Item')"
                    choice="itemChoice"
                    value="form.item"
                    :current="$itemChoice"
                    :options="$itemOptions"
                    :placeholder="__('Select item...')"
                    :other-placeholder="__('Type a new item')"
                    error="form.item"
                    required
                />

                <x-ui.field :label="__('Description')" for="rate-description" error="form.description" class="boq-form-span-2">
                    <textarea id="rate-description" placeholder="{{ __('Add description...') }}" wire:model="form.description" rows="3" class="boq-field boq-textarea"></textarea>
                </x-ui.field>

                <x-ui.field :label="__('Unit')" for="rate-unit" error="form.unit" required>
                    <input id="rate-unit" placeholder="{{ __('e.g. bag, m³, kg, piece') }}" wire:model="form.unit" class="boq-field @error('form.unit') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Rate')" for="rate-rate" error="form.rate" required>
                    <input id="rate-rate" placeholder="0.00" wire:model="form.rate" type="number" step="0.01" min="0" inputmode="decimal" class="boq-field @error('form.rate') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Currency')" for="rate-form-currency" error="form.currency" required>
                    <x-currency-select id="rate-form-currency" wire:model="form.currency" :current="$form['currency'] ?? null" />
                </x-ui.field>

                <x-ui.field :label="__('Region')" for="rate-region" error="form.region">
                    <input id="rate-region" placeholder="{{ __('e.g. city, town or market') }}" wire:model="form.region" class="boq-field">
                </x-ui.field>

                <x-ui.field :label="__('Supplier')" for="rate-supplier" error="form.supplier_id">
                    <select id="rate-supplier" wire:model="form.supplier_id" class="boq-field">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach($this->suppliers as $supplier)
                            <option value="{{ $supplier['id'] }}">{{ $supplier['name'] }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Source Type')" for="rate-source-type" error="form.source_type">
                    <select id="rate-source-type" wire:model="form.source_type" class="boq-field">
                        @foreach($sourceTypes as $type)
                            <option value="{{ $type }}">{{ __(ucwords(str_replace('_', ' ', $type))) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Source Reference')" for="rate-source-ref" error="form.source_reference">
                    <input id="rate-source-ref" placeholder="{{ __('e.g. supplier quote #123') }}" wire:model="form.source_reference" class="boq-field">
                </x-ui.field>

                <x-ui.field :label="__('Effective From')" for="rate-from" error="form.effective_from">
                    <input id="rate-from" wire:model="form.effective_from" type="date" class="boq-field @error('form.effective_from') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Effective Until')" for="rate-until" error="form.effective_until">
                    <input id="rate-until" wire:model="form.effective_until" type="date" class="boq-field @error('form.effective_until') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Verification')" for="rate-form-verification" error="form.verification_status">
                    <select id="rate-form-verification" wire:model="form.verification_status" class="boq-field">
                        <option value="draft">{{ __('Draft') }}</option>
                        <option value="pending">{{ __('Pending') }}</option>
                        <option value="approved">{{ __('Approved') }}</option>
                    </select>
                </x-ui.field>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ __('Save Rate') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
