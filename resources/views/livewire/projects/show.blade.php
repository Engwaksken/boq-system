@php
    $dash = '—';
    $details = [
        __('Project Code') => $project->code,
        __('Client') => $project->client,
        __('Contractor') => $project->contractor,
        __('Consultant') => $project->consultant,
        __('Quantity Surveyor') => $project->quantity_surveyor,
        __('Project Manager') => $project->project_manager,
        __('Site Engineer') => $project->site_engineer,
        __('Funding Organisation') => $project->funding_organisation,
        __('Country') => $project->country,
        __('District') => $project->district,
        __('Location') => $project->location,
        __('Project Type') => $project->project_type ? __(\Illuminate\Support\Str::headline($project->project_type)) : null,
        __('Start Date') => \App\Support\Format::date($project->start_date),
        __('Expected Completion') => \App\Support\Format::date($project->expected_completion_date),
        __('Currency') => $project->currency,
    ];
@endphp

<div class="boq-page-stack">

    <x-ui.page-header :title="$project->name" icon="fa-folder-open">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-ui.status :status="$project->status" />
            @if($project->code)
                <span class="boq-code">{{ $project->code }}</span>
            @endif
            @if($project->location)
                <span class="boq-cell-with-icon text-sm text-slate-500"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $project->location }}</span>
            @endif
        </div>

        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="route('projects.index')">{{ __('Back') }}</x-ui.button>
            @if(auth()->user()->hasPermission('projects.edit'))
                <x-ui.button variant="secondary" icon="fa-pen" :href="route('projects.edit', $project->id)">{{ __('Edit') }}</x-ui.button>
            @endif
            @can('create', [\App\Models\Boq::class, $project])
                <x-ui.button icon="fa-file-circle-plus" :href="route('boqs.create', ['project' => $project->id])">{{ __('Add BOQ') }}</x-ui.button>
            @endcan
            @can('create', [\App\Models\Expense::class, $project])
                <x-ui.button variant="secondary" icon="fa-receipt" :href="route('expenses.index', ['project' => $project->id])">{{ __('Expenses & Receipts') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash />

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-5 xl:col-span-2">
            <x-boq-totals :totals="$totals" :currency="$project->currency" :title="__('Project totals')" />

            <x-ui.card :title="__('BOQs')" icon="fa-file-invoice-dollar" :padded="false">
                <x-slot:actions>
                    <x-ui.badge>{{ \App\Support\Format::number($project->boqs->count(), 0) }}</x-ui.badge>
                </x-slot:actions>

                @forelse ($project->boqs as $boq)
                    <a
                        href="{{ route('boqs.show', $boq) }}"
                        wire:key="project-boq-{{ $boq->id }}"
                        class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900">{{ $boq->name }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span>{{ trans_choice(':count item|:count items', $boqTotals[$boq->id]['items'] ?? 0, ['count' => \App\Support\Format::number($boqTotals[$boq->id]['items'] ?? 0, 0)]) }}</span>
                                <x-ui.status :status="$boq->status" />
                            </div>
                        </div>
                        <x-boq-totals compact class="sm:text-right" :totals="$boqTotals[$boq->id] ?? []" :currency="$boq->currency ?: $project->currency" />
                    </a>
                @empty
                    <x-ui.empty-state
                        icon="fa-file-invoice-dollar"
                        :title="__('No BOQs yet.')"
                        :description="__('Upload a bill of quantities to price it against current market rates.')"
                    >
                        @can('create', [\App\Models\Boq::class, $project])
                            <x-ui.button icon="fa-file-arrow-up" :href="route('boqs.create', ['project' => $project->id])">{{ __('Add BOQ') }}</x-ui.button>
                        @endcan
                    </x-ui.empty-state>
                @endforelse
            </x-ui.card>
        </div>

        <div class="flex min-w-0 flex-col gap-5">
            <x-ui.card :title="__('Progress')" icon="fa-chart-line">
                <div class="text-2xl font-bold tracking-tight text-slate-900">{{ $project->progressPercent() }}%</div>
                <div class="mt-2"><x-project-progress :project="$project" :show-label="false" /></div>
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <x-project-deadline :project="$project" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Contract Value')" icon="fa-money-bill-wave">
                <p class="text-2xl font-bold tracking-tight text-slate-900">
                    <x-money :amount="$project->contract_value ?? 0" :currency="$project->currency" />
                </p>
            </x-ui.card>

            <x-ui.card :title="__('Project Details')" icon="fa-circle-info">
                <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    @foreach($details as $label => $value)
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold text-slate-500">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 [overflow-wrap:anywhere]">{{ filled($value) ? $value : $dash }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if(filled($project->description))
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <p class="text-xs font-semibold text-slate-500">{{ __('Description') }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $project->description }}</p>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
