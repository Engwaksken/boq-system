<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'BOQ System' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 bg-slate-50">
        <a href="{{ url('/') }}" class="flex items-center gap-2 mb-6">
            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-600">
                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
            </span>
            <span class="text-2xl font-bold text-gray-900">BOQ System</span>
        </a>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 w-full max-w-md">
            {{ $slot }}
        </div>

        <p class="mt-6 text-xs text-gray-400">&copy; {{ date('Y') }} BOQ System</p>
    </div>
</body>
</html>
