<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RolesManager extends Component
{
    public bool $showForm=false;
    public ?int $editingId=null;
    public string $name='';
    public string $slug='';
    public string $description='';
    public array $permissionIds=[];

    public function create(): void { $this->resetForm(); $this->showForm=true; }
    public function edit(int $id): void
    {
        $role=Role::with('permissions')->findOrFail($id);
        $this->editingId=$id; $this->name=$role->name; $this->slug=$role->slug; $this->description=(string)$role->description;
        $this->permissionIds=$role->permissions->pluck('id')->map(fn($id)=>(string)$id)->all(); $this->showForm=true;
    }
    public function save(): void
    {
        $data=$this->validate([
            'name'=>['required','string','max:255'], 'slug'=>['nullable','string','max:100','unique:roles,slug,'.($this->editingId ?? 'NULL')],
            'description'=>['nullable','string'], 'permissionIds'=>['array'], 'permissionIds.*'=>['exists:permissions,id'],
        ]);
        $slug=$data['slug'] ?: Str::slug($data['name']);
        $role=Role::updateOrCreate(['id'=>$this->editingId],['name'=>$data['name'],'slug'=>$slug,'description'=>$data['description'],'is_system'=>false]);
        $role->permissions()->sync(array_map('intval',$data['permissionIds']));
        session()->flash('message','Role saved successfully.'); $this->cancel();
    }
    public function delete(int $id): void
    {
        $role=Role::findOrFail($id); abort_if($role->is_system,422,'System roles cannot be deleted.'); $role->delete(); session()->flash('message','Role deleted.');
    }
    public function cancel(): void { $this->showForm=false; $this->resetForm(); }
    private function resetForm(): void { $this->editingId=null; $this->name=''; $this->slug=''; $this->description=''; $this->permissionIds=[]; }
    public function render() { return view('livewire.admin.roles-manager',['roles'=>Role::withCount('users')->with('permissions')->orderBy('name')->get(),'permissions'=>Permission::orderBy('module')->orderBy('name')->get()->groupBy(fn($p)=>$p->module ?: 'General')]); }
}
