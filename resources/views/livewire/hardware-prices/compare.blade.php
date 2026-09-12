<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Compare Hardware Prices</h1>
            <p class="mt-1 text-sm text-gray-500">Select 2 to 10 items to compare side by side</p>
        </div>
        <a href="{{ url('/hardware-prices') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Back
        </a>
    </div>

    {{-- Selection --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Select Items</h2>
            <button type="button" wire:click="compare"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
                Compare Selected ({{ isset($selectedIds) ? count($selectedIds) : 0 }})
            </button>
        </div>

        @if(isset($prices) && $prices->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($prices as $item)
                    <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" wire:model="selectedIds" value="{{ $item->id }}" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-gray-900 truncate">{{ $item->item_name }}</span>
                            <span class="block text-xs text-gray-500">{{ number_format((float) $item->price, 2) }} {{ $item->currency }} ? {{ $item->supplier }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">No items available to compare.</p>
        @endif
    </div>

    {{-- Comparison Results --}}
    @if(isset($comparison) && count($comparison) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Comparison</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">Attribute</th>
                            @foreach($comparison as $item)
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider min-w-[180px]">
                                    <span class="block text-sm font-semibold text-gray-900">{{ $item['item_name'] }}</span>
                                    @if(isset($summary) && is_array($summary))
                                        <span class="mt-1 flex flex-wrap gap-1">
                                            @if(isset($summary['best_value']) && $summary['best_value'] === $item['id'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800">Best Value</span>
                                            @endif
                                            @if(isset($summary['lowest_price']) && $summary['lowest_price'] === $item['id'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-800">Lowest Price</span>
                                            @endif
                                            @if(isset($summary['best_rated']) && $summary['best_rated'] === $item['id'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-800">Best Rated</span>
                                            @endif
                                        </span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Brand</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item['brand'] ?? '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Category</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item['category'] ?? '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Specification</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item['specification'] ?? '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Unit</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item['unit'] ?? '?' }}</td>
                            @endforeach
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Price</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm font-bold text-gray-900">{{ number_format((float) $item['price'], 2) }} {{ $item['currency'] }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Supplier</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item['supplier'] ?? '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Location</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item['location'] ?? '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Fetched At</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item['fetched_at'] ?? '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Lowest</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ isset($item['price_history']['lowest']) ? number_format((float) $item['price_history']['lowest'], 2) : '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Highest</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ isset($item['price_history']['highest']) ? number_format((float) $item['price_history']['highest'], 2) : '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Average</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ isset($item['price_history']['average']) ? number_format((float) $item['price_history']['average'], 2) : '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Change</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ isset($item['price_history']['change']) ? number_format((float) $item['price_history']['change'], 2) : '?' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">Change %</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm text-gray-900">{{ isset($item['price_history']['change_percent']) ? number_format((float) $item['price_history']['change_percent'], 2) . '%' : '?' }}</td>
                            @endforeach
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-500">AI Rating</td>
                            @foreach($comparison as $item)
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ isset($item['rating']['overall']) && $item['rating']['overall'] >= 80 ? 'bg-green-100 text-green-800' : (isset($item['rating']['overall']) && $item['rating']['overall'] >= 60 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                        {{ $item['rating']['overall'] ?? '?' }}/100
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="text-center py-12">
                <p class="text-gray-500">Select items above and click "Compare Selected" to see the side-by-side comparison.</p>
            </div>
        </div>
    @endif
</div>
