<?php

namespace App\Livewire\Components;

use Livewire\Component;

class Modal extends Component
{
    public bool $isOpen = false;
    public string $title = '';
    public string $size = 'md';

    protected $listeners = [
        'openModal',
        'closeModal',
    ];

    public function openModal(array $data = []): void
    {
        $this->title = $data['title'] ?? '';
        $this->size = $data['size'] ?? 'md';
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetValidation();
        $this->dispatch('modalClosed');
    }

    public function render()
    {
        return view('livewire.components.modal');
    }
}