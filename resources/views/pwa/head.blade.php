{{-- Progressive Web App: install on phones and desktops, offline page. --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<link rel="apple-touch-icon" href="{{ \App\Support\PwaIcons::url('apple-touch-icon') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ \App\Models\SiteSetting::get('system_name', 'BOQ System') ?: 'BOQ System' }}">
<meta name="pwa-sw" content="{{ route('pwa.sw') }}">
