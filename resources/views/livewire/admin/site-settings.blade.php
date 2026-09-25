<div class="min-h-screen bg-slate-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Site Settings</h1>
                <p class="mt-2 text-slate-600">Configure global system settings and defaults.</p>
            </div>
            <div>
                <a href="{{ route('admin.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-lg transition">
                    &larr; Back to Admin Panel
                </a>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <form wire:submit.prevent="save" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">System Name</label>
                        <input type="text" wire:model="settings.system_name" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. Civil Works AI BOQ Platform">
                        @error('settings.system_name') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default Currency</label>
                        <input type="text" wire:model="settings.currency" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. UGX">
                        @error('settings.currency') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default Language</label>
                        <input type="text" wire:model="settings.language" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. en">
                        @error('settings.language') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Trial Duration (Days)</label>
                        <input type="number" wire:model="settings.trial_duration" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="14">
                        @error('settings.trial_duration') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Logo</label>
                        <div class="flex items-center gap-3 mb-2">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Logo" class="w-16 h-16 object-contain p-1 border border-slate-200 rounded-lg bg-white">
                            @else
                                <div class="w-16 h-16 flex items-center justify-center rounded-lg bg-slate-100 text-slate-400 text-xs">No logo</div>
                            @endif
                            <div class="flex flex-col gap-1.5">
                                <label class="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg cursor-pointer">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1.5M3 14l4.5-4.5 4 4L15 10l6 5.5"/></svg>
                                    Choose logo
                                    <input type="file" wire:model="logoFile" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden">
                                </label>
                                @if($logoUrl)
                                    <button type="button" wire:click="removeLogo" class="inline-flex items-center px-3 py-1.5 text-red-600 hover:bg-red-50 text-sm font-semibold rounded-lg">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-8 0 1 13h8l1-13"/></svg>
                                        Remove
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div wire:loading wire:target="logoFile" class="text-xs text-indigo-600 mb-1">Uploading logo...</div>
                        @error('logoFile') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        <input type="hidden" wire:model="settings.logo">
                        <p class="text-xs text-slate-500">Max 2MB. Supported: PNG, JPG, JPEG, WEBP, SVG</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Favicon</label>
                        <div class="flex items-center gap-3 mb-2">
                            @if($faviconUrl)
                                <img src="{{ $faviconUrl }}" alt="Favicon" class="w-10 h-10 object-contain p-1 border border-slate-200 rounded-lg bg-white">
                            @else
                                <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-slate-100 text-slate-400 text-xs">No icon</div>
                            @endif
                            <div class="flex flex-col gap-1.5">
                                <label class="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg cursor-pointer">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1.5M3 14l4.5-4.5 4 4L15 10l6 5.5"/></svg>
                                    Choose favicon
                                    <input type="file" wire:model="faviconFile" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon" class="hidden">
                                </label>
                                @if($faviconUrl)
                                    <button type="button" wire:click="removeFavicon" class="inline-flex items-center px-3 py-1.5 text-red-600 hover:bg-red-50 text-sm font-semibold rounded-lg">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-8 0 1 13h8l1-13"/></svg>
                                        Remove
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div wire:loading wire:target="faviconFile" class="text-xs text-indigo-600 mb-1">Uploading favicon...</div>
                        @error('faviconFile') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        <input type="hidden" wire:model="settings.favicon">
                        <p class="text-xs text-slate-500">Max 1MB. Supported: PNG, JPG, WEBP, SVG, ICO</p>
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="settings.maintenance_mode" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 h-4 w-4">
                            <span class="ml-2 text-sm font-medium text-slate-700">Enable Maintenance Mode</span>
                        </label>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Mobile App</h2>
                    <p class="text-sm text-slate-600 mb-5">Controls the splash screen and login screen shown in the Flutter app.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="settings.splash_enabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 h-4 w-4">
                                <span class="ml-2 text-sm font-medium text-slate-700">Show splash screen on launch</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Splash Duration (milliseconds)</label>
                            <input type="number" wire:model="settings.splash_duration" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="2000">
                            @error('settings.splash_duration') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Splash Message</label>
                            <input type="text" wire:model="settings.splash_message" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. Civil Works Solutions">
                            @error('settings.splash_message') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Login Title</label>
                            <input type="text" wire:model="settings.login_title" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Optional - overrides the app title">
                            @error('settings.login_title') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Login Subtitle</label>
                            <input type="text" wire:model="settings.login_subtitle" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Optional - shown under the login title">
                            @error('settings.login_subtitle') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Registration &amp; Access</h2>
                    <p class="text-sm text-slate-600 mb-5">Which options appear on the mobile login screen.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="settings.allow_registration" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 h-4 w-4">
                                <span class="ml-2 text-sm font-medium text-slate-700">Allow account sign up</span>
                            </label>
                        </div>

                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="settings.allow_forgot_password" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 h-4 w-4">
                                <span class="ml-2 text-sm font-medium text-slate-700">Allow forgot password</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Legal</h2>
                    <p class="text-sm text-slate-600 mb-5">Shown as links on the login screen. Leave blank to hide a link. If the text is a URL, the app opens it in the browser; otherwise plain text is shown in the app.</p>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Privacy Policy</label>
                            <textarea wire:model="settings.privacy_policy" rows="5" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Paste the privacy policy text or a full URL"></textarea>
                            @error('settings.privacy_policy') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Terms of Use</label>
                            <textarea wire:model="settings.terms_of_use" rows="5" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Paste the terms of use text or a full URL"></textarea>
                            @error('settings.terms_of_use') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-200">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg shadow-sm transition text-sm">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>