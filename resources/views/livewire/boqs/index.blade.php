@php
    $sortOptions = [
        'created_at' => __('Created'),
        'name' => __('Name'),
        'project.name' => __('Project'),
        'status' => __('Status'),
        'currency' => __('Currency'),
        'items_count' => __('Items'),
    ];
@endphp

<div class="boq-page-stack">

    <x-ui.page-header
        :title="__('BOQs')"
        icon="fa-file-invoice-dollar"
        :subtitle="__('Bill of Quantities for your projects.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-plus" :href="route('boqs.create')">{{ __('New BOQ') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash />

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Total BOQs')" :value="\App\Support\Format::number($stats['total_boqs'] ?? 0, 0)" icon="fa-file-invoice-dollar" color="green" />
        <x-stat-card :label="__('Uploaded')" :value="\App\Support\Format::number($stats['uploaded'] ?? 0, 0)" icon="fa-cloud-arrow-up" color="blue" />
        <x-stat-card :label="__('Under Review')" :value="\App\Support\Format::number($stats['under_review'] ?? 0, 0)" icon="fa-magnifying-glass-chart" color="amber" />
        <x-stat-card :label="__('Approved')" :value="\App\Support\Format::number($stats['approved'] ?? 0, 0)" icon="fa-circle-check" color="purple" />
    </div>

    <div class="boq-panel">

        <div class="boq-toolbar border-b border-slate-200">
            <x-ui.field :label="__('Search BOQs')" for="boq-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input
                        id="boq-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="{{ __('Search by BOQ name...') }}"
                    >
                </div>
            </x-ui.field>

            @if($projects->count() > 1)
                <x-ui.field :label="__('Project')" for="boq-project-filter" class="w-full sm:w-56">
                    <select id="boq-project-filter" wire:model.live="projectId" class="boq-field">
                        <option value="">{{ __('All projects') }}</option>
                        @foreach($projects as $projectOption)
                            <option value="{{ $projectOption->id }}">{{ $projectOption->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            @endif

            <x-ui.field :label="__('Sort By')" for="boq-sort" class="w-full sm:w-40">
                <select id="boq-sort" class="boq-field" wire:change="sortBy($event.target.value)">
                    @foreach($sortOptions as $field => $label)
                        <option value="{{ $field }}" @selected($sortBy === $field)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field :label="__('Rows')" for="boq-rows" class="w-full sm:w-24">
                <select id="boq-rows" wire:model.live="perPage" class="boq-field">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected BOQs?') }}" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>

        <div class="boq-loading-bar" wire:loading.delay wire:target="search, perPage, projectId, sortBy, gotoPage, nextPage, previousPage"></div>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$boqs->pluck('id')" :selected="$selected" /></th>
                    <x-ui.sort-header field="name" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Name') }}</x-ui.sort-header>
                    <x-ui.sort-header field="project.name" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Project') }}</x-ui.sort-header>
                    <x-ui.sort-header field="status" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Status') }}</x-ui.sort-header>
                    <x-ui.sort-header field="items_count" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Items') }}</x-ui.sort-header>
                    <th>{{ __('Totals') }}</th>
                    <x-ui.sort-header field="created_at" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Created') }}</x-ui.sort-header>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse($boqs as $boq)
                    <tr wire:key="boq-{{ $boq->id }}">
                        <td class="boq-check-col">
                            @can('delete', $boq)
                                <x-select-row :id="$boq->id" />
                            @endcan
                        </td>

                        <td>
                            <a href="{{ route('boqs.show', $boq->id) }}" class="boq-table-link">{{ $boq->name }}</a>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span class="boq-version-badge"><i class="fas fa-code-branch" aria-hidden="true"></i> v{{ $boq->version ?? 1 }}</span>
                                @if($boq->currency)
                                    <span class="boq-currency-badge">{{ $boq->currency }}</span>
                                @endif
                            </div>
                        </td>

                        <td>
                            @if($boq->project)
                                <a href="{{ route('projects.show', $boq->project->id) }}" class="boq-cell-with-icon hover:text-brand-700">
                                    <i class="fas fa-folder-open" aria-hidden="true"></i> {{ $boq->project->name }}
                                </a>
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>

                        <td><x-ui.status :status="$boq->status" /></td>

                        <td>
                            <span class="boq-item-count"><i class="fas fa-list-ul" aria-hidden="true"></i> {{ \App\Support\Format::number($boq->items_count ?? 0, 0) }}</span>
                        </td>

                        <td>
                            @if(($totals[$boq->id]['items'] ?? 0) > 0)
                                <x-boq-totals compact class="mt-0" :totals="$totals[$boq->id]" :currency="$boq->currency" />
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap">
                            @if($boq->created_at)
                                <x-date :value="$boq->created_at" />
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>

                        <td class="text-right">
                            <div class="boq-table-actions">
                                <a href="{{ route('boqs.show', $boq->id) }}" class="boq-icon-btn" title="{{ __('View BOQ') }}" aria-label="{{ __('View BOQ') }}">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </a>

                                @can('update', $boq)
                                    <button type="button" wire:click="editBoq({{ $boq->id }})" class="boq-icon-btn" title="{{ __('Edit BOQ') }}" aria-label="{{ __('Edit BOQ') }}">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </button>
                                @endcan

                                @can('delete', $boq)
                                    <button type="button" wire:click="confirmDelete({{ $boq->id }})" class="boq-icon-btn boq-icon-danger" title="{{ __('Delete BOQ') }}" aria-label="{{ __('Delete BOQ') }}">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-0">
                            @if($search !== '' || $projectId)
                                <x-ui.empty-state
                                    icon="fa-magnifying-glass"
                                    :title="__('No BOQs found.')"
                                    :description="__('Try a different search term or project.')"
                                >
                                    <x-ui.button variant="secondary" size="sm" wire:click="$set('search', '')">{{ __('Clear search') }}</x-ui.button>
                                </x-ui.empty-state>
                            @else
                                <x-ui.empty-state
                                    icon="fa-file-invoice-dollar"
                                    :title="__('No BOQs yet.')"
                                    :description="__('Upload a bill of quantities to price it against current market rates.')"
                                >
                                    <x-ui.button icon="fa-plus" :href="route('boqs.create')">{{ __('Upload your first BOQ') }}</x-ui.button>
                                </x-ui.empty-state>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($boqs->hasPages())
            <div class="boq-pagination">
                {{ $boqs->links() }}
            </div>
        @endif
    </div>

    @if($editingBoqId)
        <x-ui.modal wire:key="boq-edit-modal" id="boq-edit" :title="__('Edit BOQ')" icon="fa-pen" size="sm" close="closeEdit" submit="saveBoq">
            <div class="space-y-4">
                <x-ui.field :label="__('BOQ Name')" for="boq-edit-name" error="editName" required>
                    <input id="boq-edit-name" type="text" wire:model="editName" class="boq-field @error('editName') has-error @enderror" maxlength="255" placeholder="{{ __('e.g. Main building - Phase 1') }}">
                </x-ui.field>

                <x-ui.field :label="__('Project')" for="boq-edit-project" error="editProjectId" required>
                    <select id="boq-edit-project" wire:model="editProjectId" class="boq-field @error('editProjectId') has-error @enderror">
                        @foreach($projects as $projectOption)
                            <option value="{{ $projectOption->id }}">{{ $projectOption->name }}{{ $projectOption->code ? ' ('.$projectOption->code.')' : '' }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <p class="boq-field-help">{{ __('To change item rates, open the BOQ and review its items.') }}</p>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeEdit">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="saveBoq">{{ __('Save Changes') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if($deletingBoqId)
        <x-ui.modal wire:key="boq-delete-modal" id="boq-delete" :title="__('Delete BOQ?')" icon="fa-trash" size="sm" close="cancelDelete">
            <p class="boq-modal-message">{{ __('The BOQ and its items will be removed from your list. An administrator can restore it if needed.') }}</p>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancelDelete">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" icon="fa-trash" wire:click="deleteBoq" loading="deleteBoq">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
