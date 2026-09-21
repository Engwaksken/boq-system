<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">Rate Library</h1><p class="text-sm text-slate-500">Central, verified construction rates used to price BOQ items.</p></div>
        <button wire:click="create" class="boq-btn-primary">+ Add Rate</button>
    </div>
    @include('livewire.admin._tabs')
    @if(session('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" placeholder="Search item, description or code..." class="w-full rounded-lg border-slate-300 text-sm md:w-96">
        <select wire:model.live="verification" class="rounded-lg border-slate-300 text-sm">
            <option value="">All statuses</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="draft">Draft</option>
            <option value="rejected">Rejected</option>
            <option value="expired">Expired</option>
        </select>
        <select wire:model.live="currency" class="rounded-lg border-slate-300 text-sm">
            <option value="">All currencies</option>
            <option>UGX</option>
            <option>USD</option>
            <option>KES</option>
            <option>TZS</option>
            <option>RWF</option>
            <option>EUR</option>
        </select>
    </div>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr>@foreach(['Item','Unit','Rate','Currency','Region','Supplier','Verification','Actions'] as $h)<th class="px-4 py-3 text-left">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($rates as $rate)
                <tr>
                    <td class="px-4 py-3"><div class="font-semibold">{{ $rate->item }}</div><div class="max-w-xs truncate text-xs text-slate-500">{{ $rate->description }}</div><div class="text-[11px] text-slate-400">{{ $rate->code }}</div></td>
                    <td class="px-4 py-3">{{ $rate->unit }}</td>
                    <td class="px-4 py-3 font-semibold">{{ number_format((float)$rate->rate, 2) }}</td>
                    <td class="px-4 py-3">{{ $rate->currency }}</td>
                    <td class="px-4 py-3 text-sm">{{ $rate->region ?: '—' }}</td>
                    <td class="px-4 py-3 text-sm">{{ $rate->supplier?->name ?: '—' }}</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ match($rate->verification_status) { 'approved' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'rejected' => 'bg-red-100 text-red-700', default => 'bg-slate-100 text-slate-600' } }}">{{ ucfirst($rate->verification_status) }}</span></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($rate->verification_status === 'pending' || $rate->verification_status === 'draft' || $rate->verification_status === 'rejected')
                            <button wire:click="approve({{ $rate->id }})" class="mr-3 text-sm font-semibold text-emerald-700">Approve</button>
                            @if($rate->verification_status !== 'rejected')
                                <button wire:click="reject({{ $rate->id }})" class="mr-3 text-sm text-red-600">Reject</button>
                            @endif
                        @endif
                        <button wire:click="edit({{ $rate->id }})" class="text-sm text-slate-600">Edit</button>
                    </td>
                </tr>
            @empty<tr><td colspan="8" class="p-8 text-center text-slate-500">No rates found.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $rates->links() }}</div>
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="rate-form-modal" x-data @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head">
                <div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Rate' : 'Add Rate' }}</h2><p class="text-xs text-slate-500">Rates become effective after approval.</p></div>
                <button type="button" wire:click="cancel" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <form wire:submit="save">
                <div class="boq-modal-body grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2"><label class="text-sm font-medium">Item</label><input wire:model="form.item" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Category</label><input wire:model="form.category" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Description</label><textarea wire:model="form.description" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                    <div><label class="text-sm font-medium">Unit</label><input wire:model="form.unit" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Rate</label><input wire:model="form.rate" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Currency</label><select wire:model="form.currency" class="mt-1 w-full rounded-lg border-slate-300">@foreach(['UGX','USD','KES','TZS','RWF','EUR'] as $c)<option>{{ $c }}</option>@endforeach</select></div>
                    <div><label class="text-sm font-medium">Region</label><input wire:model="form.region" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div>
                        <label class="text-sm font-medium">Supplier</label>
                        <select wire:model="form.supplier_id" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="">— None —</option>
                            @foreach($this->suppliers as $s)<option value="{{ $s['id'] }}">{{ $s['name'] }}</option>@endforeach
                        </select>
                    </div>
                    <div><label class="text-sm font-medium">Source type</label><select wire:model="form.source_type" class="mt-1 w-full rounded-lg border-slate-300">@foreach(['previous_boq','supplier_quotation','supplier_price_list','procurement','market_survey','reference_schedule','external_feed','manual'] as $t)<option value="{{ $t }}">{{ ucwords(str_replace('_',' ',$t)) }}</option>@endforeach</select></div>
                    <div><label class="text-sm font-medium">Source reference</label><input wire:model="form.source_reference" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Effective from</label><input wire:model="form.effective_from" type="date" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Effective until</label><input wire:model="form.effective_until" type="date" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Verification</label><select wire:model="form.verification_status" class="mt-1 w-full rounded-lg border-slate-300"><option value="draft">Draft</option><option value="pending">Pending</option><option value="approved">Approved</option></select></div>
                    @if($errors->any())<div class="md:col-span-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                </div>
                <div class="boq-modal-foot"><button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button><button class="boq-btn-primary">Save Rate</button></div>
            </form>
        </div>
    </div>
    @endif
</div>