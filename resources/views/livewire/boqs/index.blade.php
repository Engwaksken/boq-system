<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">BOQs</h1>
            <p class="mt-1 text-sm text-gray-500">Bill of Quantities for your projects</p>
        </div>
        <a href="{{ url('/boqs/create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            New BOQ
        </a>
    </div>

    {{-- Search --}}
    <div class="mb-6">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search BOQs..."
                   class="w-full pl-10 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>
    </div>

    {{-- BOQs Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if(isset($boqs) && $boqs->isNotEmpty())
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Currency</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Version</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($boqs as $boq)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ url('/boqs/' . $boq->id) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $boq->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $boq->project?->name }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $boq->status === 'approved' || $boq->status === 'analysed' ? 'bg-green-100 text-green-800' : ($boq->status === 'under_review' ? 'bg-purple-100 text-purple-800' : ($boq->status === 'uploaded' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')) }}">
                                    {{ $boq->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $boq->currency }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $boq->version }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $boq->items_count ?? $boq->items->count() }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $boq->created_at?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                <a href="{{ url('/boqs/' . $boq->id) }}" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg transition">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($boqs->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $boqs->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No BOQs found.</p>
                <a href="{{ url('/boqs/create') }}" class="inline-flex items-center px-4 py-2 mt-4 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
                    Upload your first BOQ
                </a>
            </div>
        @endif
    </div>
</div>
