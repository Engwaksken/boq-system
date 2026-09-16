<div class="boq-page-stack">

    {{-- =====================================================
         HEADER
    ====================================================== --}}
    <div class="boq-page-header">

        <div>
            <h1 class="boq-page-title">
                <i class="fas fa-folder-open"></i>
                Projects
            </h1>

            <p class="boq-page-subtitle">
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


    {{-- =====================================================
         STATISTICS
         SAME DISPLAY AS PLANS & PRICING
    ====================================================== --}}
    <div class="boq-stats-grid">

        {{-- Total Projects --}}
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


        {{-- Active Projects --}}
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
                <i class="fas fa-diagram-project"></i>
            </span>

        </div>


        {{-- Total BOQs --}}
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


        {{-- Total Value --}}
        <div class="boq-stat-card boq-stat-purple">

            <div>
                <p class="boq-stat-label">
                    Total Value
                </p>

                <p class="boq-stat-value">

                    @php
                        $totalValue = (float) ($stats['total_value'] ?? 0);
                    @endphp

                    @if($totalValue >= 1000000000)

                        UGX
                        {{ number_format(
                            $totalValue / 1000000000,
                            1
                        ) }}B

                    @elseif($totalValue >= 1000000)

                        UGX
                        {{ number_format(
                            $totalValue / 1000000,
                            1
                        ) }}M

                    @elseif($totalValue >= 1000)

                        UGX
                        {{ number_format(
                            $totalValue / 1000,
                            1
                        ) }}K

                    @else

                        UGX
                        {{ number_format(
                            $totalValue,
                            0
                        ) }}

                    @endif

                </p>
            </div>

            <span class="boq-stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </span>

        </div>

    </div>


    {{-- =====================================================
         SEARCH / ROWS / SORT
    ====================================================== --}}
    <div class="boq-panel">

        <div
            style="
                display:grid;
                grid-template-columns:minmax(280px,1fr) 120px minmax(300px,auto);
                gap:.75rem;
                align-items:end;
                padding:1rem;
            "
            class="project-filter-grid"
        >

            {{-- Search --}}
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


            {{-- Rows --}}
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


            {{-- Sort --}}
            <div>

                <label class="boq-field-label">
                    Sort By
                </label>

                <div
                    style="
                        display:flex;
                        flex-wrap:wrap;
                        gap:.4rem;
                    "
                >

                    @foreach([
                        'name' => [
                            'label' => 'Name',
                            'icon' => 'fa-font'
                        ],

                        'code' => [
                            'label' => 'Code',
                            'icon' => 'fa-hashtag'
                        ],

                        'client' => [
                            'label' => 'Client',
                            'icon' => 'fa-user-tie'
                        ],

                        'location' => [
                            'label' => 'Location',
                            'icon' => 'fa-location-dot'
                        ],

                        'contract_value' => [
                            'label' => 'Value',
                            'icon' => 'fa-money-bill-wave'
                        ],

                        'status' => [
                            'label' => 'Status',
                            'icon' => 'fa-circle-check'
                        ],

                        'created_at' => [
                            'label' => 'Created',
                            'icon' => 'fa-calendar'
                        ],

                    ] as $field => $sortOption)

                        <button
                            type="button"
                            wire:click="sortBy('{{ $field }}')"

                            style="
                                display:inline-flex;
                                height:40px;
                                align-items:center;
                                justify-content:center;
                                gap:.35rem;
                                border-radius:.625rem;
                                padding:0 .7rem;
                                font-size:.72rem;
                                font-weight:700;
                                border:1px solid {{ $sortBy === $field ? '#05645b' : '#e2e8f0' }};
                                background:{{ $sortBy === $field ? '#05645b' : '#f8fafc' }};
                                color:{{ $sortBy === $field ? '#ffffff' : '#475569' }};
                                cursor:pointer;
                            "
                        >

                            <i class="fas {{ $sortOption['icon'] }}"></i>

                            {{ $sortOption['label'] }}

                            @if($sortBy === $field)

                                <i class="fas {{
                                    $sortDir === 'asc'
                                        ? 'fa-arrow-up'
                                        : 'fa-arrow-down'
                                }}"></i>

                            @endif

                        </button>

                    @endforeach

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         PROJECTS TABLE
    ====================================================== --}}
    <div class="boq-panel">

        <div class="boq-table-wrapper">

            <table class="boq-table">

                <thead>

                    <tr>

                        {{-- Name --}}
                        <th
                            wire:click="sortBy('name')"
                            style="cursor:pointer;"
                        >

                            <span
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:.3rem;
                                "
                            >
                                Name

                                @if($sortBy === 'name')

                                    <i class="fas {{
                                        $sortDir === 'asc'
                                            ? 'fa-arrow-up'
                                            : 'fa-arrow-down'
                                    }}"></i>

                                @endif
                            </span>

                        </th>


                        {{-- Code --}}
                        <th
                            wire:click="sortBy('code')"
                            style="cursor:pointer;"
                        >

                            <span
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:.3rem;
                                "
                            >
                                Code

                                @if($sortBy === 'code')

                                    <i class="fas {{
                                        $sortDir === 'asc'
                                            ? 'fa-arrow-up'
                                            : 'fa-arrow-down'
                                    }}"></i>

                                @endif

                            </span>

                        </th>


                        {{-- Client --}}
                        <th
                            wire:click="sortBy('client')"
                            style="cursor:pointer;"
                        >
                            Client
                        </th>


                        {{-- Location --}}
                        <th
                            wire:click="sortBy('location')"
                            style="cursor:pointer;"
                        >
                            Location
                        </th>


                        {{-- Contract Value --}}
                        <th
                            wire:click="sortBy('contract_value')"
                            style="cursor:pointer;"
                        >
                            Contract Value
                        </th>


                        {{-- Status --}}
                        <th
                            wire:click="sortBy('status')"
                            style="cursor:pointer;"
                        >
                            Status
                        </th>


                        {{-- Start Date --}}
                        <th
                            wire:click="sortBy('start_date')"
                            style="cursor:pointer;"
                        >
                            Start Date
                        </th>


                        {{-- Actions --}}
                        <th class="text-right">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($projects as $project)

                        <tr wire:key="project-{{ $project->id }}">

                            {{-- Project --}}
                            <td>

                                <a
                                    href="{{ url('/projects/'.$project->id) }}"
                                    style="
                                        color:#05645b;
                                        font-weight:700;
                                        text-decoration:none;
                                    "
                                >
                                    {{ $project->name }}
                                </a>

                            </td>


                            {{-- Code --}}
                            <td>

                                @if($project->code)

                                    <span
                                        style="
                                            display:inline-flex;
                                            border-radius:.4rem;
                                            background:#f1f5f9;
                                            padding:.2rem .45rem;
                                            color:#475569;
                                            font-size:.7rem;
                                            font-weight:700;
                                        "
                                    >
                                        {{ $project->code }}
                                    </span>

                                @else

                                    <span style="color:#94a3b8;">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Client --}}
                            <td>

                                @if($project->client)

                                    <span
                                        style="
                                            display:inline-flex;
                                            align-items:center;
                                            gap:.35rem;
                                        "
                                    >
                                        <i
                                            class="fas fa-user-tie"
                                            style="
                                                color:#94a3b8;
                                                font-size:.7rem;
                                            "
                                        ></i>

                                        {{ $project->client }}

                                    </span>

                                @else

                                    <span style="color:#94a3b8;">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Location --}}
                            <td>

                                @if($project->location)

                                    <span
                                        style="
                                            display:inline-flex;
                                            align-items:center;
                                            gap:.35rem;
                                        "
                                    >

                                        <i
                                            class="fas fa-location-dot"
                                            style="
                                                color:#94a3b8;
                                                font-size:.7rem;
                                            "
                                        ></i>

                                        {{ $project->location }}

                                    </span>

                                @else

                                    <span style="color:#94a3b8;">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Contract Value --}}
                            <td>

                                <strong style="color:#0f172a;">

                                    {{ $project->currency ?? 'UGX' }}

                                    {{ number_format(
                                        (float) ($project->contract_value ?? 0),
                                        0
                                    ) }}

                                </strong>

                            </td>


                            {{-- Status --}}
                            <td>

                                @php
                                    $statusClass = match($project->status) {

                                        'active'
                                            => 'boq-badge-success',

                                        'completed'
                                            => 'boq-badge-info',

                                        'archived'
                                            => '',

                                        'on_hold'
                                            => 'boq-badge-warning',

                                        default
                                            => ''
                                    };
                                @endphp

                                <span class="boq-badge {{ $statusClass }}">

                                    @switch($project->status)

                                        @case('active')
                                            <i class="fas fa-circle-check"></i>
                                            @break

                                        @case('completed')
                                            <i class="fas fa-check-double"></i>
                                            @break

                                        @case('on_hold')
                                            <i class="fas fa-pause"></i>
                                            @break

                                        @case('archived')
                                            <i class="fas fa-box-archive"></i>
                                            @break

                                        @default
                                            <i class="fas fa-circle"></i>

                                    @endswitch

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $project->status ?? 'unknown'
                                        )
                                    ) }}

                                </span>

                            </td>


                            {{-- Start date --}}
                            <td>

                                @if($project->start_date)

                                    <span
                                        style="
                                            display:inline-flex;
                                            align-items:center;
                                            gap:.35rem;
                                        "
                                    >

                                        <i
                                            class="fas fa-calendar-day"
                                            style="
                                                color:#94a3b8;
                                                font-size:.7rem;
                                            "
                                        ></i>

                                        {{ $project->start_date->format('d M Y') }}

                                    </span>

                                @else

                                    <span style="color:#94a3b8;">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Actions --}}
                            <td class="text-right">

                                <div
                                    style="
                                        display:inline-flex;
                                        align-items:center;
                                        gap:.35rem;
                                    "
                                >

                                    {{-- View --}}
                                    <a
                                        href="{{ url('/projects/'.$project->id) }}"
                                        class="boq-icon-btn"
                                        title="View Project"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>


                                    {{-- Edit --}}
                                    <a
                                        href="{{ url('/projects/'.$project->id.'/edit') }}"
                                        class="boq-icon-btn"
                                        title="Edit Project"
                                    >
                                        <i class="fas fa-pen"></i>
                                    </a>


                                    {{-- Delete --}}
                                    <button
                                        type="button"
                                        wire:click="delete({{ $project->id }})"
                                        wire:confirm="Are you sure you want to delete this project?"
                                        class="boq-icon-btn boq-icon-danger"
                                        title="Delete Project"
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
                                class="boq-empty-table"
                            >

                                <i class="fas fa-folder-open"></i>

                                <span>
                                    No projects found.
                                </span>

                                <div style="margin-top:1rem;">

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


        {{-- =================================================
             PAGINATION
        ================================================== --}}
        @if($projects->hasPages())

            <div class="boq-pagination">

                {{ $projects->links() }}

            </div>

        @endif

    </div>

</div>


{{-- =========================================================
     RESPONSIVE FILTER LAYOUT
========================================================= --}}
<style>

    @media (max-width: 1100px) {

        .project-filter-grid {
            grid-template-columns:
                minmax(250px, 1fr)
                110px !important;
        }

        .project-filter-grid > div:last-child {
            grid-column:
                1 / -1;
        }

    }


    @media (max-width: 700px) {

        .project-filter-grid {
            grid-template-columns:
                1fr !important;
        }

        .project-filter-grid > div:last-child {
            grid-column:
                auto;
        }

    }

</style>
