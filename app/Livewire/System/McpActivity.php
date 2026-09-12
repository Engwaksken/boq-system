<?php

namespace App\Livewire\System;

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class McpActivity extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function render()
    {
        $logs = AuditLog::query()
            ->where('organisation_id', auth()->user()->organisation_id)
            ->where('action', 'like', 'mcp.%')
            ->with('user')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q->where('action', 'like', '%'.$this->search.'%')->orWhere('reference', 'like', '%'.$this->search.'%')))
            ->when($this->status !== '', fn ($query) => $query->where('new_value->status', $this->status))
            ->latest()
            ->paginate(25);

        return view('livewire.system.mcp-activity', ['logs' => $logs]);
    }
}
