<?php

namespace App\Livewire\Plans;

use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public $plans;

    public function mount(): void
    {
        $this->plans = Plan::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with('features')
            ->orderBy('display_order')
            ->get();
    }

    public function render()
    {
        return view('livewire.plans.index');
    }
}