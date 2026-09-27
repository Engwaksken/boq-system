<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Hardware Price Bookmarks</h3>
            <p class="mt-1 text-sm text-slate-500">Keep the hardware prices you check most often, by location.</p>
        </div>

        <button wire:click="bookmarkHardware" type="button" class="boq-btn-primary">
            <i class="fas fa-bookmark"></i>
            Add Bookmark
        </button>
    </div>

    @if($bookmarkedHardware->isEmpty())
        <div class="boq-empty-state">
            <span class="boq-empty-icon">
                <i class="fas fa-bookmark"></i>
            </span>
            <h4 class="boq-empty-title">No bookmarks yet</h4>
            <p class="boq-empty-description">Bookmark hardware prices by location for quick access.</p>
            <button wire:click="bookmarkHardware" type="button" class="boq-btn-primary boq-empty-action">
                <i class="fas fa-plus"></i>
                Add Your First Bookmark
            </button>
        </div>
    @else
        <div class="boq-table-wrapper">
            <table class="boq-table">
                <thead>
                    <tr>
                        <th>Hardware Item</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th class="text-right">Price</th>
                        <th>Notes</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookmarkedHardware as $bookmark)
                        <tr wire:key="bookmark-{{ $bookmark->id }}">
                            <td>
                                @if($bookmark->hardwarePrice)
                                    <a href="{{ route('hardware-prices.show', $bookmark->hardwarePrice) }}" class="boq-table-link">
                                        {{ $bookmark->hardwarePrice->item_name }}
                                    </a>
                                @else
                                    <span class="text-slate-400">Removed item</span>
                                @endif
                            </td>
                            <td>{{ $bookmark->hardwarePrice?->category ?? '—' }}</td>
                            <td>{{ $bookmark->location }}</td>
                            <td class="text-right font-semibold">
                                @if($bookmark->hardwarePrice)
                                    {{ $bookmark->hardwarePrice->currency }}
                                    {{ number_format((float) $bookmark->hardwarePrice->price, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="max-w-xs truncate">{{ $bookmark->notes ?: '—' }}</td>
                            <td>
                                <div class="boq-table-actions justify-end">
                                    <button wire:click="editBookmark({{ $bookmark->id }})" type="button" class="boq-icon-btn" title="Edit bookmark" aria-label="Edit bookmark">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button
                                        wire:click="deleteBookmark({{ $bookmark->id }})"
                                        wire:confirm="Remove this bookmark?"
                                        type="button"
                                        class="boq-icon-btn boq-icon-danger"
                                        title="Remove bookmark"
                                        aria-label="Remove bookmark"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($showBookmarkModal)
        <div
            class="boq-modal-backdrop"
            wire:key="bookmark-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="bookmark-modal-title"
            x-data
            x-on:keydown.escape.window="$wire.closeBookmarkModal()"
        >
            <form wire:submit="saveBookmark" class="boq-modal boq-modal-sm">
                <div class="boq-modal-head">
                    <h2 id="bookmark-modal-title">
                        {{ $editingBookmarkId ? 'Edit Bookmark' : 'Bookmark Hardware' }}
                    </h2>

                    <button type="button" wire:click="closeBookmarkModal" class="boq-modal-close" aria-label="Close">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <div class="boq-modal-body space-y-4">
                    <div>
                        <label for="bookmark-price" class="boq-field-label">Hardware price</label>
                        <select id="bookmark-price" wire:model.live="hardwareBookmarkForm.hardware_price_id" class="boq-field" required>
                            <option value="">Select hardware price...</option>
                            @foreach($bookmarkablePrices as $price)
                                <option value="{{ $price->id }}">
                                    {{ $price->item_name }} · {{ ucfirst($price->price_type ?? 'hardware') }} · {{ $price->category }}{{ $price->location ? ' · '.$price->location : '' }} · {{ $price->currency }} {{ number_format((float) $price->price, 2) }}
                                </option>
                            @endforeach
                        </select>
                        @if($bookmarkablePrices->isEmpty())
                            <p class="boq-field-help">No active hardware prices are available yet.</p>
                        @endif
                        @error('hardwareBookmarkForm.hardware_price_id') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <x-select-with-other
                        label="Location"
                        choice="bookmarkLocationChoice"
                        value="hardwareBookmarkForm.location"
                        :current="$bookmarkLocationChoice"
                        :options="$bookmarkLocations"
                        :placeholder="empty($hardwareBookmarkForm['hardware_price_id']) ? 'Select a hardware price first...' : 'Select location...'"
                        other-placeholder="e.g. a market, town or supplier branch"
                        error="hardwareBookmarkForm.location"
                        required
                    />
                    <p class="boq-field-help -mt-2">Filled in from the selected price. Pick another location where this item is priced, or choose Other.</p>

                    <div>
                        <label for="bookmark-notes" class="boq-field-label">Notes (optional)</label>
                        <textarea id="bookmark-notes" wire:model="hardwareBookmarkForm.notes" rows="3" class="boq-field" maxlength="500" placeholder="Why did you bookmark this?"></textarea>
                        @error('hardwareBookmarkForm.notes') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="boq-modal-foot">
                    <button type="button" wire:click="closeBookmarkModal" class="boq-btn-secondary">
                        Cancel
                    </button>

                    <button type="submit" wire:loading.attr="disabled" wire:target="saveBookmark" class="boq-btn-primary">
                        <i wire:loading.remove wire:target="saveBookmark" class="fas fa-bookmark"></i>
                        <i wire:loading wire:target="saveBookmark" class="fas fa-spinner fa-spin"></i>
                        {{ $editingBookmarkId ? 'Update' : 'Save' }} Bookmark
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
