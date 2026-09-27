
<div x-data="{ dashboardTab: 'projects' }" class="space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Overview of your BOQ workspace.</p>
    </div>

    <div class="boq-stats-grid">
        <x-stat-card label="Projects" :value="\App\Support\Format::number($projectsCount ?? 0, 0)" icon="fa-folder-open" color="green" />
        <x-stat-card label="BOQs" :value="\App\Support\Format::number($boqsCount ?? 0, 0)" icon="fa-file-invoice-dollar" color="blue" />
        <x-stat-card label="Hardware Prices" :value="\App\Support\Format::number($hardwarePricesCount ?? 0, 0)" icon="fa-tags" color="amber" />
        <x-stat-card label="Subscription" :value="isset($subscription) && $subscription ? ($subscription->plan?->name ?? 'Active') : 'Free'" icon="fa-credit-card" color="purple" />
    </div>

    <div class="boq-panel overflow-hidden">
        <div class="boq-admin-tabs">
            <button @click="dashboardTab='projects'" :class="dashboardTab==='projects' ? 'is-active' : ''" class="boq-admin-tab">Recent Projects</button>
            <button @click="dashboardTab='actions'" :class="dashboardTab==='actions' ? 'is-active' : ''" class="boq-admin-tab">Quick Actions</button>
            @if(Auth::user()?->isSuperAdmin())
                <button @click="dashboardTab='admin'" :class="dashboardTab==='admin' ? 'is-active' : ''" class="boq-admin-tab">Administration</button>
            @endif
        </div>

        <div x-show="dashboardTab==='projects'">
            @if(isset($recentProjects) && $recentProjects->isNotEmpty())
            <div class="overflow-x-auto"><table class="boq-table min-w-full divide-y divide-slate-200"><thead><tr><th class="px-4 py-3 text-left">Name</th><th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Contract Value</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($recentProjects as $project)<tr><td class="px-4 py-3"><a href="{{ url('/projects/'.$project->id) }}" class="font-semibold text-emerald-700">{{ $project->name }}</a></td><td class="px-4 py-3 text-sm">{{ $project->code }}</td><td class="px-4 py-3 text-sm">{{ ucfirst($project->status) }}</td><td class="px-4 py-3 text-sm">{{ \App\Support\Format::number((float)$project->contract_value, 2) }} {{ $project->currency }}</td></tr>@endforeach</tbody></table></div>
            @else<div class="p-8 text-center text-slate-500">No projects yet.</div>@endif
        </div>

        <div x-show="dashboardTab==='actions'" x-cloak class="p-5">
            <div class="flex flex-wrap gap-3">
                <a href="{{ url('/projects/create') }}" class="boq-btn-primary">New Project</a>
                <a href="{{ url('/boqs/create') }}" class="boq-btn-secondary">New BOQ</a>
                <a href="{{ url('/hardware-prices/compare') }}" class="boq-btn-secondary">Compare Prices</a>
                <a href="{{ url('/plans') }}" class="boq-btn-secondary">View Plans</a>
            </div>
        </div>

        @if(Auth::user()?->isSuperAdmin())
        <div x-show="dashboardTab==='admin'" x-cloak class="p-5">
            <p class="mb-4 text-sm text-slate-600">All administration pages are also available under Administration in the sidebar.</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.index') }}" class="boq-btn-primary">Admin Overview</a>
                <a href="{{ route('admin.plans') }}" class="boq-btn-secondary">Plans</a>
                <a href="{{ route('admin.subscriptions') }}" class="boq-btn-secondary">Subscriptions</a>
                <a href="{{ route('admin.payment-gateways') }}" class="boq-btn-secondary">Payment Gateways</a>
                <a href="{{ route('admin.users') }}" class="boq-btn-secondary">Users</a>
                <a href="{{ route('admin.roles-permissions') }}" class="boq-btn-secondary">Roles & Permissions</a>
                <a href="{{ route('admin.settings') }}" class="boq-btn-secondary">Settings</a>
            </div>
        </div>
        @endif
    </div>
</div>
