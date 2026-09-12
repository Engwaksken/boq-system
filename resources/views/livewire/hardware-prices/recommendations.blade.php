<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Recommendations</h1>
            <p class="mt-1 text-sm text-gray-500">Best-value hardware picks based on price, stability and freshness</p>
        </div>
        <a href="{{ url('/hardware-prices') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Back
        </a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="location" class="block text-xs font-medium text-gray-500 mb-1">Location</label>
                <input type="text" wire:model.live.debounce.300ms="location" id="location" placeholder="e.g. Kampala"
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="load"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Refresh Recommendations
                </button>
            </div>
        </div>
    </div>

    {{-- Recommendation Cards --}}
    @if(isset($recommendations) && count($recommendations) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($recommendations as $rec)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold {{ isset($rec['rating']['overall']) && $rec['rating']['overall'] >= 80 ? 'bg-green-100 text-green-700' : (isset($rec['rating']['overall']) && $rec['rating']['overall'] >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                {{ $rec['rating']['overall'] ?? '?' }}
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">AI Rating</p>
                                <p class="text-xs font-medium text-gray-700">out of 100</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ isset($rec['price_history']['trend']) && $rec['price_history']['trend'] === 'rising' ? 'bg-red-100 text-red-800' : (isset($rec['price_history']['trend']) && $rec['price_history']['trend'] === 'falling' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') }}">
                            {{ ucfirst($rec['price_history']['trend'] ?? 'stable') }}
                        </span>
                    </div>

                    <h3 class="text-base font-semibold text-gray-900">{{ $rec['item_name'] }}</h3>
                    <p class="text-sm text-gray-500">{{ $rec['brand'] ?? '' }}</p>
                    <span class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 w-fit">{{ $rec['category'] }}</span>

                    <p class="mt-3 text-sm text-gray-500 line-clamp-2">{{ $rec['specification'] ?? '' }}</p>

                    <p class="mt-4 text-xl font-bold text-gray-900">{{ number_format((float) $rec['price'], 2) }} {{ $rec['currency'] }}</p>
                    <p class="text-sm text-gray-500">{{ $rec['supplier'] ?? '' }} ? {{ $rec['location'] ?? '' }}</p>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs border-t border-gray-100 pt-4">
                        <div>
                            <p class="text-gray-500">Lowest</p>
                            <p class="font-medium text-gray-900">{{ isset($rec['price_history']['lowest']) ? number_format((float) $rec['price_history']['lowest'], 2) : '?' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Highest</p>
                            <p class="font-medium text-gray-900">{{ isset($rec['price_history']['highest']) ? number_format((float) $rec['price_history']['highest'], 2) : '?' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Average</p>
                            <p class="font-medium text-gray-900">{{ isset($rec['price_history']['average']) ? number_format((float) $rec['price_history']['average'], 2) : '?' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Change</p>
                            <p class="font-medium text-gray-900">{{ isset($rec['price_history']['change']) ? number_format((float) $rec['price_history']['change'], 2) : '?' }}</p>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ url('/hardware-prices/' . $rec['id']) }}" class="inline-flex items-center justify-center w-full px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
                            View Details
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="text-center py-12">
                <p class="text-gray-500">No recommendations available yet.</p>
            </div>
        </div>
    @endif
</div>
