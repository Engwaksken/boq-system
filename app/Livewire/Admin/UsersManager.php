<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UsersManager extends Component
{
    use WithPagination;

    public string $search='';
    public string $status='all';
    public int $perPage=20;

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);
        abort_if($user->id === auth()->id(), 422, 'You cannot disable your own account.');
        $user->update(['is_active' => ! $user->is_active]);
        session()->flash('message', 'User status updated.');
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $user = User::findOrFail($userId);
        $role = Role::findOrFail($roleId);
        $user->roles()->syncWithoutDetaching([$role->id => ['organisation_id' => $user->organisation_id]]);
        session()->flash('message', 'Role assigned successfully.');
    }

    public function removeRole(int $userId, int $roleId): void
    {
        $user = User::findOrFail($userId);
        abort_if($user->id === auth()->id() && Role::whereKey($roleId)->value('slug') === 'super-admin', 422, 'You cannot remove your own Super Admin role.');
        $user->roles()->detach($roleId);
        session()->flash('message', 'Role removed.');
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function render()
    {
        $users = User::query()
            ->when($this->search, fn($q) => $q->where(fn($x) => $x->where('name','like','%'.$this->search.'%')->orWhere('email','like','%'.$this->search.'%')))
            ->when($this->status !== 'all', fn($q) => $q->where('is_active', $this->status === 'active'))
            ->with(['roles','organisation','subscriptions.plan'])
            ->latest()->paginate($this->perPage);
        return view('livewire.admin.users-manager', ['users'=>$users,'roles'=>Role::orderBy('name')->get()]);
    }
}
