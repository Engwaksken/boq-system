<?php

namespace App\Livewire;

use App\Models\Faq;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Faqs extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.faqs', [
            'faqs' => Faq::where('is_active', true)
                ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($search) => $search
                    ->where('question', 'like', '%'.trim($this->search).'%')
                    ->orWhere('answer', 'like', '%'.trim($this->search).'%')))
                ->orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }
}
