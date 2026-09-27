<div class="space-y-5" x-data="{ confirmDeactivate: false, deactivateId: null, deactivateName: '' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">{{ __('Suppliers') }}</h1><p class="text-sm text-slate-500">{{ __('Manage suppliers, their price lists and quotations.') }}</p></div>
        <button wire:click="create" class="boq-btn-primary">{{ __('+ Add Supplier') }}</button>
    </div>
    @if(session('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="flex gap-3">
        <input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name, contact or phone...') }}" class="boq-field md:max-w-md">
    </div>

    <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-circle-check"></i> {{ __('Activate') }}</button>
            <button type="button" wire:click="bulkSetActive(false)" wire:confirm="Deactivate the selected suppliers? Historical quotations are kept." class="boq-btn-secondary"><i class="fas fa-ban"></i> {{ __('Deactivate') }}</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="Delete the selected suppliers? Suppliers with rates or quotations are skipped." class="boq-btn-danger"><i class="fas fa-trash"></i> {{ __('Delete') }}</button>
    </x-bulk-bar>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr><th class="boq-check-col"><x-select-all :ids="$suppliers->pluck('id')" :selected="$selected" /></th>@foreach(['Supplier','Contact','Location','Currency','Rates','Status','Actions'] as $h)<th class="px-4 py-3 text-left">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($suppliers as $supplier)
                <tr wire:key="supplier-{{ $supplier->id }}">
                    <td class="boq-check-col"><x-select-row :id="$supplier->id" /></td>
                    <td class="px-4 py-3"><div class="font-semibold">{{ $supplier->name }}</div><div class="text-[11px] text-slate-400">{{ $supplier->code }}</div></td>
                    <td class="px-4 py-3 text-sm"><div>{{ $supplier->contact_name ?: '—' }}</div><div class="text-xs text-slate-500">{{ $supplier->phone }}{{ $supplier->phone && $supplier->email ? ' · ' : '' }}{{ $supplier->email }}</div></td>
                    <td class="px-4 py-3 text-sm">{{ $supplier->location ?: $supplier->region ?: '—' }}</td>
                    <td class="px-4 py-3 text-sm">{{ $supplier->currency }}</td>
                    <td class="px-4 py-3 text-sm">{{ $supplier->rates_count }}</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $supplier->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button wire:click="edit({{ $supplier->id }})" class="mr-3 text-sm font-semibold text-emerald-700">{{ __('Edit') }}</button>
                        <button wire:click="toggleActive({{ $supplier->id }})" class="mr-3 text-sm text-slate-600">{{ $supplier->is_active ? 'Deactivate' : 'Activate' }}</button>
                        @if($supplier->is_active)
                        <button @click="deactivateId={{$supplier->id}}; deactivateName=@js($supplier->name); confirmDeactivate=true" class="text-sm font-semibold text-red-600">{{ __('Deactivate') }}</button>
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="8" class="p-8 text-center text-slate-500">{{ __('No suppliers found.') }}</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $suppliers->links() }}</div>
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="supplier-form-modal" x-data @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head">
                <div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Supplier' : 'Add Supplier' }}</h2><p class="text-xs text-slate-500">{{ __('Supplier details feed quotations and the rate library.') }}</p></div>
                <button type="button" wire:click="cancel" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <form wire:submit="save">
                <div class="boq-modal-body grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2"><label class="text-sm font-medium">{{ __('Supplier name') }}</label><input placeholder="{{ __('Enter name') }}" wire:model="form.name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">{{ __('Preferred currency') }}</label><x-currency-select wire:model="form.currency" :current="$form['currency'] ?? null" class="mt-1" /></div>
                    <div><label class="text-sm font-medium">{{ __('Contact person') }}</label><input placeholder="{{ __('e.g. full name') }}" wire:model="form.contact_name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">{{ __('Phone') }}</label><input placeholder="+1 202 555 0143" wire:model="form.phone" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">{{ __('Email') }}</label><input placeholder="name@example.com" wire:model="form.email" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">{{ __('Location') }}</label><input placeholder="{{ __('e.g. city, town or market') }}" wire:model="form.location" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">{{ __('Region') }}</label><input placeholder="{{ __('e.g. city, town or market') }}" wire:model="form.region" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">{{ __('Preferred language') }}</label><input placeholder="{{ __('e.g. en') }}" wire:model="form.preferred_language" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Materials / services supplied (comma separated)</label><input placeholder="{{ __('e.g. cement, steel, roofing') }}" wire:model="form.materials_text" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">{{ __('Notes') }}</label><textarea placeholder="{{ __('Add notes...') }}" wire:model="form.notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                    @if($errors->any())<div class="md:col-span-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                </div>
                <div class="boq-modal-foot"><button type="button" wire:click="cancel" class="boq-btn-secondary">{{ __('Cancel') }}</button><button class="boq-btn-primary">{{ __('Save Supplier') }}</button></div>
            </form>
        </div>
    </div>
    @endif

    <div x-show="confirmDeactivate" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmDeactivate=false">
        <div class="boq-modal boq-modal-sm" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">{{ __('Deactivate supplier?') }}</h2><button @click="confirmDeactivate=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">{{ __('Deactivate') }} <strong x-text="deactivateName"></strong>{{ __('? Historical quotations are preserved.') }}</div>
            <div class="boq-modal-foot"><button @click="confirmDeactivate=false" class="boq-btn-secondary">{{ __('Cancel') }}</button><button @click="$wire.deactivate(deactivateId); confirmDeactivate=false" class="boq-btn-danger">{{ __('Deactivate') }}</button></div>
        </div>
    </div>
</div>