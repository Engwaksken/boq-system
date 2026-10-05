@php
    $user = Auth::user();
    $canProjects = $user?->hasPermission('projects.view');
    $canCreateProject = $user?->hasPermission('projects.create');
    $canPrices = $user?->hasPermission('hardware-prices.view');
    $canSubscriptions = $user?->hasPermission('subscriptions.view');
    $firstName = \Illuminate\Support\Str::before(trim((string) $user?->name), ' ') ?: $user?->name;
@endphp

<div class="boq-page-stack">
    <x-ui.flash />

    <x-ui.page-header
        :title="__('Dashboard')"
        :subtitle="__('Overview of your BOQ workspace.')"
        :eyebrow="__('Welcome back, :name', ['name' => $firstName])"
    >
        <x-slot:actions>
            @if($canCreateProject)
                <x-ui.button variant="secondary" icon="fa-folder-plus" :href="route('projects.create')">{{ __('New Project') }}</x-ui.button>
            @endif
            <x-ui.button icon="fa-file-circle-plus" :href="route('boqs.create')">{{ __('New BOQ') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="boq-stats-grid">
        <x-stat-card
            :label="__('Projects')"
            :value="\App\Support\Format::number($projectsCount ?? 0, 0)"
            icon="fa-folder-open"
            color="green"
            :href="$canProjects ? route('projects.index') : null"
        />
        <x-stat-card
            :label="__('BOQs')"
            :value="\App\Support\Format::number($boqsCount ?? 0, 0)"
            icon="fa-file-invoice-dollar"
            color="blue"
            :href="route('boqs.index')"
        />
        <x-stat-card
            :label="__('Hardware & Factory Prices')"
            :value="\App\Support\Format::number($hardwarePricesCount ?? 0, 0)"
            icon="fa-tags"
            color="amber"
            :href="$canPrices ? route('hardware-prices.index') : null"
        />
        <x-stat-card
            :label="__('Subscription')"
            :value="$subscription ? ($subscription->plan?->name ?? __('Active')) : __('Free')"
            :hint="$subscription?->end_date ? __('Renews or ends on :date', ['date' => \App\Support\Format::date($subscription->end_date)]) : null"
            icon="fa-credit-card"
            color="purple"
            :href="$canSubscriptions ? route('subscriptions.index') : route('plans.index')"
        />
    </div>

    <div class="grid gap-5 xl:grid-cols-3">
        <x-ui.card
            class="xl:col-span-2"
            :title="__('Recent Projects')"
            icon="fa-clock-rotate-left"
            :padded="false"
        >
            @if($canProjects)
                <x-slot:actions>
                    <a href="{{ route('projects.index') }}" class="boq-link-button">{{ __('View all') }} <i class="fas fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
                </x-slot:actions>
            @endif

            @if(isset($recentProjects) && $recentProjects->isNotEmpty())
                <x-ui.table compact>
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('BOQs') }}</th>
                            <th class="text-right">{{ __('Contract Value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentProjects as $project)
                            <tr wire:key="recent-project-{{ $project->id }}">
                                <td>
                                    <a href="{{ route('projects.show', $project->id) }}" class="boq-table-link">{{ $project->name }}</a>
                                    @if($project->code)
                                        <div class="boq-table-subtitle">{{ $project->code }}</div>
                                    @endif
                                </td>
                                <td><x-ui.status :status="$project->status" /></td>
                                <td>{{ \App\Support\Format::number($project->boqs_count ?? 0, 0) }}</td>
                                <td class="is-numeric font-semibold text-slate-900">
                                    <x-money :amount="$project->contract_value ?? 0" :currency="$project->currency" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @else
                <x-ui.empty-state
                    icon="fa-folder-open"
                    :title="__('No projects yet.')"
                    :description="__('Create a project to organise your BOQs, pricing and reports.')"
                >
                    @if($canCreateProject)
                        <x-ui.button icon="fa-plus" :href="route('projects.create')">{{ __('Create your first project') }}</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @endif
        </x-ui.card>

        <div class="flex flex-col gap-5">
            <x-ui.card :title="__('Quick Actions')" icon="fa-bolt">
                <div class="grid gap-2">
                    @if($canCreateProject)
                        <a href="{{ route('projects.create') }}" class="boq-action-tile">
                            <span class="boq-stat-icon"><i class="fas fa-folder-plus" aria-hidden="true"></i></span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900">{{ __('New Project') }}</span>
                                <span class="block text-xs text-slate-500">{{ __('Set up client, location and budget.') }}</span>
                            </span>
                        </a>
                    @endif

                    <a href="{{ route('boqs.create') }}" class="boq-action-tile">
                        <span class="boq-stat-icon"><i class="fas fa-file-arrow-up" aria-hidden="true"></i></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900">{{ __('New BOQ') }}</span>
                            <span class="block text-xs text-slate-500">{{ __('Upload a spreadsheet or PDF and price it.') }}</span>
                        </span>
                    </a>

                    @if($canPrices)
                        <a href="{{ route('hardware-prices.compare') }}" class="boq-action-tile">
                            <span class="boq-stat-icon"><i class="fas fa-scale-balanced" aria-hidden="true"></i></span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900">{{ __('Compare Prices') }}</span>
                                <span class="block text-xs text-slate-500">{{ __('Side-by-side hardware and factory prices.') }}</span>
                            </span>
                        </a>
                    @endif

                    <a href="{{ route('plans.index') }}" class="boq-action-tile">
                        <span class="boq-stat-icon"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900">{{ __('View Plans') }}</span>
                            <span class="block text-xs text-slate-500">{{ __('Upgrade for more BOQs and features.') }}</span>
                        </span>
                    </a>
                </div>
            </x-ui.card>

            @if($user?->isSuperAdmin())
                <x-ui.card :title="__('Administration')" icon="fa-shield-halved">
                    <p class="mb-3 text-sm text-slate-500">{{ __('All administration pages are also available under Administration in the sidebar.') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="sm" :href="route('admin.index')">{{ __('Admin Overview') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.users')">{{ __('Users') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.subscriptions')">{{ __('Subscriptions') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.payment-gateways')">{{ __('Payment Gateways') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.settings')">{{ __('Settings') }}</x-ui.button>
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
