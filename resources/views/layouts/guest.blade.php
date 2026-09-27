<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full"
>
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <meta
        name="theme-color"
        content="#05645b"
    >

    @php
        $siteName = \App\Models\SiteSetting::get(
            'system_name',
            'BOQ System'
        );

        $siteLogo = \App\Models\SiteSetting::get(
            'logo',
            ''
        );

        $siteFavicon = \App\Models\SiteSetting::get(
            'favicon',
            ''
        );
    @endphp

    <title>
        {{ ($title ?? '') !== ''
            ? $title.' · '
            : ''
        }}{{ $siteName }}
    </title>

    @if($siteFavicon)

        <link
            rel="icon"
            href="{{ asset('storage/'.$siteFavicon) }}"
        >

    @else

        <link
            rel="icon"
            href="{{ asset('favicon.ico') }}"
        >

    @endif

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

</head>

<body class="boq-auth-shell min-h-full antialiased">

    <main
        class="min-h-screen flex items-center justify-center px-4 py-10"
    >

        <div class="auth-shell">

            {{-- Card --}}
            <section
                class="auth-card w-full p-6 sm:p-8"
            >
                {{ $slot }}
            </section>


            {{-- Footer --}}
            <p
                class="mt-6 text-center text-xs text-slate-500"
            >
                © {{ date('Y') }}
                {{ $siteName }}.
                All rights reserved.
            </p>

        </div>

    </main>

</body>
</html>