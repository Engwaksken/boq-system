
<div class="space-y-5" x-data="{ confirmToggle:false, toggleId:null, toggleName:'', toggleAction:'' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">Payment Gateways</h1><p class="text-sm text-slate-500">Configure direct providers, ioTec and other aggregators.</p></div>
        <button wire:click="create" class="boq-btn-primary">+ Add Gateway</button>
    </div>
    @include('livewire.admin._tabs')
    @if(session()->has('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr><th class="px-4 py-3 text-left">Name</th><th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-left">Driver</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Mode</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($gateways as $gateway)<tr>
                <td class="px-4 py-3 font-semibold">{{$gateway->name}}</td>
                <td class="px-4 py-3 font-mono text-sm text-slate-600">{{$gateway->code}}</td>
                <td class="px-4 py-3 text-sm text-slate-600">{{$driverOptions[$gateway->driver] ?? $gateway->driver}}</td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{$gateway->is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-700'}}">{{$gateway->is_active?'Active':'Inactive'}}</span></td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{$gateway->is_test_mode?'bg-amber-100 text-amber-800':'bg-blue-100 text-blue-800'}}">{{$gateway->is_test_mode?'Test':'Live'}}</span></td>
                <td class="px-4 py-3 text-right"><button wire:click="edit({{$gateway->id}})" class="mr-3 text-sm font-semibold text-emerald-700">Edit</button><button @click="toggleId={{$gateway->id}};toggleName=@js($gateway->name);toggleAction=@js($gateway->is_active?'Deactivate':'Activate');confirmToggle=true" class="text-sm font-semibold text-slate-600">{{$gateway->is_active?'Deactivate':'Activate'}}</button></td>
            </tr>@empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No payment gateways configured.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{$gateways->links()}}</div>
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="gateway-form-modal" @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head"><div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Payment Gateway' : 'Add Payment Gateway' }}</h2><p class="text-xs text-slate-500">Credentials remain encrypted and masked after save.</p></div><button wire:click="cancel" class="text-2xl text-slate-400">&times;</button></div>
            <form wire:submit.prevent="save">
                <div class="boq-modal-body grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="text-sm font-medium">Name</label><input wire:model="form.name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Code</label><input wire:model="form.code" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Driver</label><div class="mt-1 flex gap-2"><select wire:model="form.driver" class="w-full rounded-lg border-slate-300">@foreach($driverOptions as $value=>$label)<option value="{{$value}}">{{$label}}</option>@endforeach</select><button type="button" wire:click="loadDriverTemplate" class="boq-btn-secondary whitespace-nowrap">Load template</button></div></div>
                    <div><label class="text-sm font-medium">Timeout (seconds)</label><input type="number" wire:model="form.payment_timeout_seconds" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="md:col-span-2"><label class="text-sm font-medium">Description</label><textarea wire:model="form.description" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                    <div class="md:col-span-2"><label class="text-sm font-medium">Webhook URL</label><input wire:model="form.webhook_url" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Currencies</label><input wire:model="supportedCurrenciesCsv" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Countries</label><input wire:model="supportedCountriesCsv" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="md:col-span-2"><label class="text-sm font-medium">Payment methods</label><input wire:model="supportedMethodsCsv" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="md:col-span-2"><div class="flex justify-between"><label class="text-sm font-medium">Configuration JSON</label><span class="text-xs text-slate-500">Secrets display as ***stored***</span></div><textarea wire:model="configJson" rows="12" spellcheck="false" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs"></textarea></div>
                    <div class="md:col-span-2 flex gap-6"><label><input type="checkbox" wire:model="form.is_active"> Active</label><label><input type="checkbox" wire:model="form.is_test_mode"> Test mode</label></div>
                    @if($errors->any())<div class="md:col-span-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                </div>
                <div class="boq-modal-foot"><button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button><button class="boq-btn-primary">Save Gateway</button></div>
            </form>
        </div>
    </div>
    @endif

    <div x-show="confirmToggle" x-cloak class="boq-modal-backdrop">
        <div class="boq-modal max-w-md" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold"><span x-text="toggleAction"></span> gateway?</h2><button @click="confirmToggle=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600"><span x-text="toggleAction"></span> <strong x-text="toggleName"></strong>?</div>
            <div class="boq-modal-foot"><button @click="confirmToggle=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.toggleActive(toggleId);confirmToggle=false" class="boq-btn-primary">Confirm</button></div>
        </div>
    </div>
</div>
