<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#05645b">

    @php
        $siteName = \App\Models\SiteSetting::get('system_name', 'BOQ System') ?: 'BOQ System';
        $siteLogo = \App\Models\SiteSetting::get('logo', '');
        $siteFavicon = \App\Models\SiteSetting::get('favicon', '');
        $wide = $wide ?? false;
    @endphp

    <title>{{ ($title ?? '') !== '' ? $title.' · ' : '' }}{{ $siteName }}</title>

    <link rel="icon" href="{{ $siteFavicon ? asset('storage/'.$siteFavicon) : asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="boq-auth-shell min-h-full antialiased">

    <div class="auth-layout">

        {{-- Brand panel (desktop only) --}}
        <aside class="auth-aside" aria-hidden="true">
            <div class="auth-aside-brand">
                <span class="auth-aside-mark">
                    @if($siteLogo)
                        <img src="{{ asset('storage/'.$siteLogo) }}" alt="">
                    @else
                        <i class="fas fa-file-invoice-dollar"></i>
                    @endif
                </span>
                <span>{{ $siteName }}</span>
            </div>

            <div>
                <p class="auth-aside-title">{{ __('Plan, price and deliver construction projects with confidence.') }}</p>
                <p class="auth-aside-copy">{{ __('Bills of quantities, live hardware and factory prices, and project costs in one workspace.') }}</p>

                <ul class="auth-aside-points">
                    <li><i class="fas fa-file-invoice-dollar"></i> {{ __('Upload a BOQ and price every item against current market rates.') }}</li>
                    <li><i class="fas fa-tags"></i> {{ __('Compare hardware and factory prices by location and brand.') }}</li>
                    <li><i class="fas fa-file-pdf"></i> {{ __('Share branded BOQ PDFs with clients in one click.') }}</li>
                </ul>
            </div>

            <p class="text-xs text-white/60">&copy; {{ date('Y') }} {{ $siteName }}</p>
        </aside>

        <main class="auth-main">
            <div class="auth-shell {{ $wide ? 'max-w-3xl' : '' }}">

                <section class="auth-card w-full p-6 sm:p-8">
                    {{ $slot }}
                </section>

                {{-- Language switcher (guests; signed-in users choose in Profile > Preferences) --}}
                @php
                    $guestLanguages = \App\Models\Language::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['code', 'native_name']);
                @endphp

                @if($guestLanguages->count() > 1)
                    <nav class="mt-5 flex flex-wrap justify-center gap-3 text-xs" aria-label="{{ __('Language') }}">
                        @foreach($guestLanguages as $guestLanguage)
                            <a
                                href="{{ request()->fullUrlWithQuery(['lang' => $guestLanguage->code]) }}"
                                class="auth-link {{ app()->getLocale() === $guestLanguage->code ? 'font-extrabold text-slate-900' : 'font-medium text-slate-500' }}"
                                hreflang="{{ $guestLanguage->code }}"
                                @if(app()->getLocale() === $guestLanguage->code) aria-current="true" @endif
                            >{{ $guestLanguage->native_name }}</a>
                        @endforeach
                    </nav>
                @endif

                <p class="mt-6 text-center text-xs text-slate-500">
                    &copy; {{ date('Y') }} {{ $siteName }}. {{ __('All rights reserved.') }}
                    <span class="mx-1" aria-hidden="true">&middot;</span>
                    <a href="{{ route('legal.privacy') }}" class="auth-link font-medium">{{ __('Privacy Policy') }}</a>
                    <span class="mx-1" aria-hidden="true">&middot;</span>
                    <a href="{{ route('legal.terms') }}" class="auth-link font-medium">{{ __('Terms of Use') }}</a>
                </p>

            </div>
        </main>
    </div>

</body>
</html>
