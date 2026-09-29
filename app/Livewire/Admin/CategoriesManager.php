<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Manage the selectable project types and BOQ work sections. Material
 * categories are managed with the hardware price categories.
 */
#[Layout('layouts.app')]
class CategoriesManager extends Component
{
    public string $type = Category::TYPE_PROJECT;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public function setType(string $type): void
    {
        if (in_array($type, Category::TYPES, true)) {
            $this->type = $type;
            $this->cancel();
        }
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->type = $category->type;
        $this->name = $category->name;
        $this->description = (string) $category->description;
        $this->isActive = $category->is_active;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'description', 'isActive']);
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $this->name = trim($this->name);
        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:120',
                function ($attribute, $value, $fail) {
                    $taken = Category::where('type', $this->type)
                        ->where('slug', Str::slug($value))
                        ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                        ->exists();
                    if ($taken) {
                        $fail(__('This category already exists.'));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'type' => ['required', Rule::in(Category::TYPES)],
        ]);

        $category = $this->editingId ? Category::findOrFail($this->editingId) : new Category([
            'type' => $this->type,
            'sort_order' => (int) Category::where('type', $this->type)->max('sort_order') + 10,
        ]);

        $category->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'is_active' => $data['isActive'],
        ])->save();

        session()->flash('message', __('Category saved.'));
        $this->cancel();
    }

    public function toggle(int $id): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $category = Category::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function move(int $id, int $direction): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $categories = Category::ofType($this->type)->get()->values();
        $index = $categories->search(fn ($c) => $c->id === $id);
        $swap = $index === false ? null : $categories->get($index + ($direction < 0 ? -1 : 1));

        if ($swap) {
            $current = $categories[$index];
            [$a, $b] = [$current->sort_order, $swap->sort_order];
            $current->update(['sort_order' => $a === $b ? $b + ($direction < 0 ? -1 : 1) : $b]);
            $swap->update(['sort_order' => $a]);
        }
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        Category::whereKey($id)->delete();
        if ($this->editingId === $id) {
            $this->cancel();
        }
        session()->flash('message', __('Category deleted.'));
    }

    public function render()
    {
        return view('livewire.admin.categories-manager', [
            'categories' => Category::ofType($this->type)->get(),
            'counts' => Category::selectRaw('type, COUNT(*) AS total')->groupBy('type')->pluck('total', 'type'),
        ]);
    }
}
