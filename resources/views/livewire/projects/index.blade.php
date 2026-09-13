<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Projects</h1>
            <p class="mt-1 text-slate-500">Manage your construction projects</p>
        </div>
        <a href="{{ url('/projects/create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            New Project
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-indigo-500">
            <p class="text-sm font-medium text-slate-500">Total Projects</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['total_projects'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2h14a2 2 0 012 2z" /><path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5 5 5M7 11v10a2 2 0 002 2h10" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-emerald-500">
            <p class="text-sm font-medium text-slate-500">Active Projects</p>
            <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $stats['active_projects'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-amber-500">
            <p class="text-sm font-medium text-slate-500">Total BOQs</p>
            <p class="mt-1 text-3xl font-bold text-amber-600">{{ $stats['total_boqs'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-indigo-500">
            <p class="text-sm font-medium text-slate-500">Total Value</p>
            <p class="mt-1 text-3xl font-bold text-indigo-600">UGX {{ number_format($stats['total_value'] / 1000000, 1) }}M</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search projects..."
                   class="w-full pl-10 rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full sm:w-auto">
            <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }} per page</option>
                @endforeach
            </select>

            <div class="flex gap-2">
                @foreach(['name' => 'Name', 'code' => 'Code', 'client' => 'Client', 'location' => 'Location', 'contract_value' => 'Value', 'status' => 'Status', 'created_at' => 'Created'] as $field => $label)
                    <button wire:click="sortBy('{{ $field }}')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $sortBy === $field ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }} whitespace-nowrap">
                        {{ $label }} {{ $sortBy === $field ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        @if(isset($projects) && $projects->isNotEmpty())
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('name')" style="user-select: none;">
                            Name {{ $sortBy === 'name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('code')" style="user-select: none;">
                            Code {{ $sortBy === 'code' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('client')" style="user-select: none;">
                            Client {{ $sortBy === 'client' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('location')" style="user-select: none;">
                            Location {{ $sortBy === 'location' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('contract_value')" style="user-select: none;">
                            Contract Value {{ $sortBy === 'contract_value' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('status')" style="user-select: none;">
                            Status {{ $sortBy === 'status' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('start_date')" style="user-select: none;">
                            Start Date {{ $sortBy === 'start_date' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach($projects as $project)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ url('/projects/' . $project->id) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $project->code }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $project->client }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $project->location }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ number_format((float) $project->contract_value, 2) }} {{ $project->currency }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ match($project->status) {
                                    'active' => 'bg-emerald-100 text-emerald-800',
                                    'completed' => 'bg-blue-100 text-blue-800',
                                    'archived' => 'bg-slate-100 text-slate-800',
                                    default => 'bg-amber-100 text-amber-800',
                                } }}">
                                    {{ $project->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $project->start_date?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                <a href="{{ url('/projects/' . $project->id) }}" class="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition mr-1">View</a>
                                <a href="{{ url('/projects/' . $project->id . '/edit') }}" class="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition mr-1">Edit</a>
                                <button type="button" wire:click="delete({{ $project->id }})" wire:confirm="Are you sure you want to delete this project?"
                                        class="inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white text-xs font-semibold rounded-lg transition">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($projects->hasPages())
                <div class="px-4 py-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1">
                        <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 text-sm">
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }} per page</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 sm:ml-auto">
                        {{ $projects->links() }}
                    </div>
                </div>
            @endif
        @else
            <div class="text-center py-12">
                <p class="text-slate-500">No projects found.</p>
                <a href="{{ url('/projects/create') }}" class="inline-flex items-center px-4 py-2 mt-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                    Create your first project
                </a>
            </div>
        @endif
    </div>
</div>