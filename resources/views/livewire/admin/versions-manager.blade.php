<div class="space-y-5" x-data="{ confirmDelete: false, deleteId: null, deleteName: '' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">Product Versions</h1><p class="text-sm text-slate-500">Manage app releases and the features each version introduces.</p></div>
        <button wire:click="create" class="boq-btn-primary">+ Add Version</button>
    </div>
    @include('livewire.admin._tabs')
    @if(session('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="flex gap-3">
        <input wire:model.live.debounce.300ms="search" placeholder="Search versions..." class="w-full rounded-lg border-slate-300 text-sm md:w-96">
    </div>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr>@foreach(['Version','Name','Released','Classification','New Features','Top-up','Status','Actions'] as $h)<th class="px-4 py-3 text-left">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($versions as $version)
                <tr>
                    <td class="px-4 py-3"><div class="font-semibold">v{{ $version->version_number }}</div></td>
                    <td class="px-4 py-3"><div>{{ $version->name }}</div><div class="text-xs text-slate-500">{{ Str::limit($version->release_notes, 70) }}</div></td>
                    <td class="px-4 py-3 text-sm">{{ $version->release_date?->toDateString() ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $version->classification === 'major' ? 'bg-amber-100 text-amber-700' : ($version->classification === 'minor' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600') }}">{{ ucfirst($version->classification) }}</span></td>
                    <td class="px-4 py-3 text-xs text-slate-600">{{ count($version->included_features ?? []) ? implode(', ', array_slice($version->included_features, 0, 3)).(count($version->included_features) > 3 ? '…' : '') : '—' }}</td>
                    <td class="px-4 py-3">{{ $version->requires_topup ? 'Top-up' : 'Included' }}</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $version->is_active ? 'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-600' }}">{{ $version->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button wire:click="edit({{ $version->id }})" class="mr-3 text-sm font-semibold text-emerald-700">Edit</button>
                        <button wire:click="toggleActive({{ $version->id }})" class="mr-3 text-sm text-slate-600">{{ $version->is_active?'Deactivate':'Activate' }}</button>
                        <button @click="deleteId={{$version->id}}; deleteName=@js($version->name); confirmDelete=true" class="text-sm font-semibold text-red-600">Delete</button>
                    </td>
                </tr>
            @empty<tr><td colspan="8" class="p-8 text-center text-slate-500">No product versions found.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $versions->links() }}</div>
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="version-form-modal" x-data @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head">
                <div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Version' : 'Create Version' }}</h2><p class="text-xs text-slate-500">Changes are saved without leaving this page.</p></div>
                <button type="button" wire:click="cancel" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <form wire:submit="save">
                <div class="boq-modal-body grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div><label class="text-sm font-medium">Version number</label><input wire:model="form.version_number" class="mt-1 w-full rounded-lg border-slate-300" placeholder="2.0"></div>
                    <div><label class="text-sm font-medium">Name</label><input wire:model="form.name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Classification</label>
                        <select wire:model="form.classification" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="major">Major</option><option value="minor">Minor</option><option value="patch">Patch</option>
                        </select>
                    </div>
                    <div><label class="text-sm font-medium">Release date</label><input wire:model="form.release_date" type="date" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Minimum supported version</label><input wire:model="form.minimum_supported_version" class="mt-1 w-full rounded-lg border-slate-300" placeholder="1.0"></div>
                    <div><label class="text-sm font-medium">Requires top-up</label>
                        <select wire:model="form.requires_topup" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="1">Yes</option><option value="0">No</option>
                        </select>
                    </div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Release notes</label><textarea wire:model="form.release_notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Included features (comma separated codes)</label><input wire:model="form.included_features" class="mt-1 w-full rounded-lg border-slate-300" placeholder="variations,cost.tracking"></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Eligible plans (comma separated codes, blank = all)</label><input wire:model="form.eligible_plans" class="mt-1 w-full rounded-lg border-slate-300" placeholder="monthly-professional,one-time"></div>
                    <div class="flex flex-wrap items-center gap-5 md:col-span-3">
                        <label><input type="checkbox" wire:model="form.is_active"> Active</label>
                    </div>
                    @if($errors->any())<div class="md:col-span-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                </div>
                <div class="boq-modal-foot"><button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button><button class="boq-btn-primary">Save Version</button></div>
            </form>
        </div>
    </div>
    @endif

    <div x-show="confirmDelete" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmDelete=false">
        <div class="boq-modal boq-modal-sm" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">Delete version?</h2><button @click="confirmDelete=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">Delete <strong x-text="deleteName"></strong>? Versions still referenced by active subscriptions are deactivated instead.</div>
            <div class="boq-modal-foot"><button @click="confirmDelete=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.destroy(deleteId); confirmDelete=false" class="boq-btn-danger">Delete</button></div>
        </div>
    </div>
</div>