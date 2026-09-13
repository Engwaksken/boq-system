<div class="max-w-xl">
    <div class="bg-slate-50 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Notification Preferences</h3>
        <p class="text-slate-600 mb-4">Choose how you want to be notified about important events.</p>

        <div class="space-y-4">
            @foreach([
                'email_project_updates' => ['Email', 'Project updates', 'Receive email when projects are updated'],
                'email_boq_changes' => ['Email', 'BOQ changes', 'Get notified when BOQs are modified'],
                'email_price_alerts' => ['Email', 'Price alerts', 'Alerts when hardware prices change significantly'],
                'in_app_project_updates' => ['In-App', 'Project updates', 'See project updates in notification center'],
                'in_app_boq_changes' => ['In-App', 'BOQ changes', 'See BOQ changes in notification center'],
                'in_app_approvals' => ['In-App', 'Approvals', 'Notifications when items need your approval'],
            ] as $key => [$type, $title, $description])
                <div class="flex items-center justify-between p-4 bg-white rounded-lg border border-slate-200">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-{{ $type === 'Email' ? 'indigo' : 'emerald' }}-100 text-{{ $type === 'Email' ? 'indigo' : 'emerald' }}-600 text-xs font-semibold">{{ strtoupper(substr($type, 0, 1)) }}</span>
                        <div>
                            <p class="font-medium text-slate-900">{{ $title }}</p>
                            <p class="text-sm text-slate-500">{{ $description }}</p>
                        </div>
                    </div>
                    <button class="relative inline-flex h-6 w-11 items-center rounded-full bg-slate-200 transition-colors" aria-label="Toggle">
                        <span class="inline-block h-4 w-4 transform bg-white rounded-full transition-transform"></span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-slate-50 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Notification Frequency</h3>
        <div class="space-y-3">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="frequency" value="immediate" class="h-4 w-4 text-indigo-600 border-slate-300 focus:ring-indigo-500" checked>
                <span class="text-sm font-medium text-slate-700">Immediate</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="frequency" value="hourly" class="h-4 w-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                <span class="text-sm font-medium text-slate-700">Hourly Digest</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="frequency" value="daily" class="h-4 w-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                <span class="text-sm font-medium text-slate-700">Daily Digest</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="radio" name="frequency" value="weekly" class="h-4 w-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                <span class="text-sm font-medium text-slate-700">Weekly Digest</span>
            </label>
        </div>
    </div>
</div>