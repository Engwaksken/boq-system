
<div class="space-y-5" x-data="{ confirmDelete:false, deleteId:null, deleteName:'' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Roles & Permissions</h1><p class="text-sm text-slate-500">Control access to BOQ, pricing, reports and administration.</p></div>
        <button wire:click="create" class="boq-btn-primary">+ Add Role</button>
    </div>
    @include('livewire.admin._tabs')
    @if(session('message'))<div class="boq-flash">{{session('message')}}</div>@endif

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr><th class="px-4 py-3 text-left">Role</th><th class="px-4 py-3 text-left">Users</th><th class="px-4 py-3 text-left">Permissions</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($roles as $role)<tr>
                <td class="px-4 py-3"><div class="font-semibold">{{$role->name}}</div><div class="text-xs text-slate-500">{{$role->slug}}</div></td>
                <td class="px-4 py-3 text-sm">{{$role->users_count}}</td>
                <td class="px-4 py-3 text-sm text-slate-600">{{$role->permissions->count()}} permissions</td>
                <td class="px-4 py-3">@if($role->is_system)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs">System</span>@else<span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">Custom</span>@endif</td>
                <td class="px-4 py-3 whitespace-nowrap"><button wire:click="edit({{$role->id}})" class="mr-3 text-sm font-semibold text-emerald-700">Edit</button>@unless($role->is_system)<button @click="deleteId={{$role->id}}; deleteName=@js($role->name); confirmDelete=true" class="text-sm font-semibold text-red-600">Delete</button>@endunless</td>
            </tr>@endforeach
            </tbody>
        </table>
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="role-form-modal" @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head"><div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Role' : 'Create Role' }}</h2><p class="text-xs text-slate-500">Configure permissions in this modal.</p></div><button wire:click="cancel" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body grid gap-4 md:grid-cols-2">
                <div><label class="text-sm font-medium">Name</label><input wire:model="name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                <div><label class="text-sm font-medium">Slug</label><input wire:model="slug" class="mt-1 w-full rounded-lg border-slate-300" placeholder="auto if empty"></div>
                <div class="md:col-span-2"><label class="text-sm font-medium">Description</label><textarea wire:model="description" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                <div class="md:col-span-2"><h3 class="mb-2 font-semibold">Permissions</h3><div class="grid gap-3 md:grid-cols-3">@foreach($permissions as $module=>$items)<div class="rounded-xl border border-slate-200 p-3"><div class="mb-2 font-semibold">{{$module}}</div>@foreach($items as $permission)<label class="mb-1 block text-sm"><input type="checkbox" wire:model="permissionIds" value="{{$permission->id}}"> {{$permission->name}}</label>@endforeach</div>@endforeach</div></div>
                @if($errors->any())<div class="md:col-span-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            </div>
            <div class="boq-modal-foot"><button wire:click="cancel" class="boq-btn-secondary">Cancel</button><button wire:click="save" class="boq-btn-primary">Save Role</button></div>
        </div>
    </div>
    @endif

    <div x-show="confirmDelete" x-cloak class="boq-modal-backdrop">
        <div class="boq-modal max-w-md" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">Delete role?</h2><button @click="confirmDelete=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">Delete <strong x-text="deleteName"></strong>? This action cannot be undone.</div>
            <div class="boq-modal-foot"><button @click="confirmDelete=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.delete(deleteId); confirmDelete=false" class="boq-btn-danger">Delete</button></div>
        </div>
    </div>
</div>
