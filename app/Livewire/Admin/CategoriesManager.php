<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Manage the categories users choose from: project types and BOQ work
 * sections (categories table) and material categories with their items
 * (hardware_categories). Open to admins and super admins.
 */
#[Layout('layouts.app')]
class CategoriesManager extends Component
{
    public string $type = Category::TYPE_PROJECT;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    /** Material categories only: one item per line. */
    public string $items = '';

    public const TYPE_MATERIAL = 'material';

    private function authoriseManager(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'super_admin', 'admin']), 403);
    }

    private function isMaterial(): bool
    {
        return $this->type === self::TYPE_MATERIAL;
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function model(): string
    {
        return $this->isMaterial() ? \App\Models\HardwareCategory::class : Category::class;
    }

    public function mount(): void
    {
        $this->authoriseManager();
    }

    public function setType(string $type): void
    {
        if (in_array($type, [...Category::TYPES, self::TYPE_MATERIAL], true)) {
            $this->type = $type;
            $this->cancel();
        }
    }

    public function edit(int $id): void
    {
        $category = $this->model()::findOrFail($id);
        $this->editingId = $category->id;
        $this->type = $this->isMaterial() ? self::TYPE_MATERIAL : $category->type;
        $this->name = $category->name;
        $this->description = (string) $category->description;
        $this->isActive = (bool) $category->is_active;
        $this->items = $this->isMaterial() ? implode("\n", $category->itemNames()) : '';
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'description', 'isActive', 'items']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authoriseManager();

        $this->name = trim($this->name);
        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:120',
                function ($attribute, $value, $fail) {
                    $taken = $this->isMaterial()
                        ? \App\Models\HardwareCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                            ->exists()
                        : Category::where('type', $this->type)
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
            'type' => ['required', Rule::in([...Category::TYPES, self::TYPE_MATERIAL])],
            'items' => ['nullable', 'string', 'max:20000'],
        ]);

        if ($this->isMaterial()) {
            $this->saveMaterial($data);

            return;
        }

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

    private function saveMaterial(array $data): void
    {
        $items = collect(preg_split('/\r\n|\r|\n/', (string) ($data['items'] ?? '')) ?: [])
            ->map(fn ($item) => trim($item))
            ->filter()
            ->unique(fn ($item) => mb_strtolower($item))
            ->values()
            ->all();

        $category = $this->editingId
            ? \App\Models\HardwareCategory::findOrFail($this->editingId)
            : new \App\Models\HardwareCategory([
                'sort_order' => (int) \App\Models\HardwareCategory::max('sort_order') + 10,
                'created_by' => auth()->id(),
            ]);

        $category->forceFill(['slug' => Str::slug($data['name'])]);
        $category->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'is_active' => $data['isActive'],
            'default_items' => $items,
            'updated_by' => auth()->id(),
        ])->save();

        session()->flash('message', __('Category saved.'));
        $this->cancel();
    }

    public function toggle(int $id): void
    {
        $this->authoriseManager();
        $category = $this->model()::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function move(int $id, int $direction): void
    {
        $this->authoriseManager();
        $categories = $this->query()->get()->values();
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
        $this->authoriseManager();
        if ($this->isMaterial()) {
            $category = \App\Models\HardwareCategory::findOrFail($id);
            // Prices keep their category name; a category in use is only hidden.
            $category->hardwarePrices()->exists()
                ? $category->update(['is_active' => false])
                : $category->delete();
        } else {
            Category::whereKey($id)->delete();
        }
        if ($this->editingId === $id) {
            $this->cancel();
        }
        session()->flash('message', __('Category deleted.'));
    }

    private function query()
    {
        return $this->isMaterial()
            ? \App\Models\HardwareCategory::query()->withCount('items')->orderBy('sort_order')->orderBy('name')
            : Category::ofType($this->type);
    }

    public function render()
    {
        $counts = Category::selectRaw('type, COUNT(*) AS total')->groupBy('type')->pluck('total', 'type');
        $counts[self::TYPE_MATERIAL] = \App\Models\HardwareCategory::count();

        return view('livewire.admin.categories-manager', [
            'categories' => $this->query()->get(),
            'counts' => $counts,
        ]);
    }
}
