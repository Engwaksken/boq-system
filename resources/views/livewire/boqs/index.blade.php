<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900">
                <i class="fas fa-file-invoice-dollar text-[#05645b]"></i>
                BOQs
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Bill of Quantities for your projects.
            </p>

        </div>

        <a
            href="{{ url('/boqs/create') }}"
            class="boq-btn-primary"
        >
            <i class="fas fa-plus"></i>
            New BOQ
        </a>

    </div>

    {{-- Statistics --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

        <div class="boq-stat-card boq-stat-green">

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

        <div class="boq-stat-card boq-stat-blue">

            <div>
                <p class="boq-stat-label">
                    Uploaded
                </p>

                <p class="boq-stat-value">
                    {{ $stats['uploaded'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-cloud-arrow-up"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-amber">

            <div>
                <p class="boq-stat-label">
                    Under Review
                </p>

                <p class="boq-stat-value">
                    {{ $stats['under_review'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-magnifying-glass-chart"></i>
            </span>

        </div>

        <div class="boq-stat-card boq-stat-purple">

            <div>
                <p class="boq-stat-label">
                    Approved
                </p>

                <p class="boq-stat-value">
                    {{ $stats['approved'] ?? 0 }}
                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-circle-check"></i>
            </span>

        </div>

    </div>

    {{-- Filters --}}
    <div class="boq-panel p-4">

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(280px,1fr)_130px_auto] xl:items-end">

            <div>

                <label class="boq-field-label">
                    Search BOQs
                </label>

                <div class="boq-input-icon-wrap">

                    <i class="fas fa-search boq-input-icon"></i>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        class="boq-field boq-field-with-icon"
                        placeholder="Search BOQ name, project or status..."
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
                        'status' => 'Status',
                        'currency' => 'Currency',
                        'created_at' => 'Created',
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
                            wire:click="sortBy('project.name')"
                            class="cursor-pointer"
                        >
                            Project
                        </th>

                        <th
                            wire:click="sortBy('status')"
                            class="cursor-pointer"
                        >
                            Status
                        </th>

                        <th
                            wire:click="sortBy('currency')"
                            class="cursor-pointer"
                        >
                            Currency
                        </th>

                        <th
                            wire:click="sortBy('version')"
                            class="cursor-pointer"
                        >
                            Version
                        </th>

                        <th
                            wire:click="sortBy('items_count')"
                            class="cursor-pointer"
                        >
                            Items
                        </th>

                        <th
                            wire:click="sortBy('created_at')"
                            class="cursor-pointer"
                        >
                            Created
                        </th>

                        <th class="text-right">
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($boqs as $boq)

                        <tr wire:key="boq-{{ $boq->id }}">

                            <td>

                                <a
                                    href="{{ url('/boqs/'.$boq->id) }}"
                                    class="font-semibold text-[#05645b] hover:underline"
                                >
                                    {{ $boq->name }}
                                </a>

                            </td>

                            <td>
                                {{ $boq->project?->name ?? '—' }}
                            </td>

                            <td>

                                <span class="boq-badge">

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $boq->status
                                        )
                                    ) }}

                                </span>

                            </td>

                            <td>
                                {{ $boq->currency }}
                            </td>

                            <td>
                                {{ $boq->version }}
                            </td>

                            <td>
                                {{ $boq->items_count
                                    ?? $boq->items->count()
                                }}
                            </td>

                            <td>
                                {{ $boq->created_at?->format('d M Y')
                                    ?? '—'
                                }}
                            </td>

                            <td class="text-right">

                                <a
                                    href="{{ url('/boqs/'.$boq->id) }}"
                                    class="boq-icon-btn"
                                    title="View"
                                >
                                    <i class="fas fa-eye"></i>
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="8"
                                class="p-10 text-center text-sm text-slate-500"
                            >

                                <i class="fas fa-file-invoice-dollar mb-2 block text-2xl text-slate-300"></i>

                                No BOQs found.

                                <div class="mt-4">

                                    <a
                                        href="{{ url('/boqs/create') }}"
                                        class="boq-btn-primary"
                                    >
                                        <i class="fas fa-plus"></i>
                                        Upload your first BOQ
                                    </a>

                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if($boqs->hasPages())

            <div class="border-t border-slate-200 p-4">
                {{ $boqs->links() }}
            </div>

        @endif

    </div>

</div>
