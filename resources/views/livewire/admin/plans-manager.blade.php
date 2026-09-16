
<div class="space-y-5" x-data="{ confirmArchive: false, archiveId: null, archiveName: '' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">Subscription Plans</h1><p class="text-sm text-slate-500">Create and manage pricing, limits and trial eligibility.</p></div>
        <button wire:click="create" class="boq-btn-primary">+ Add Plan</button>
    </div>
    @include('livewire.admin._tabs')
    @if(session('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="flex gap-3">
        <input wire:model.live.debounce.300ms="search" placeholder="Search plans..." class="w-full rounded-lg border-slate-300 text-sm md:w-96">
    </div>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr>@foreach(['Plan','Price','Duration','Limits','Status','Actions'] as $h)<th class="px-4 py-3 text-left">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($plans as $plan)
                <tr>
                    <td class="px-4 py-3"><div class="font-semibold">{{ $plan->name }}</div><div class="text-xs text-slate-500">{{ $plan->code }}</div></td>
                    <td class="px-4 py-3">{{ $plan->currency }} {{ number_format((float)$plan->price,2) }}</td>
                    <td class="px-4 py-3">{{ $plan->duration_days ?: '—' }} days</td>
                    <td class="px-4 py-3 text-sm">{{ $plan->max_projects ?? '∞' }} projects · {{ $plan->max_boqs ?? '∞' }} BOQs · {{ $plan->max_ai_credits ?? '∞' }} AI</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $plan->is_active ? 'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-600' }}">{{ $plan->is_active?'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button wire:click="edit({{ $plan->id }})" class="mr-3 text-sm font-semibold text-emerald-700">Edit</button>
                        <button wire:click="toggleActive({{ $plan->id }})" class="mr-3 text-sm text-slate-600">{{ $plan->is_active?'Deactivate':'Activate' }}</button>
                        @unless($plan->is_archived)
                        <button @click="archiveId={{$plan->id}}; archiveName=@js($plan->name); confirmArchive=true" class="text-sm font-semibold text-red-600">Archive</button>
                        @endunless
                    </td>
                </tr>
            @empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No plans found.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $plans->links() }}</div>
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="plan-form-modal" x-data @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head">
                <div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Plan' : 'Create Plan' }}</h2><p class="text-xs text-slate-500">Changes are saved without leaving this page.</p></div>
                <button type="button" wire:click="cancel" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <form wire:submit="save">
                <div class="boq-modal-body grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div><label class="text-sm font-medium">Name</label><input wire:model="form.name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Code</label><input wire:model="form.code" class="mt-1 w-full rounded-lg border-slate-300" placeholder="auto-generated"></div>
                    <div><label class="text-sm font-medium">Type</label><select wire:model="form.type" class="mt-1 w-full rounded-lg border-slate-300"><option value="monthly">Monthly</option><option value="three_month">3 Months</option><option value="six_month">6 Months</option><option value="annual">Annual</option><option value="one_time">One Time</option><option value="lifetime">Lifetime</option></select></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Description</label><textarea wire:model="form.description" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                    @foreach(['price'=>'Price','currency'=>'Currency','duration_days'=>'Duration Days','max_users'=>'Max Users','max_projects'=>'Max Projects','max_boqs'=>'Max BOQs','max_ai_credits'=>'AI Credits','max_ocr_pages'=>'OCR Pages','max_translations'=>'Translations','grace_period_days'=>'Grace Days','display_order'=>'Display Order'] as $field=>$label)
                    <div><label class="text-sm font-medium">{{ $label }}</label><input wire:model="form.{{ $field }}" class="mt-1 w-full rounded-lg border-slate-300" type="{{ $field==='currency' ? 'text' : 'number' }}"></div>
                    @endforeach
                    <div><label class="text-sm font-medium">Storage bytes</label><input wire:model="form.max_storage_bytes" type="number" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="flex flex-wrap items-center gap-5 md:col-span-3">
                        <label><input type="checkbox" wire:model="form.is_active"> Active</label>
                        <label><input type="checkbox" wire:model="form.has_trial"> Trial enabled</label>
                        <label><input type="checkbox" wire:model="form.auto_renewal"> Auto renewal</label>
                        <label class="text-sm">Trial days <input wire:model="form.trial_days" type="number" class="ml-2 w-24 rounded-lg border-slate-300"></label>
                    </div>
                    @if($errors->any())<div class="md:col-span-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                </div>
                <div class="boq-modal-foot"><button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button><button class="boq-btn-primary">Save Plan</button></div>
            </form>
        </div>
    </div>
    @endif

    <div x-show="confirmArchive" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmArchive=false">
        <div class="boq-modal max-w-md" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">Archive plan?</h2><button @click="confirmArchive=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">Archive <strong x-text="archiveName"></strong>? Existing subscriptions will remain and no BOQ data will be deleted.</div>
            <div class="boq-modal-foot"><button @click="confirmArchive=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.archive(archiveId); confirmArchive=false" class="boq-btn-danger">Archive</button></div>
        </div>
    </div>
</div>
