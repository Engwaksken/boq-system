@php
    $canCreate = auth()->user()->hasPermission('projects.create');
    $canEdit = auth()->user()->hasPermission('projects.edit');
    $sortOptions = [
        'created_at' => __('Created'),
        'name' => __('Name'),
        'code' => __('Code'),
        'client' => __('Client'),
        'location' => __('Location'),
        'contract_value' => __('Value'),
        'status' => __('Status'),
        'start_date' => __('Start Date'),
    ];
@endphp

<div class="boq-page-stack">

    <x-ui.page-header
        :title="__('Projects')"
        icon="fa-folder-open"
        :subtitle="__('Manage your construction projects.')"
    >
        @if($canCreate)
            <x-slot:actions>
                <x-ui.button icon="fa-plus" :href="route('projects.create')">{{ __('New Project') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.flash />

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Total Projects')" :value="\App\Support\Format::number($stats['total_projects'] ?? 0, 0)" icon="fa-folder-open" color="green" />
        <x-stat-card :label="__('Active Projects')" :value="\App\Support\Format::number($stats['active_projects'] ?? 0, 0)" icon="fa-diagram-project" color="blue" />
        <x-stat-card :label="__('Total BOQs')" :value="\App\Support\Format::number($stats['total_boqs'] ?? 0, 0)" icon="fa-file-invoice-dollar" color="amber" />
        <x-stat-card
            :label="__('Total Value')"
            :value="\App\Support\Regional::currency().' '.\App\Support\Format::compact($stats['total_value'] ?? 0)"
            :hint="__('Sum of contract values')"
            icon="fa-money-bill-wave"
            color="purple"
        />
    </div>

    <div class="boq-panel">

        <div class="boq-toolbar border-b border-slate-200">
            <x-ui.field :label="__('Search Projects')" for="project-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input
                        id="project-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="{{ __('Search name, code, client or location...') }}"
                    >
                </div>
            </x-ui.field>

            <x-ui.field :label="__('Sort By')" for="project-sort" class="w-full sm:w-44">
                <select id="project-sort" class="boq-field" wire:change="sortBy($event.target.value)">
                    @foreach($sortOptions as $field => $label)
                        <option value="{{ $field }}" @selected($sortBy === $field)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field :label="__('Rows')" for="project-rows" class="w-full sm:w-24">
                <select id="project-rows" wire:model.live="perPage" class="boq-field">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected projects?') }}" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>

        <div class="boq-loading-bar" wire:loading.delay wire:target="search, perPage, sortBy, gotoPage, nextPage, previousPage"></div>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$projects->pluck('id')" :selected="$selected" /></th>
                    <x-ui.sort-header field="name" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Name') }}</x-ui.sort-header>
                    <x-ui.sort-header field="client" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Client') }}</x-ui.sort-header>
                    <x-ui.sort-header field="location" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Location') }}</x-ui.sort-header>
                    <x-ui.sort-header field="contract_value" :sort-by="$sortBy" :sort-dir="$sortDir" class="text-right">{{ __('Contract Value') }}</x-ui.sort-header>
                    <x-ui.sort-header field="status" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Status') }}</x-ui.sort-header>
                    <th>{{ __('Remaining') }}</th>
                    <th>{{ __('Progress') }}</th>
                    <x-ui.sort-header field="start_date" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Start Date') }}</x-ui.sort-header>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse($projects as $project)
                    <tr wire:key="project-{{ $project->id }}" @class(['is-selected' => in_array((string) $project->id, array_map('strval', $selected), true)])>
                        <td class="boq-check-col"><x-select-row :id="$project->id" /></td>

                        <td>
                            <a href="{{ route('projects.show', $project->id) }}" class="boq-table-link">{{ $project->name }}</a>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                @if($project->code)
                                    <span class="boq-code">{{ $project->code }}</span>
                                @endif
                                <span class="boq-table-meta mt-0">{{ trans_choice(':count BOQ|:count BOQs', $project->boqs_count ?? 0, ['count' => $project->boqs_count ?? 0]) }}</span>
                            </div>
                        </td>

                        <td>
                            @if($project->client)
                                <span class="boq-cell-with-icon"><i class="fas fa-user-tie" aria-hidden="true"></i> {{ $project->client }}</span>
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>

                        <td>
                            @if($project->location)
                                <span class="boq-cell-with-icon"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $project->location }}</span>
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>

                        <td class="is-numeric">
                            <span class="font-semibold text-slate-900">
                                <x-money :amount="$project->contract_value ?? 0" :currency="$project->currency ?? \App\Support\Regional::currency()" />
                            </span>
                            <x-boq-totals compact :totals="$totals[$project->id] ?? []" :currency="$project->currency" />
                        </td>

                        <td><x-ui.status :status="$project->status" /></td>

                        <td><x-project-deadline :project="$project" /></td>

                        <td class="w-44"><x-project-progress :project="$project" /></td>

                        <td class="whitespace-nowrap">
                            @if($project->start_date)
                                <x-date :value="$project->start_date" />
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>

                        <td class="text-right">
                            <div class="boq-table-actions">
                                <a href="{{ route('projects.show', $project->id) }}" class="boq-icon-btn" title="{{ __('View Project') }}" aria-label="{{ __('View Project') }}">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </a>

                                @if($canEdit)
                                    <a href="{{ route('projects.edit', $project->id) }}" class="boq-icon-btn" title="{{ __('Edit Project') }}" aria-label="{{ __('Edit Project') }}">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </a>
                                @endif

                                <button
                                    type="button"
                                    wire:click="delete({{ $project->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this project?') }}"
                                    class="boq-icon-btn boq-icon-danger"
                                    title="{{ __('Delete Project') }}"
                                    aria-label="{{ __('Delete Project') }}"
                                >
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p-0">
                            @if($search !== '')
                                <x-ui.empty-state
                                    icon="fa-magnifying-glass"
                                    :title="__('No projects found.')"
                                    :description="__('Try a different search term.')"
                                >
                                    <x-ui.button variant="secondary" size="sm" wire:click="$set('search', '')">{{ __('Clear search') }}</x-ui.button>
                                </x-ui.empty-state>
                            @else
                                <x-ui.empty-state
                                    icon="fa-folder-open"
                                    :title="__('No projects yet.')"
                                    :description="__('Create a project to organise your BOQs, pricing and reports.')"
                                >
                                    @if($canCreate)
                                        <x-ui.button icon="fa-plus" :href="route('projects.create')">{{ __('Create your first project') }}</x-ui.button>
                                    @endif
                                </x-ui.empty-state>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($projects->hasPages())
            <div class="boq-pagination">
                {{ $projects->links() }}
            </div>
        @endif
    </div>
</div>
