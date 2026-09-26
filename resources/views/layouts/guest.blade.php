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

    <style>
        [x-cloak] {
            display: none !important;
        }

        body {
            background:
                radial-gradient(
                    circle at top left,
                    rgba(5, 100, 91, .12),
                    transparent 36%
                ),
                linear-gradient(
                    135deg,
                    #f8fafc 0%,
                    #f1f5f9 100%
                );
        }

        .auth-shell {
            width: 100%;
            max-width: 460px;
        }

        .auth-brand-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: #05645b;
            color: #fff;
            box-shadow:
                0 8px 22px
                rgba(5, 100, 91, .18);
        }

        .auth-card {
            border: 1px solid #e2e8f0;
            border-top: 4px solid #05645b;
            border-radius: 16px;
            background: rgba(255, 255, 255, .98);
            box-shadow:
                0 18px 45px
                rgba(15, 23, 42, .08);
        }

        .auth-field {
            width: 100%;
            min-height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            padding: 0 14px;
            color: #0f172a;
            font-size: 14px;
            transition:
                border-color .2s ease,
                box-shadow .2s ease,
                background .2s ease;
        }

        .auth-field::placeholder {
            color: #94a3b8;
        }

        .auth-field:focus {
            outline: none;
            border-color: #05645b;
            box-shadow:
                0 0 0 3px
                rgba(5, 100, 91, .12);
        }

        .auth-field.has-error {
            border-color: #dc2626;
        }

        .auth-field.has-error:focus {
            box-shadow:
                0 0 0 3px
                rgba(220, 38, 38, .10);
        }

        .auth-input-wrap {
            position: relative;
        }

        .auth-input-wrap .auth-field {
            padding-left: 42px;
        }

        .auth-input-wrap.has-toggle .auth-field {
            padding-right: 46px;
        }

        .auth-input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }

        .auth-password-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            display: inline-flex;
            width: 34px;
            height: 34px;
            transform: translateY(-50%);
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #64748b;
            transition:
                background .2s ease,
                color .2s ease;
        }

        .auth-password-toggle:hover {
            background: #f1f5f9;
            color: #05645b;
        }

        .auth-primary {
            display: inline-flex;
            width: 100%;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 10px;
            background: #05645b;
            padding: 0 18px;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            transition:
                background .2s ease,
                transform .2s ease,
                box-shadow .2s ease;
        }

        .auth-primary:hover {
            background: #034f48;
            box-shadow:
                0 8px 20px
                rgba(5, 100, 91, .18);
        }

        .auth-primary:focus-visible {
            outline: 3px solid
                rgba(215, 223, 33, .45);
            outline-offset: 2px;
        }

        .auth-link {
            color: #05645b;
            font-weight: 600;
        }

        .auth-link:hover {
            text-decoration: underline;
        }

        .auth-error {
            margin-top: 6px;
            color: #dc2626;
            font-size: 12px;
        }

        .auth-status {
            margin-bottom: 18px;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            background: #ecfdf5;
            padding: 12px 14px;
            color: #047857;
            font-size: 13px;
        }
    </style>
</head>

<body class="min-h-full antialiased">

    <main
        class="min-h-screen flex items-center justify-center px-4 py-10"
    >

        <div class="auth-shell">

            {{-- Brand --}}
            <a
                href="{{ url('/') }}"
                class="mb-6 flex items-center justify-center gap-3"
            >

                @if($siteLogo)

                    <img
                        src="{{ asset('storage/'.$siteLogo) }}"
                        alt="{{ $siteName }}"
                        class="h-12 w-12 rounded-xl object-contain"
                    >

                @else

                    <span class="auth-brand-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </span>

                @endif

                <span
                    class="text-2xl font-extrabold tracking-tight text-slate-900"
                >
                    {{ $siteName }}
                </span>

            </a>


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