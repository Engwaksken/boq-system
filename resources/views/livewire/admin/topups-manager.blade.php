<div class="space-y-5" x-data="{ confirmArchive: false, archiveId: null, archiveName: '' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">Top-ups &amp; Purchases</h1><p class="text-sm text-slate-500">Feature updates, usage credits and one-off unlocks users can buy.</p></div>
        <button wire:click="create" class="boq-btn-primary">+ Add Top-up</button>
    </div>
    @include('livewire.admin._tabs')
    @if(session('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="flex gap-3">
        <input wire:model.live.debounce.300ms="search" placeholder="Search top-ups..." class="w-full rounded-lg border-slate-300 text-sm md:w-96">
    </div>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr>@foreach(['Top-up','Type','Price','Credits','Availability','Status','Actions'] as $h)<th class="px-4 py-3 text-left">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($topups as $topup)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-semibold">{{ $topup->name }}</div>
                        <div class="text-xs text-slate-500">{{ $topup->code }}</div>
                        @if($topup->release_version)<div class="text-xs text-slate-400">v{{ $topup->release_version }}</div>@endif
                    </td>
                    <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs">{{ str_replace('_', ' ', $topup->type) }}</span></td>
                    <td class="px-4 py-3">{{ $topup->currency }} {{ number_format((float)$topup->price,2) }}</td>
                    <td class="px-4 py-3 text-sm">
                        @if($topup->isUsageTopup())
                            @foreach(($topup->usage_credits ?? []) as $key=>$value)<span class="mr-1 inline-block rounded bg-indigo-50 px-1.5 py-0.5 text-xs">{{ $key }}: {{ $value }}</span>@endforeach
                        @elseif(count($topup->included_features ?? []))
                            <span class="text-xs text-slate-500">{{ count($topup->included_features) }} feature(s)</span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">
                        {{ $topup->duration_days ? $topup->duration_days.' days' : ($topup->is_permanent ? 'Lifetime' : 'Unlimited') }}
                        · {{ $purchaseCounts[$topup->id] ?? 0 }} purchases
                    </td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $topup->is_active ? 'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-600' }}">{{ $topup->is_active ? 'Active':'Inactive' }}</span></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button wire:click="edit({{ $topup->id }})" class="mr-3 text-sm font-semibold text-emerald-700">Edit</button>
                        <button wire:click="toggleActive({{ $topup->id }})" class="mr-3 text-sm text-slate-600">{{ $topup->is_active?'Deactivate':'Activate' }}</button>
                        @unless($topup->is_archived)
                        <button @click="archiveId={{$topup->id}}; archiveName=@js($topup->name); confirmArchive=true" class="text-sm font-semibold text-red-600">Archive</button>
                        @endunless
                    </td>
                </tr>
            @empty<tr><td colspan="7" class="p-8 text-center text-slate-500">No top-ups found.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $topups->links() }}</div>
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="topup-form-modal" x-data @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head">
                <div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Top-up' : 'Create Top-up' }}</h2><p class="text-xs text-slate-500">Changes are saved without leaving this page.</p></div>
                <button type="button" wire:click="cancel" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <form wire:submit="save">
                <div class="boq-modal-body grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div><label class="text-sm font-medium">Name</label><input wire:model="form.name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Code</label><input wire:model="form.code" class="mt-1 w-full rounded-lg border-slate-300" placeholder="auto-generated"></div>
                    <div><label class="text-sm font-medium">Type</label>
                        <select wire:model="form.type" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="feature_unlock">Feature Unlock</option>
                            <option value="version_update">Version Update</option>
                            <option value="bundle">Bundle</option>
                            <option value="ai_credit_topup">AI Credit Top-up</option>
                            <option value="ocr_credit_topup">OCR Credit Top-up</option>
                            <option value="translation_credit_topup">Translation Credit Top-up</option>
                            <option value="storage_topup">Storage Top-up</option>
                            <option value="user_seat_topup">User Seat Top-up</option>
                            <option value="project_limit_topup">Project Limit Top-up</option>
                            <option value="boq_limit_topup">BOQ Limit Top-up</option>
                            <option value="report_export_topup">Report Export Top-up</option>
                        </select>
                    </div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Description</label><textarea wire:model="form.description" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                    @foreach(['price'=>'Price','currency'=>'Currency','duration_days'=>'Duration Days','release_version'=>'Release Version','purchase_limit'=>'Purchase Limit','display_order'=>'Display Order'] as $field=>$label)
                    <div><label class="text-sm font-medium">{{ $label }}</label><input wire:model="form.{{ $field }}" class="mt-1 w-full rounded-lg border-slate-300" type="{{ in_array($field,['duration_days','purchase_limit','display_order'],true) ? 'number' : 'text' }}"></div>
                    @endforeach
                    <div class="md:col-span-3"><label class="text-sm font-medium">Included features (comma separated codes)</label><input wire:model="form.included_features" class="mt-1 w-full rounded-lg border-slate-300" placeholder="variations,cost.tracking"></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Usage credits (JSON, e.g. {"ai_credits":50,"ocr_pages":100})</label><textarea wire:model="form.usage_credits" rows="2" class="mt-1 w-full rounded-lg border-slate-300" placeholder='{"ai_credits":50}'></textarea></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Applicable plans (comma separated codes, blank = all)</label><input wire:model="form.applicable_plans" class="mt-1 w-full rounded-lg border-slate-300" placeholder="monthly-professional,one-time"></div>
                    <div class="flex flex-wrap items-center gap-5 md:col-span-3">
                        <label><input type="checkbox" wire:model="form.is_active"> Active</label>
                        <label><input type="checkbox" wire:model="form.is_permanent"> Permanent (no expiry)</label>
                        <label><input type="checkbox" wire:model="form.requires_confirmation"> Requires payment confirmation</label>
                    </div>
                    @if($errors->any())<div class="md:col-span-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                </div>
                <div class="boq-modal-foot"><button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button><button class="boq-btn-primary">Save Top-up</button></div>
            </form>
        </div>
    </div>
    @endif

    <div x-show="confirmArchive" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmArchive=false">
        <div class="boq-modal boq-modal-sm" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">Archive top-up?</h2><button @click="confirmArchive=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">Archive <strong x-text="archiveName"></strong>? Existing purchases will remain valid until they expire.</div>
            <div class="boq-modal-foot"><button @click="confirmArchive=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.archive(archiveId); confirmArchive=false" class="boq-btn-danger">Archive</button></div>
        </div>
    </div>
</div>