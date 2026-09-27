<div class="space-y-5" x-data="{ confirmAccept: false, acceptId: null, acceptNo: '', confirmReject: false, rejectId: null, rejectNo: '' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">{{ __('Supplier Quotations') }}</h1><p class="text-sm text-slate-500">{{ __('Review, approve lines and promote prices into the rate library.') }}</p></div>
    </div>
    @if(session('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search quote number or supplier...') }}" class="w-full rounded-lg border-slate-300 text-sm md:w-96">
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-sm">
            <option value="">{{ __('All statuses') }}</option>
            <option>{{ __('draft') }}</option>
            <option>{{ __('sent') }}</option>
            <option>{{ __('received') }}</option>
            <option>{{ __('reviewed') }}</option>
            <option>{{ __('accepted') }}</option>
            <option>{{ __('rejected') }}</option>
            <option>{{ __('expired') }}</option>
        </select>
    </div>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr>@foreach(['Quote #','Supplier','Date','Valid until','Total','Status','Actions'] as $h)<th class="px-4 py-3 text-left">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($quotations as $quotation)
                <tr>
                    <td class="px-4 py-3 font-mono text-sm font-semibold">{{ $quotation->quote_number }}</td>
                    <td class="px-4 py-3 font-semibold">{{ $quotation->supplier?->name }}</td>
                    <td class="px-4 py-3 text-sm">{{ \App\Support\Format::date($quotation->quotation_date, false) }}</td>
                    <td class="px-4 py-3 text-sm">{{ \App\Support\Format::date($quotation->valid_until, false) ?: '—' }}</td>
                    <td class="px-4 py-3 font-semibold">{{ $quotation->currency }} {{ \App\Support\Format::number((float)$quotation->total_amount, 2) }}</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ match($quotation->status) { 'accepted' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-red-100 text-red-700', 'reviewed' => 'bg-sky-100 text-sky-700', 'expired' => 'bg-slate-100 text-slate-600', default => 'bg-amber-100 text-amber-700' } }}">{{ ucfirst($quotation->status) }}</span></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button wire:click="view({{ $quotation->id }})" class="mr-3 text-sm font-semibold text-emerald-700">{{ __('Review') }}</button>
                        @if($quotation->status === 'received' || $quotation->status === 'reviewed')
                            <button @click="acceptId={{$quotation->id}}; acceptNo=@js($quotation->quote_number); confirmAccept=true" class="mr-3 text-sm text-slate-600">{{ __('Accept') }}</button>
                            <button @click="rejectId={{$quotation->id}}; rejectNo=@js($quotation->quote_number); confirmReject=true" class="text-sm font-semibold text-red-600">{{ __('Reject') }}</button>
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="7" class="p-8 text-center text-slate-500">{{ __('No quotations found.') }}</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $quotations->links() }}</div>
    </div>

    @if($this->viewing)
    <div class="boq-modal-backdrop" x-data @keydown.escape.window="$wire.close()">
        <div class="boq-modal boq-modal-xl" @click.stop>
            <div class="boq-modal-head">
                <div><h2 class="text-lg font-bold">{{ $viewing->quote_number }}</h2><p class="text-xs text-slate-500">From {{ $viewing->supplier?->name }} · {{ $viewing->currency }} · {{ $viewing->total_amount ? \App\Support\Format::number((float)$viewing->total_amount, 2) : '—' }}</p></div>
                <button type="button" wire:click="close" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <div class="boq-modal-body">
                <table class="boq-table min-w-full divide-y divide-slate-200">
                    <thead><tr>@foreach(['Product','Description','Qty','Unit','Unit price','Line total','Approve'] as $h)<th class="px-3 py-2 text-left text-xs">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach($viewing->items as $item)
                        <tr>
                            <td class="px-3 py-2 text-sm font-semibold">{{ $item->product }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500 max-w-xs truncated">{{ $item->description }}</td>
                            <td class="px-3 py-2 text-sm">{{ \App\Support\Format::number((float)$item->quantity, 2) }}</td>
                            <td class="px-3 py-2 text-sm">{{ $item->unit }}</td>
                            <td class="px-3 py-2 text-sm">{{ \App\Support\Format::number((float)$item->unit_price, 2) }}</td>
                            <td class="px-3 py-2 text-sm font-semibold">{{ \App\Support\Format::number((float)$item->line_total, 2) }}</td>
                            <td class="px-3 py-2"><input type="checkbox" @checked($item->approved) wire:change="preapproveLine({{ $item->id }})" @disabled(in_array($viewing->status, ['accepted','rejected']))></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if($viewing->status === 'received' || $viewing->status === 'reviewed')
                <div class="mt-4 flex flex-wrap gap-3">
                    <button wire:click="review({{ $viewing->id }})" class="boq-btn-secondary">{{ __('Mark reviewed') }}</button>
                    <button @click="acceptId={{$viewing->id}}; acceptNo=@js($viewing->quote_number); confirmAccept=true" class="boq-btn-primary">{{ __('Accept & promote to rates') }}</button>
                    <button @click="rejectId={{$viewing->id}}; rejectNo=@js($viewing->quote_number); confirmReject=true" class="boq-btn-danger">{{ __('Reject') }}</button>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div x-show="confirmAccept" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmAccept=false">
        <div class="boq-modal boq-modal-sm" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">{{ __('Accept quotation?') }}</h2><button @click="confirmAccept=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">{{ __('Accept') }} <strong x-text="acceptNo"></strong>{{ __('? Approved lines will be promoted into the rate library.') }}</div>
            <div class="boq-modal-foot"><button @click="confirmAccept=false" class="boq-btn-secondary">{{ __('Cancel') }}</button><button @click="$wire.accept(acceptId); confirmAccept=false" class="boq-btn-primary">{{ __('Accept') }}</button></div>
        </div>
    </div>

    <div x-show="confirmReject" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmReject=false">
        <div class="boq-modal boq-modal-sm" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">{{ __('Reject quotation?') }}</h2><button @click="confirmReject=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">{{ __('Reject') }} <strong x-text="rejectNo"></strong>{{ __('? No rates will be added.') }}</div>
            <div class="boq-modal-foot"><button @click="confirmReject=false" class="boq-btn-secondary">{{ __('Cancel') }}</button><button @click="$wire.reject(rejectId); confirmReject=false" class="boq-btn-danger">{{ __('Reject') }}</button></div>
        </div>
    </div>
</div>