<div class="boq-page-stack" x-data="{ confirmAccept: false, acceptId: null, acceptNo: '', confirmReject: false, rejectId: null, rejectNo: '' }">
    <x-ui.page-header
        :title="__('Supplier Quotations')"
        icon="fa-file-invoice"
        :subtitle="__('Review, approve lines and promote prices into the rate library.')"
    />

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-panel">
        <div class="boq-toolbar border-b border-slate-200">
            <x-ui.field :label="__('Search')" for="quote-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-magnifying-glass boq-input-icon" aria-hidden="true"></i>
                    <input id="quote-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search quote number or supplier...') }}" class="boq-field boq-field-with-icon">
                </div>
            </x-ui.field>

            <x-ui.field :label="__('Status')" for="quote-status" class="w-full sm:w-44">
                {{-- Option values stay in English: they are the stored statuses, only the labels are translated. --}}
                <select id="quote-status" wire:model.live="status" class="boq-field">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach(['draft', 'sent', 'received', 'reviewed', 'accepted', 'rejected', 'expired'] as $quoteStatus)
                        <option value="{{ $quoteStatus }}">{{ __(ucfirst($quoteStatus)) }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <x-ui.table>
            <thead>
                <tr>
                    <th>{{ __('Quote #') }}</th>
                    <th>{{ __('Supplier') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Valid until') }}</th>
                    <th class="text-right">{{ __('Total') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotations as $quotation)
                    <tr wire:key="quotation-{{ $quotation->id }}">
                        <td><span class="boq-code">{{ $quotation->quote_number }}</span></td>
                        <td class="font-semibold text-slate-900">{{ $quotation->supplier?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ \App\Support\Format::date($quotation->quotation_date) ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ \App\Support\Format::date($quotation->valid_until) ?? '—' }}</td>
                        <td class="is-numeric font-semibold text-slate-900"><x-money :amount="$quotation->total_amount ?? 0" :currency="$quotation->currency" /></td>
                        <td><x-ui.status :status="$quotation->status" /></td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button type="button" wire:click="view({{ $quotation->id }})" class="boq-btn-secondary boq-btn-sm"><i class="fas fa-eye" aria-hidden="true"></i> {{ __('Review') }}</button>
                                @if(in_array($quotation->status, ['received', 'reviewed'], true))
                                    <button type="button" @click="acceptId={{ $quotation->id }}; acceptNo=@js($quotation->quote_number); confirmAccept=true" class="boq-icon-btn boq-icon-success" title="{{ __('Accept') }}" aria-label="{{ __('Accept') }}"><i class="fas fa-check" aria-hidden="true"></i></button>
                                    <button type="button" @click="rejectId={{ $quotation->id }}; rejectNo=@js($quotation->quote_number); confirmReject=true" class="boq-icon-btn boq-icon-danger" title="{{ __('Reject') }}" aria-label="{{ __('Reject') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-0"><x-ui.empty-state icon="fa-file-invoice" :title="__('No quotations found.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($quotations->hasPages())<div class="boq-pagination">{{ $quotations->links() }}</div>@endif
    </div>

    @if($this->viewing)
        <x-ui.modal
            wire:key="quotation-view-{{ $viewing->id }}"
            id="quotation-view"
            :title="$viewing->quote_number"
            :subtitle="__('From :supplier', ['supplier' => $viewing->supplier?->name ?? '—']).' · '.($viewing->total_amount ? \App\Support\Format::money($viewing->total_amount, $viewing->currency) : '—')"
            icon="fa-file-invoice"
            size="xl"
            close="close"
        >
            <div class="boq-table-wrapper rounded-lg border border-slate-200">
                <table class="boq-table">
                    <thead>
                        <tr>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="text-right">{{ __('Qty') }}</th>
                            <th>{{ __('Unit') }}</th>
                            <th class="text-right">{{ __('Unit price') }}</th>
                            <th class="text-right">{{ __('Line total') }}</th>
                            <th class="text-center">{{ __('Approve') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($viewing->items as $item)
                            <tr wire:key="quote-line-{{ $item->id }}">
                                <td class="font-semibold text-slate-900">{{ $item->product }}</td>
                                <td class="max-w-xs text-xs text-slate-500">{{ $item->description }}</td>
                                <td class="is-numeric">{{ \App\Support\Format::number((float) $item->quantity, 2) }}</td>
                                <td>{{ $item->unit }}</td>
                                <td class="is-numeric">{{ \App\Support\Format::number((float) $item->unit_price, 2) }}</td>
                                <td class="is-numeric font-semibold">{{ \App\Support\Format::number((float) $item->line_total, 2) }}</td>
                                <td class="text-center">
                                    <input type="checkbox" class="boq-checkbox" aria-label="{{ __('Approve') }} {{ $item->product }}" @checked($item->approved) wire:change="preapproveLine({{ $item->id }})" @disabled(in_array($viewing->status, ['accepted', 'rejected'], true))>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="boq-empty-table">{{ __('This quotation has no lines.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(in_array($viewing->status, ['received', 'reviewed'], true))
                <x-slot:footer>
                    <x-ui.button variant="secondary" icon="fa-eye" wire:click="review({{ $viewing->id }})">{{ __('Mark reviewed') }}</x-ui.button>
                    <button type="button" @click="rejectId={{ $viewing->id }}; rejectNo=@js($viewing->quote_number); confirmReject=true" class="boq-btn-danger"><i class="fas fa-xmark" aria-hidden="true"></i> {{ __('Reject') }}</button>
                    <button type="button" @click="acceptId={{ $viewing->id }}; acceptNo=@js($viewing->quote_number); confirmAccept=true" class="boq-btn-primary"><i class="fas fa-check" aria-hidden="true"></i> {{ __('Accept & promote to rates') }}</button>
                </x-slot:footer>
            @endif
        </x-ui.modal>
    @endif

    <div x-show="confirmAccept" x-cloak class="boq-modal-backdrop z-[110]" role="dialog" aria-modal="true" @keydown.escape.window="confirmAccept=false">
        <div class="boq-modal boq-modal-sm" @click.outside="confirmAccept=false">
            <div class="boq-modal-head">
                <h2>{{ __('Accept quotation?') }}</h2>
                <button type="button" @click="confirmAccept=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="boq-modal-body boq-modal-message">{{ __('Accept') }} <strong x-text="acceptNo"></strong>{{ __('? Approved lines will be promoted into the rate library.') }}</div>
            <div class="boq-modal-foot">
                <button type="button" @click="confirmAccept=false" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" @click="$wire.accept(acceptId); confirmAccept=false" class="boq-btn-primary">{{ __('Accept') }}</button>
            </div>
        </div>
    </div>

    <div x-show="confirmReject" x-cloak class="boq-modal-backdrop z-[110]" role="dialog" aria-modal="true" @keydown.escape.window="confirmReject=false">
        <div class="boq-modal boq-modal-sm" @click.outside="confirmReject=false">
            <div class="boq-modal-head">
                <h2>{{ __('Reject quotation?') }}</h2>
                <button type="button" @click="confirmReject=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="boq-modal-body boq-modal-message">{{ __('Reject') }} <strong x-text="rejectNo"></strong>{{ __('? No rates will be added.') }}</div>
            <div class="boq-modal-foot">
                <button type="button" @click="confirmReject=false" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" @click="$wire.reject(rejectId); confirmReject=false" class="boq-btn-danger">{{ __('Reject') }}</button>
            </div>
        </div>
    </div>
</div>
