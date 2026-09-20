<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $siteName = App\Models\SiteSetting::get('system_name', 'BOQ System');
        $siteLogo = App\Models\SiteSetting::get('logo', '');
        $siteFavicon = App\Models\SiteSetting::get('favicon', '');
    @endphp
    <title>{{ ($title ?? '') !== '' ? $title.' · ' : '' }}{{ $siteName }}</title>
    @if($siteFavicon)
        <link rel="icon" type="image/x-icon" href="{{ asset('storage/'.$siteFavicon) }}">
    @else
        <link rel="icon" href="/favicon.ico">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 bg-slate-50">
        <a href="{{ url('/') }}" class="flex items-center gap-2 mb-6">
            @if($siteLogo)
                <img src="{{ asset('storage/'.$siteLogo) }}" alt="Logo" class="w-10 h-10 rounded-lg object-contain">
            @else
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-600">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                </span>
            @endif
            <span class="text-2xl font-bold text-gray-900">{{ $siteName }}</span>
        </a>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 w-full max-w-md">
            {{ $slot }}
        </div>

        <p class="mt-6 text-xs text-gray-400">&copy; {{ date('Y') }} {{ $siteName }}</p>
    </div>
</body>
</html>
