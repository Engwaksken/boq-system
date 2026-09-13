<div class="max-w-xl">
    <div class="bg-slate-50 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Change Password</h3>
        <form wire:submit.prevent="updatePassword" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Current Password</label>
                <input type="password" wire:model="passwordForm.current_password" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required autocomplete="current-password">
                @error('passwordForm.current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
                <input type="password" wire:model="passwordForm.password" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required autocomplete="new-password" minlength="8">
                @error('passwordForm.password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Confirm New Password</label>
                <input type="password" wire:model="passwordForm.password_confirmation" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required autocomplete="new-password">
                @error('passwordForm.password_confirmation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                <svg wire:loading.class="animate-spin" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                <span wire:loading.remove>Update Password</span>
                <span wire:loading>Updating...</span>
            </button>
        </form>
    </div>

    <div class="mt-6 bg-slate-50 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Two-Factor Authentication</h3>
        <p class="text-slate-600 mb-4">Two-factor authentication is not yet configured.</p>
        <button class="inline-flex items-center px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-lg transition cursor-not-allowed" disabled>
            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            Enable 2FA (Coming Soon)
        </button>
    </div>
</div>