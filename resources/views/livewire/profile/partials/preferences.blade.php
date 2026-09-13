<div class="grid gap-6 sm:grid-cols-2">
    <div class="bg-slate-50 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Display Preferences</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Theme</label>
                <select class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option>System Default</option>
                    <option>Light</option>
                    <option>Dark</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Language</label>
                <select class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="en" {{ Auth::user()->locale === 'en' ? 'selected' : '' }}>English</option>
                    <option value="fr" {{ Auth::user()->locale === 'fr' ? 'selected' : '' }}>French</option>
                    <option value="sw" {{ Auth::user()->locale === 'sw' ? 'selected' : '' }}>Swahili</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date Format</label>
                <select class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option>DD/MM/YYYY</option>
                    <option>MM/DD/YYYY</option>
                    <option>YYYY-MM-DD</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Number Format</label>
                <select class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option>1,234.56</option>
                    <option>1.234,56</option>
                    <option>1 234,56</option>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-slate-50 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Default Values</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Default Currency</label>
                <select class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="UGX" {{ Auth::user()->currency === 'UGX' ? 'selected' : '' }}>UGX</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="KES">KES</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Items Per Page</label>
                <select class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>
</div>