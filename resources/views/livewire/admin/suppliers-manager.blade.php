<div class="space-y-5" x-data="{ confirmDeactivate: false, deactivateId: null, deactivateName: '' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">Suppliers</h1><p class="text-sm text-slate-500">Manage suppliers, their price lists and quotations.</p></div>
        <button wire:click="create" class="boq-btn-primary">+ Add Supplier</button>
    </div>
    @include('livewire.admin._tabs')
    @if(session('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="flex gap-3">
        <input wire:model.live.debounce.300ms="search" placeholder="Search name, contact or phone..." class="w-full rounded-lg border-slate-300 text-sm md:w-96">
    </div>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr>@foreach(['Supplier','Contact','Location','Currency','Rates','Status','Actions'] as $h)<th class="px-4 py-3 text-left">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($suppliers as $supplier)
                <tr>
                    <td class="px-4 py-3"><div class="font-semibold">{{ $supplier->name }}</div><div class="text-[11px] text-slate-400">{{ $supplier->code }}</div></td>
                    <td class="px-4 py-3 text-sm"><div>{{ $supplier->contact_name ?: '—' }}</div><div class="text-xs text-slate-500">{{ $supplier->phone }}{{ $supplier->phone && $supplier->email ? ' · ' : '' }}{{ $supplier->email }}</div></td>
                    <td class="px-4 py-3 text-sm">{{ $supplier->location ?: $supplier->region ?: '—' }}</td>
                    <td class="px-4 py-3 text-sm">{{ $supplier->currency }}</td>
                    <td class="px-4 py-3 text-sm">{{ $supplier->rates_count }}</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $supplier->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button wire:click="edit({{ $supplier->id }})" class="mr-3 text-sm font-semibold text-emerald-700">Edit</button>
                        <button wire:click="toggleActive({{ $supplier->id }})" class="mr-3 text-sm text-slate-600">{{ $supplier->is_active ? 'Deactivate' : 'Activate' }}</button>
                        @if($supplier->is_active)
                        <button @click="deactivateId={{$supplier->id}}; deactivateName=@js($supplier->name); confirmDeactivate=true" class="text-sm font-semibold text-red-600">Deactivate</button>
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="7" class="p-8 text-center text-slate-500">No suppliers found.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $suppliers->links() }}</div>
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="supplier-form-modal" x-data @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head">
                <div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Supplier' : 'Add Supplier' }}</h2><p class="text-xs text-slate-500">Supplier details feed quotations and the rate library.</p></div>
                <button type="button" wire:click="cancel" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <form wire:submit="save">
                <div class="boq-modal-body grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2"><label class="text-sm font-medium">Supplier name</label><input wire:model="form.name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Favorite currency</label><input wire:model="form.currency" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Contact person</label><input wire:model="form.contact_name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Phone</label><input wire:model="form.phone" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Email</label><input wire:model="form.email" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Location</label><input wire:model="form.location" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Region</label><input wire:model="form.region" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Preferred language</label><input wire:model="form.preferred_language" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Materials / services supplied (comma separated)</label><input wire:model="form.materials_text" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Notes</label><textarea wire:model="form.notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                    @if($errors->any())<div class="md:col-span-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                </div>
                <div class="boq-modal-foot"><button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button><button class="boq-btn-primary">Save Supplier</button></div>
            </form>
        </div>
    </div>
    @endif

    <div x-show="confirmDeactivate" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmDeactivate=false">
        <div class="boq-modal boq-modal-sm" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">Deactivate supplier?</h2><button @click="confirmDeactivate=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">Deactivate <strong x-text="deactivateName"></strong>? Historical quotations are preserved.</div>
            <div class="boq-modal-foot"><button @click="confirmDeactivate=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.deactivate(deactivateId); confirmDeactivate=false" class="boq-btn-danger">Deactivate</button></div>
        </div>
    </div>
</div>