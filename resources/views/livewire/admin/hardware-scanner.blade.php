<div class="min-h-screen bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">Hardware Price Scanner</h1>
            <p class="mt-2 text-slate-600">Scan and import hardware prices from external sources.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">AI Price Scanner</h2>
                <p class="text-slate-600 mb-6">Scan current market prices for a specific category and location using AI.</p>

                <form wire:submit.prevent="scanPrices" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                            <select wire:model="scanForm.category" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Select category...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                            @error('scanForm.category') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Location</label>
                            <input type="text" wire:model="scanForm.location" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required placeholder="e.g., Kampala, Nairobi">
                            @error('scanForm.location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Items to Fetch</label>
                            <input type="number" wire:model="scanForm.limit" min="1" max="50" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            @error('scanForm.limit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200">
                        <button wire:submit.prevent="scanPrices" wire:loading.attr="disabled" type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition">
                            <svg wire:loading.class="animate-spin" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            <span wire:loading.remove>Scan Prices</span>
                            <span wire:loading>Scanning...</span>
                        </button>
                    </div>
                </form>

                @if($scanResults)
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Scan Results ({{ count($scanResults) }})</h3>
                        <div class="max-h-64 overflow-y-auto bg-slate-50 rounded-xl p-4">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-slate-500">
                                    <tr>
                                        <th class="pb-2">Item</th>
                                        <th class="pb-2 text-right">Price</th>
                                        <th class="pb-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($scanResults as $result)
                                        <tr class="border-t border-slate-200">
                                            <td class="py-2 font-medium">{{ $result['item'] }}</td>
                                            <td class="py-2 text-right font-mono">{{ number_format($result['price'], 2) }}</td>
                                            <td class="py-2">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $result['status'] === 'created' ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800' }}">
                                                    {{ ucfirst($result['status']) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">CSV Import</h2>
                <p class="text-slate-600 mb-6">Import hardware prices from a CSV file.</p>

                <form wire:submit.prevent="importCsv" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">CSV File</label>
                        <input type="file" wire:model="csvFile" accept=".csv,.txt" class="mt-1 w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required>
                        @error('csvFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-sm text-slate-500">Required columns: item_name, category, unit, price, currency, supplier. Optional: brand, specification, location, source_url, source_reference.</p>

                    <button wire:submit.prevent="importCsv" wire:loading.attr="disabled" type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition">
                        <svg wire:loading.class="animate-spin" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        <span wire:loading.remove>Import CSV</span>
                        <span wire:loading>Importing...</span>
                    </button>
                </form>

                @if($importResults)
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                            <p class="text-3xl font-bold text-emerald-800">{{ $importResults['created'] }}</p>
                            <p class="text-sm text-emerald-700">Created</p>
                        </div>
                        <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-200">
                            <p class="text-3xl font-bold text-indigo-800">{{ $importResults['updated'] }}</p>
                            <p class="text-sm text-indigo-700">Updated</p>
                        </div>
                        <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                            <p class="text-3xl font-bold text-red-800">{{ $importResults['errors'] }}</p>
                            <p class="text-sm text-red-700">Errors</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">Scheduled Fetch</h2>
                <p class="text-slate-600 mb-4">Run the daily hardware price fetch command manually.</p>

                <button wire:click="runFetchCommand" wire:loading.attr="disabled" type="button" class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:bg-amber-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition">
                    <svg wire:loading.class="animate-spin" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    <span wire:loading.remove>Run Daily Fetch</span>
                    <span wire:loading>Running...</span>
                </button>
            </div>
        </div>
    </div>
</div>