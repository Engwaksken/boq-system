<div class="min-h-screen bg-slate-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Payment Gateways Management</h1>
                <p class="mt-2 text-slate-600">Configure payment drivers, keys, and webhook settings.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-lg transition">
                    &larr; Back to Admin Panel
                </a>
                <button wire:click="create" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition shadow-sm">
                    + Add Gateway
                </button>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium">
                {{ session('message') }}
            </div>
        @endif

        @if($showForm)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-8">
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ $editingId ? 'Edit Payment Gateway' : 'New Payment Gateway' }}</h2>
                <form wire:submit.prevent="save" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                            <input type="text" wire:model="form.name" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. Flutterwave">
                            @error('form.name') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Code (Unique)</label>
                            <input type="text" wire:model="form.code" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. flutterwave">
                            @error('form.code') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Driver</label>
                            <div class="flex gap-2">
                                <select wire:model="form.driver" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    @foreach($driverOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="loadDriverTemplate" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold whitespace-nowrap">Load template</button>
                            </div>
                            @error('form.driver') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Timeout (Seconds)</label>
                            <input type="number" wire:model="form.payment_timeout_seconds" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="900">
                            @error('form.payment_timeout_seconds') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                            <textarea wire:model="form.description" rows="2" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Description of the payment gateway"></textarea>
                            @error('form.description') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL</label>
                            <input type="text" wire:model="form.webhook_url" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="https://example.com/webhook">
                            @error('form.webhook_url') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Supported currencies</label>
                            <input type="text" wire:model="supportedCurrenciesCsv" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="UGX, USD">
                            <p class="mt-1 text-xs text-slate-500">Comma-separated ISO currency codes.</p>
                            @error('supportedCurrenciesCsv') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Supported countries</label>
                            <input type="text" wire:model="supportedCountriesCsv" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="UG">
                            <p class="mt-1 text-xs text-slate-500">Comma-separated country codes.</p>
                            @error('supportedCountriesCsv') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Supported payment methods</label>
                            <input type="text" wire:model="supportedMethodsCsv" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="mtn, airtel, card, mobile_money">
                            @error('supportedMethodsCsv') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-sm font-medium text-slate-700">Gateway configuration (JSON)</label>
                                <span class="text-xs text-slate-500">Saved secrets display as <code>***stored***</code></span>
                            </div>
                            <textarea wire:model="configJson" rows="12" spellcheck="false" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono" placeholder="{}"></textarea>
                            <p class="mt-1 text-xs text-slate-500">Credentials remain encrypted in the database. Leave <code>***stored***</code> unchanged to retain an existing secret.</p>
                            @error('configJson') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center gap-6 pt-2">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 h-4 w-4">
                                <span class="ml-2 text-sm font-medium text-slate-700">Active</span>
                            </label>

                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="form.is_test_mode" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 h-4 w-4">
                                <span class="ml-2 text-sm font-medium text-slate-700">Test Mode</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="cancel" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold rounded-lg text-sm transition">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg shadow-sm transition text-sm">Save Gateway</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Driver</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Mode</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($gateways as $gateway)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $gateway->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ $gateway->code }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $gateway->driver }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $gateway->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                    {{ $gateway->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $gateway->is_test_mode ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $gateway->is_test_mode ? 'Test' : 'Live' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button wire:click="edit({{ $gateway->id }})" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button wire:click="toggleActive({{ $gateway->id }})" class="text-sm font-medium text-slate-600 hover:text-slate-800">{{ $gateway->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No payment gateways configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4 border-t border-slate-200">
                {{ $gateways->links() }}
            </div>
        </div>
    </div>
</div>
