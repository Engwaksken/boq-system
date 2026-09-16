<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
                <i class="fas fa-folder-open text-[#05645b]"></i>
                Projects
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Manage your construction projects.
            </p>

        </div>

        <a
            href="{{ url('/projects/create') }}"
            class="boq-btn-primary"
        >
            <i class="fas fa-plus"></i>
            New Project
        </a>

    </div>

    {{-- Statistics --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

        <div class="boq-stat-card boq-stat-green">

            <div>
                <p class="boq-stat-label">
                    Total Projects
                </p>

                <p class="boq-stat-value">
                    {{ $stats['total_projects'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-folder-open"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-blue">

            <div>
                <p class="boq-stat-label">
                    Active Projects
                </p>

                <p class="boq-stat-value">
                    {{ $stats['active_projects'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-circle-check"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-amber">

            <div>
                <p class="boq-stat-label">
                    Total BOQs
                </p>

                <p class="boq-stat-value">
                    {{ $stats['total_boqs'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-purple">

            <div>
                <p class="boq-stat-label">
                    Total Value
                </p>

                <p class="boq-stat-value">
                    UGX
                    {{ number_format(
                        ($stats['total_value'] ?? 0)
                        / 1000000,
                        1
                    ) }}M
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </span>

        </div>

    </div>

    {{-- Filters --}}
    <div class="boq-panel p-4">

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(280px,1fr)_130px_auto] xl:items-end">

            <div>

                <label class="boq-field-label">
                    Search Projects
                </label>

                <div class="boq-input-icon-wrap">

                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="Search name, code, client or location..."
                    >

                </div>

            </div>

            <div>

                <label class="boq-field-label">
                    Rows
                </label>

                <select
                    wire:model.live="perPage"
                    class="boq-field"
                >

                    @foreach($perPageOptions as $option)

                        <option value="{{ $option }}">
                            {{ $option }}
                        </option>

                    @endforeach

                </select>

            </div>

            <div>

                <label class="boq-field-label">
                    Sort
                </label>

                <div class="flex flex-wrap gap-2">

                    @foreach([
                        'name' => 'Name',
                        'client' => 'Client',
                        'contract_value' => 'Value',
                        'status' => 'Status'
                    ] as $field => $label)

                        <button
                            type="button"
                            wire:click="sortBy('{{ $field }}')"
                            class="inline-flex h-10 items-center rounded-lg px-3 text-xs font-semibold
                                {{ $sortBy === $field
                                    ? 'bg-[#05645b] text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }}"
                        >

                            {{ $label }}

                            @if($sortBy === $field)

                                <i class="fas {{
                                    $sortDir === 'asc'
                                        ? 'fa-arrow-up'
                                        : 'fa-arrow-down'
                                }} ml-2"></i>

                            @endif

                        </button>

                    @endforeach

                </div>

            </div>

        </div>

    </div>

    {{-- Table --}}
    <div class="boq-panel overflow-hidden">

        <div class="overflow-x-auto">

            <table class="boq-table min-w-full">

                <thead>

                    <tr>

                        <th
                            wire:click="sortBy('name')"
                            class="cursor-pointer"
                        >
                            Name
                        </th>

                        <th
                            wire:click="sortBy('code')"
                            class="cursor-pointer"
                        >
                            Code
                        </th>

                        <th
                            wire:click="sortBy('client')"
                            class="cursor-pointer"
                        >
                            Client
                        </th>

                        <th
                            wire:click="sortBy('location')"
                            class="cursor-pointer"
                        >
                            Location
                        </th>

                        <th
                            wire:click="sortBy('contract_value')"
                            class="cursor-pointer"
                        >
                            Contract Value
                        </th>

                        <th
                            wire:click="sortBy('status')"
                            class="cursor-pointer"
                        >
                            Status
                        </th>

                        <th
                            wire:click="sortBy('start_date')"
                            class="cursor-pointer"
                        >
                            Start Date
                        </th>

                        <th class="text-right">
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($projects as $project)

                        <tr wire:key="project-{{ $project->id }}">

                            <td>

                                <a
                                    href="{{ url('/projects/'.$project->id) }}"
                                    class="font-semibold text-[#05645b] hover:underline"
                                >
                                    {{ $project->name }}
                                </a>

                            </td>

                            <td>
                                {{ $project->code }}
                            </td>

                            <td>
                                {{ $project->client }}
                            </td>

                            <td>
                                {{ $project->location }}
                            </td>

                            <td>

                                {{ $project->currency }}

                                {{ number_format(
                                    (float) $project->contract_value,
                                    2
                                ) }}

                            </td>

                            <td>

                                <span class="boq-badge">

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $project->status
                                        )
                                    ) }}

                                </span>

                            </td>

                            <td>
                                {{ $project->start_date?->format('d M Y')
                                    ?? '—'
                                }}
                            </td>

                            <td class="text-right">

                                <div class="inline-flex items-center gap-2">

                                    <a
                                        href="{{ url('/projects/'.$project->id) }}"
                                        class="boq-icon-btn"
                                        title="View"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <a
                                        href="{{ url('/projects/'.$project->id.'/edit') }}"
                                        class="boq-icon-btn"
                                        title="Edit"
                                    >
                                        <i class="fas fa-pen"></i>
                                    </a>

                                    <button
                                        type="button"
                                        wire:click="delete({{ $project->id }})"
                                        wire:confirm="Are you sure you want to delete this project?"
                                        class="boq-icon-btn text-red-600"
                                        title="Delete"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="8"
                                class="p-10 text-center text-sm text-slate-500"
                            >

                                <i class="fas fa-folder-open mb-2 block text-2xl text-slate-300"></i>

                                No projects found.

                                <div class="mt-4">

                                    <a
                                        href="{{ url('/projects/create') }}"
                                        class="boq-btn-primary"
                                    >
                                        <i class="fas fa-plus"></i>
                                        Create your first project
                                    </a>

                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if($projects->hasPages())

            <div class="border-t border-slate-200 p-4">
                {{ $projects->links() }}
            </div>

        @endif

    </div>

</div>
