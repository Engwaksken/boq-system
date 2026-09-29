<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Categories')"
        icon="fa-list-check"
        :subtitle="__('Project types and BOQ work sections that users choose from. Material categories are managed with market prices.')"
    />

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <x-ui.tabs :label="__('Category type')">
        <x-ui.tab icon="fa-building" :active="$type === 'project'" :count="$counts['project'] ?? 0" wire:click="setType('project')">{{ __('Project types') }}</x-ui.tab>
        <x-ui.tab icon="fa-layer-group" :active="$type === 'work'" :count="$counts['work'] ?? 0" wire:click="setType('work')">{{ __('Work sections') }}</x-ui.tab>
    </x-ui.tabs>

    <div class="boq-panel">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-[1fr_1.5fr_auto_auto] sm:items-end">
            <x-ui.field :label="$editingId ? __('Edit category') : __('New category')" for="category-name" error="name">
                <input id="category-name" type="text" wire:model="name" maxlength="120" required class="boq-field @error('name') has-error @enderror" placeholder="{{ $type === 'project' ? __('e.g. Health Facility') : __('e.g. Roofing') }}">
            </x-ui.field>

            <x-ui.field :label="__('Description')" for="category-description" error="description">
                <input id="category-description" type="text" wire:model="description" maxlength="255" class="boq-field @error('description') has-error @enderror">
            </x-ui.field>

            <label class="inline-flex items-center gap-2 pb-2 text-sm">
                <input type="checkbox" wire:model="isActive" class="rounded border-slate-300">
                {{ __('Active') }}
            </label>

            <div class="flex gap-2 pb-0.5">
                <x-ui.button type="submit" icon="fa-floppy-disk" wire:target="save">{{ $editingId ? __('Save') : __('Add') }}</x-ui.button>
                @if($editingId)
                    <x-ui.button type="button" variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                @endif
            </div>
        </form>
    </div>

    <div class="boq-panel">
        <x-ui.table>
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td><div class="boq-table-title">{{ $category->name }}</div></td>
                        <td class="text-sm text-slate-500">{{ $category->description ?: '—' }}</td>
                        <td>
                            <x-ui.badge :color="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? __('Active') : __('Hidden') }}</x-ui.badge>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" wire:click="move({{ $category->id }}, -1)" class="boq-btn-ghost" title="{{ __('Move up') }}" aria-label="{{ __('Move up') }}"><i class="fas fa-arrow-up" aria-hidden="true"></i></button>
                            <button type="button" wire:click="move({{ $category->id }}, 1)" class="boq-btn-ghost" title="{{ __('Move down') }}" aria-label="{{ __('Move down') }}"><i class="fas fa-arrow-down" aria-hidden="true"></i></button>
                            <button type="button" wire:click="toggle({{ $category->id }})" class="boq-btn-ghost">{{ $category->is_active ? __('Hide') : __('Show') }}</button>
                            <button type="button" wire:click="edit({{ $category->id }})" class="boq-btn-ghost"><i class="fas fa-pen" aria-hidden="true"></i> {{ __('Edit') }}</button>
                            <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="{{ __('Delete this category? Projects keep the text they already have.') }}" class="boq-btn-ghost text-rose-700"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-ui.empty-state icon="fa-list-check" :title="__('No categories yet')" :description="__('Add the first one above.')" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </div>
</div>
