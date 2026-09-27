<div
    class="max-w-2xl space-y-6"
    x-data="{ saved: false }"
    x-on:notification-preferences-saved.window="saved = true; setTimeout(() => saved = false, 2000)"
>
    <div class="boq-panel boq-panel-body">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h3 class="boq-section-title"><i class="fas fa-bell"></i> {{ __('Notification Preferences') }}</h3>
                <p class="boq-section-subtitle">{{ __('Choose how you want to be notified about important events. Changes save automatically.') }}</p>
            </div>
            <span x-show="saved" x-transition class="boq-badge boq-badge-success" role="status">
                <i class="fas fa-check"></i> {{ __('Saved') }}
            </span>
        </div>

        <div class="space-y-3">
            @foreach([
                'email_project_updates' => ['fa-envelope', 'Email', 'Project updates', 'Receive email when projects are updated'],
                'email_boq_changes' => ['fa-envelope', 'Email', 'BOQ changes', 'Get notified when BOQs are modified'],
                'email_price_alerts' => ['fa-envelope', 'Email', 'Price alerts', 'Alerts when hardware prices change significantly'],
                'in_app_project_updates' => ['fa-bell', 'In-app', 'Project updates', 'See project updates in the notification centre'],
                'in_app_boq_changes' => ['fa-bell', 'In-app', 'BOQ changes', 'See BOQ changes in the notification centre'],
                'in_app_approvals' => ['fa-bell', 'In-app', 'Approvals', 'Notifications when items need your approval'],
            ] as $key => [$icon, $channel, $title, $description])
                <label for="pref-{{ $key }}" class="boq-toggle-row" wire:key="pref-{{ $key }}">
                    <span class="flex items-center gap-3">
                        <span class="boq-stat-icon"><i class="fas {{ $icon }}"></i></span>
                        <span>
                            <span class="block font-semibold text-slate-900">{{ $title }} <span class="text-xs font-medium text-slate-400">· {{ $channel }}</span></span>
                            <span class="block text-sm text-slate-500">{{ $description }}</span>
                        </span>
                    </span>

                    <input
                        id="pref-{{ $key }}"
                        type="checkbox"
                        role="switch"
                        class="boq-switch"
                        wire:model.live="notificationPrefs.{{ $key }}"
                    >
                </label>
            @endforeach
        </div>
    </div>

    <div class="boq-panel boq-panel-body">
        <h3 class="boq-section-title mb-3"><i class="fas fa-clock"></i> {{ __('Notification Frequency') }}</h3>

        <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="{{ __('Notification frequency') }}">
            @foreach([
                'immediate' => ['Immediate', 'As soon as something happens'],
                'hourly' => ['Hourly digest', 'One summary every hour'],
                'daily' => ['Daily digest', 'One summary each day'],
                'weekly' => ['Weekly digest', 'One summary each week'],
            ] as $value => [$label, $hint])
                <label class="boq-radio-card" wire:key="freq-{{ $value }}">
                    <input type="radio" value="{{ $value }}" wire:model.live="notificationPrefs.frequency">
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">{{ $label }}</span>
                        <span class="block text-xs text-slate-500">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>
</div>
