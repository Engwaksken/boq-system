<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">BOQs</h1>
            <p class="mt-1 text-slate-500">Bill of Quantities for your projects</p>
        </div>
        <a href="{{ url('/boqs/create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            New BOQ
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-indigo-500">
            <p class="text-sm font-medium text-slate-500">Total BOQs</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['total_boqs'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-blue-500">
            <p class="text-sm font-medium text-slate-500">Uploaded</p>
            <p class="mt-1 text-3xl font-bold text-blue-600">{{ $stats['uploaded'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-purple-500">
            <p class="text-sm font-medium text-slate-500">Under Review</p>
            <p class="mt-1 text-3xl font-bold text-purple-600">{{ $stats['under_review'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-emerald-500">
            <p class="text-sm font-medium text-slate-500">Approved</p>
            <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $stats['approved'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search BOQs..."
                   class="w-full pl-10 rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full sm:w-auto">
            <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }} per page</option>
                @endforeach
            </select>

            <div class="flex gap-2">
                @foreach(['name' => 'Name', 'project.name' => 'Project', 'status' => 'Status', 'currency' => 'Currency', 'created_at' => 'Created'] as $field => $label)
                    <button wire:click="sortBy('{{ $field }}')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $sortBy === $field ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }} whitespace-nowrap">
                        {{ $label }} {{ $sortBy === $field ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        @if(isset($boqs) && $boqs->isNotEmpty())
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('name')" style="user-select: none;">
                            Name {{ $sortBy === 'name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('project.name')" style="user-select: none;">
                            Project {{ $sortBy === 'project.name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('status')" style="user-select: none;">
                            Status {{ $sortBy === 'status' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('currency')" style="user-select: none;">
                            Currency {{ $sortBy === 'currency' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('version')" style="user-select: none;">
                            Version {{ $sortBy === 'version' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('items_count')" style="user-select: none;">
                            Items {{ $sortBy === 'items_count' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('created_at')" style="user-select: none;">
                            Created {{ $sortBy === 'created_at' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach($boqs as $boq)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ url('/boqs/' . $boq->id) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $boq->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $boq->project?->name }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ match($boq->status) {
                                    'approved', 'analysed' => 'bg-emerald-100 text-emerald-800',
                                    'under_review' => 'bg-purple-100 text-purple-800',
                                    'uploaded' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-amber-100 text-amber-800',
                                } }}">
                                    {{ $boq->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $boq->currency }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $boq->version }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $boq->items_count ?? $boq->items->count() }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $boq->created_at?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                <a href="{{ url('/boqs/' . $boq->id) }}" class="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($boqs->hasPages())
                <div class="px-4 py-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1">
                        <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 text-sm">
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }} per page</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 sm:ml-auto">
                        {{ $boqs->links() }}
                    </div>
                </div>
            @endif
        @else
            <div class="text-center py-12">
                <p class="text-slate-500">No BOQs found.</p>
                <a href="{{ url('/boqs/create') }}" class="inline-flex items-center px-4 py-2 mt-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                    Upload your first BOQ
                </a>
            </div>
        @endif
    </div>
</div>