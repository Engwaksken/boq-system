<?php

namespace App\Livewire\Boqs;

use App\Models\Boq;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Boq $boq;

    public string $pdfUrl = '';

    public function mount(Boq $boq): void
    {
        $user = auth()->user();

        abort_unless(
            $boq->project->user_id === $user->id || $boq->organisation_id === $user->organisation_id,
            403
        );

        $this->boq = $boq->load(['items' => fn ($q) => $q->orderBy('id')]);
        $this->pdfUrl = route('boqs.pdf', $boq);
    }

    public function render()
    {
        return view('livewire.boqs.show');
    }
}