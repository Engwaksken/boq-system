<div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-slate-900">Hardware Price Bookmarks</h3>
        <button wire:click="bookmarkHardware(0)" type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            Add Bookmark
        </button>
    </div>

    @if($bookmarkedHardware->isEmpty())
        <div class="text-center py-12 bg-slate-50 rounded-xl">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg>
            <h4 class="mt-4 text-lg font-medium text-slate-900">No bookmarks yet</h4>
            <p class="mt-2 text-slate-500">Bookmark hardware prices by location for quick access.</p>
            <button wire:click="bookmarkHardware(0)" type="button" class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                Add Your First Bookmark
            </button>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Hardware Item</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Location</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Notes</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    @foreach($bookmarkedHardware as $bookmark)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $bookmark->hardwarePrice->item_name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $bookmark->hardwarePrice->category }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $bookmark->location }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-slate-900">{{ $bookmark->hardwarePrice->currency }} {{ number_format($bookmark->hardwarePrice->price, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500 max-w-xs truncate">{{ $bookmark->notes ?? '-' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button wire:click="editBookmark({{ $bookmark->id }})" type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 mr-3">Edit</button>
                                <button wire:click="deleteBookmark({{ $bookmark->id }})" type="button" class="text-sm font-medium text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <livewire:components.modal>
        <form wire:submit.prevent="saveBookmark" class="space-y-4">
            <input type="hidden" name="hardwareBookmarkForm[hardware_price_id]" wire:model="hardwareBookmarkForm.hardware_price_id">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Hardware Price</label>
                <select wire:model="hardwareBookmarkForm.hardware_price_id" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="">Select hardware price...</option>
                    @foreach(App\Models\HardwarePrice::active()->where('organisation_id', Auth::user()->organisation_id)->get() as $price)
                        <option value="{{ $price->id }}">{{ $price->item_name }} ({{ $price->category }}) - {{ $price->currency }} {{ number_format($price->price, 2) }}</option>
                    @endforeach
                </select>
                @error('hardwareBookmarkForm.hardware_price_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Location</label>
                <input type="text" wire:model="hardwareBookmarkForm.location" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required maxlength="150" placeholder="e.g., Kampala Central Market">
                @error('hardwareBookmarkForm.location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes (optional)</label>
                <textarea wire:model="hardwareBookmarkForm.notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" maxlength="500" placeholder="Why did you bookmark this? Any specific details?"></textarea>
                @error('hardwareBookmarkForm.notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                <button wire:click="closeModal" type="button" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg transition">Cancel</button>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                    <svg wire:loading.class="animate-spin" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    <span wire:loading.remove>{{ $editingBookmarkId ? 'Update' : 'Save' }} Bookmark</span>
                    <span wire:loading>Saving...</span>
                </button>
            </div>
        </form>
    </livewire:components.modal>
</div>