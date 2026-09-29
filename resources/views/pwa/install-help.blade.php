{{-- How to install on iPhone/iPad (Safari has no install prompt). Opened by [data-pwa-install]. --}}
<dialog data-pwa-ios-help class="boq-pwa-dialog" aria-labelledby="pwa-ios-title">
    <form method="dialog">
        <div class="boq-pwa-dialog-icon"><img src="{{ asset('icons/icon-192.png') }}" alt=""></div>
        <h2 id="pwa-ios-title">{{ __('Install the app') }}</h2>
        <ol>
            <li>{!! __('Tap the :icon Share button in Safari.', ['icon' => '<i class="fas fa-arrow-up-from-bracket" aria-hidden="true"></i>']) !!}</li>
            <li>{{ __('Choose "Add to Home Screen".') }}</li>
            <li>{{ __('Tap "Add". The app opens from your home screen like any other app.') }}</li>
        </ol>
        <button type="submit" class="boq-pwa-dialog-close">{{ __('Got it') }}</button>
    </form>
</dialog>
