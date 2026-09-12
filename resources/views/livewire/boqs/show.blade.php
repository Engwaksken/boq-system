<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ $boq->name }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $boq->status === 'approved' || $boq->status === 'analysed' ? 'bg-green-100 text-green-800' : ($boq->status === 'under_review' ? 'bg-purple-100 text-purple-800' : ($boq->status === 'uploaded' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')) }}">
                {{ $boq->status }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ url('/boqs') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back
            </a>
            @if(isset($pdfUrl) && $pdfUrl)
                <a href="{{ $pdfUrl }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Download PDF
                </a>
            @endif
        </div>
    </div>

    {{-- BOQ Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500">Project</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->project?->name ?? '?' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Currency</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->currency }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Version</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->version }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Source Type</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $boq->source_type ?? '?' }}</dd>
            </div>
            @if($boq->description)
                <div class="md:col-span-4">
                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $boq->description }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Items Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Items ({{ $boq->items->count() }})</h2>
        </div>

        @if($boq->items->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item Code</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Original Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">AI Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Approved Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">AI Conf.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($boq->items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $item->item_code }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">{{ $item->description }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $item->unit }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) $item->quantity, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) $item->original_rate, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) $item->ai_suggested_rate, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ number_format((float) $item->approved_rate, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format((float) $item->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $item->ai_confidence ? number_format((float) $item->ai_confidence, 0) . '%' : '?' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item->status === 'approved' ? 'bg-green-100 text-green-800' : ($item->status === 'reviewed' ? 'bg-blue-100 text-blue-800' : ($item->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800')) }}">
                                        {{ $item->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No items in this BOQ yet.</p>
            </div>
        @endif
    </div>
</div>
