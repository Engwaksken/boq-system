<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Hardware Prices</h1>
            <p class="mt-1 text-sm text-gray-500">Tracked market prices for hardware items</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ url('/hardware-prices/compare') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                Compare
            </a>
            <a href="{{ url('/hardware-prices/recommendations') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                Recommendations
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input type="search" wire:model.live.debounce.300ms="search" id="search" placeholder="Item, brand, supplier..."
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
            <div>
                <label for="category" class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                <select wire:model.live="category" id="category" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">All Categories</option>
                    @if(isset($categories) && $categories->isNotEmpty())
                        @foreach($categories as $categoryOption)
                            <option value="{{ is_object($categoryOption) ? $categoryOption->name : $categoryOption }}">{{ is_object($categoryOption) ? $categoryOption->name : $categoryOption }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div>
                <label for="supplier" class="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                <input type="text" wire:model.live.debounce.300ms="supplier" id="supplier" placeholder="Supplier..."
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
            <div>
                <label for="location" class="block text-xs font-medium text-gray-500 mb-1">Location</label>
                <input type="text" wire:model.live.debounce.300ms="location" id="location" placeholder="Location..."
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
        </div>
    </div>

    {{-- Prices Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if(isset($prices) && $prices->isNotEmpty())
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Currency</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Updated</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($prices as $price)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ url('/hardware-prices/' . $price->id) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $price->item_name }}</a>
                                @if($price->brand)
                                    <p class="text-xs text-gray-500">{{ $price->brand }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ $price->category }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $price->unit }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ number_format((float) $price->price, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $price->currency }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $price->supplier }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $price->fetched_at?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                <a href="{{ url('/hardware-prices/' . $price->id) }}" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg transition">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($prices->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $prices->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No hardware prices found.</p>
            </div>
        @endif
    </div>
</div>
