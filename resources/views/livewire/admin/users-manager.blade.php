
<div class="space-y-5" x-data="{ showCreate:false, confirmStatus:false, statusId:null, statusName:'', statusAction:'', confirmRole:false, roleUserId:null, roleId:null, roleName:'', roleUserName:'' }">
    <div><h1 class="text-2xl font-bold">Users</h1><p class="text-sm text-slate-500">Manage account access, roles and subscription links.</p></div>
    @include('livewire.admin._tabs')
    @if(session('message'))<div class="boq-flash">{{session('message')}}</div>@endif

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" placeholder="Search name or email" class="min-w-64 flex-1 rounded-lg border-slate-300 text-sm">
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-sm"><option value="all">All users</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
        <button @click="showCreate=true" class="boq-btn-primary">Add user</button>
    </div>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr>@foreach(['User','Organisation','Roles','Subscription','Status','Actions'] as $h)<th class="px-4 py-3 text-left">{{$h}}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach($users as $user)
            <tr>
                <td class="px-4 py-3"><div class="font-semibold">{{$user->name}}</div><div class="text-xs text-slate-500">{{$user->email}}</div></td>
                <td class="px-4 py-3 text-sm">{{$user->organisation?->name ?? '—'}}</td>
                <td class="px-4 py-3"><div class="flex flex-wrap gap-1">@foreach($user->roles as $role)<button @click="roleUserId={{$user->id}}; roleId={{$role->id}}; roleName=@js($role->name); roleUserName=@js($user->name); confirmRole=true" class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">{{$role->name}} ×</button>@endforeach</div><select wire:change="assignRole({{$user->id}}, $event.target.value)" class="mt-2 rounded border-slate-300 text-xs"><option value="">Add role...</option>@foreach($roles as $role)<option value="{{$role->id}}">{{$role->name}}</option>@endforeach</select></td>
                <td class="px-4 py-3 text-sm">@php($sub=$user->subscriptions->sortByDesc('created_at')->first()){{$sub?->plan?->name ?? 'None'}} @if($sub)<span class="text-xs text-slate-500">({{$sub->status}})</span>@endif</td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{$user->is_active?'bg-emerald-100 text-emerald-700':'bg-red-100 text-red-700'}}">{{$user->is_active?'Active':'Inactive'}}</span></td>
                <td class="px-4 py-3"><button @click="statusId={{$user->id}}; statusName=@js($user->name); statusAction=@js($user->is_active?'Disable':'Enable'); confirmStatus=true" class="text-sm font-semibold {{$user->is_active?'text-red-600':'text-emerald-700'}}">{{$user->is_active?'Disable':'Enable'}}</button></td>
            </tr>
            @endforeach
            </tbody>
        </table>
        <div class="p-4">{{$users->links()}}</div>
    </div>

    <div x-show="showCreate" x-cloak class="boq-modal-backdrop">
        <div class="boq-modal max-w-lg" @click.stop>
            <form wire:submit="createUser">
                <div class="boq-modal-head"><h2 class="text-lg font-bold">Add user</h2><button type="button" @click="showCreate=false" class="text-2xl text-slate-400">&times;</button></div>
                <div class="boq-modal-body space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Full name</label>
                        <input wire:model="newName" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="e.g. Jane Doe">
                        @error('newName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Email</label>
                        <input wire:model="newEmail" type="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="name@company.com">
                        @error('newEmail') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Temporary password</label>
                        <input wire:model="newPassword" type="password" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="min. 8 characters">
                        @error('newPassword') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Phone (optional)</label>
                        <input wire:model="newPhone" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="+256...">
                        @error('newPhone') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700">Organisation</label>
                            <select wire:model="newOrganisationId" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Select...</option>
                                @foreach($organisations as $org)<option value="{{$org->id}}">{{$org->name}}</option>@endforeach
                            </select>
                            @error('newOrganisationId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700">Role</label>
                            <select wire:model="newRoleId" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Select...</option>
                                @foreach($roles as $role)<option value="{{$role->id}}">{{$role->name}}</option>@endforeach
                            </select>
                            @error('newRoleId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="boq-modal-foot">
                    <button type="button" @click="showCreate=false" class="boq-btn-secondary">Cancel</button>
                    <button type="submit" class="boq-btn-primary" wire:loading.attr="disabled" wire:target="createUser">
                        <span wire:loading.remove wire:target="createUser">Create user</span>
                        <span wire:loading wire:target="createUser">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="confirmStatus" x-cloak class="boq-modal-backdrop">
        <div class="boq-modal max-w-md" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold"><span x-text="statusAction"></span> user?</h2><button @click="confirmStatus=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600"><span x-text="statusAction"></span> access for <strong x-text="statusName"></strong>?</div>
            <div class="boq-modal-foot"><button @click="confirmStatus=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.toggleActive(statusId); confirmStatus=false" class="boq-btn-primary">Confirm</button></div>
        </div>
    </div>
    <div x-show="confirmRole" x-cloak class="boq-modal-backdrop">
        <div class="boq-modal max-w-md" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">Remove role?</h2><button @click="confirmRole=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">Remove <strong x-text="roleName"></strong> from <strong x-text="roleUserName"></strong>?</div>
            <div class="boq-modal-foot"><button @click="confirmRole=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.removeRole(roleUserId, roleId); confirmRole=false" class="boq-btn-danger">Remove</button></div>
        </div>
    </div>
</div>
