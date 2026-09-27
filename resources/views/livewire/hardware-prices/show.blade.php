<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ $hardwarePrice->item_name }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ $hardwarePrice->category }}</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ url('/hardware-prices') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back
            </a>
            <a href="{{ url('/hardware-prices/compare') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">Compare</a>
        </div>
    </div>

    {{-- Detail --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
            <div>
                <p class="text-sm text-gray-500">{{ $hardwarePrice->brand }}</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ \App\Support\Format::number((float) $hardwarePrice->price, 2) }} {{ $hardwarePrice->currency }}</p>
                <p class="mt-1 text-sm text-gray-500">per {{ $hardwarePrice->unit }}</p>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div>
                    <dt class="font-medium text-gray-500">Specification</dt>
                    <dd class="mt-1 text-gray-900">{{ $hardwarePrice->specification ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Supplier</dt>
                    <dd class="mt-1 text-gray-900">{{ $hardwarePrice->supplier ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Location</dt>
                    <dd class="mt-1 text-gray-900">{{ $hardwarePrice->location ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Source Reference</dt>
                    <dd class="mt-1 text-gray-900">{{ $hardwarePrice->source_reference ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Fetched At</dt>
                    <dd class="mt-1 text-gray-900">{{ \App\Support\Format::date($hardwarePrice->fetched_at, true) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Summary Stats --}}
    @php
        $priceChange = $hardwarePrice->price_change;
        $priceChangePercent = $hardwarePrice->price_change_percent;
    @endphp

    <div class="boq-stats-grid mb-6">
        <x-stat-card label="Lowest Price" :value="\App\Support\Format::number((float) $hardwarePrice->lowest_price, 2)" icon="fa-arrow-down" color="green" />
        <x-stat-card label="Highest Price" :value="\App\Support\Format::number((float) $hardwarePrice->highest_price, 2)" icon="fa-arrow-up" color="red" />
        <x-stat-card label="Average Price" :value="\App\Support\Format::number((float) $hardwarePrice->average_price, 2)" icon="fa-scale-balanced" color="blue" />
        <x-stat-card
            label="Price Change"
            :value="$priceChange !== null ? \App\Support\Format::number((float) $priceChange, 2) : '—'"
            :hint="$priceChangePercent !== null ? \App\Support\Format::number((float) $priceChangePercent, 2).'%' : null"
            icon="fa-chart-line"
            :color="$priceChange !== null && (float) $priceChange < 0 ? 'green' : 'amber'"
        />
    </div>

    {{-- Price History --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Price History</h2>
        </div>

        @if($hardwarePrice->priceHistories->isNotEmpty())
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Recorded At</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Currency</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($hardwarePrice->priceHistories as $history)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-700">{{ \App\Support\Format::date($history->recorded_at, true) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ \App\Support\Format::number((float) $history->price, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $history->currency }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $history->supplier }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $history->location }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No price history recorded yet.</p>
            </div>
        @endif
    </div>
</div>
