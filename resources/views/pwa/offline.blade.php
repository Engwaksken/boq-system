<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#05645b">
    <title>{{ __('You are offline') }} | {{ $name }}</title>
    <link rel="icon" href="/icons/icon-192.png">
    {{-- Self-contained: this page is shown when nothing else can load. --}}
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f4f7f6; color: #0f172a;
        }
        .card {
            width: 100%; max-width: 420px; text-align: center; background: #fff;
            border-radius: 18px; padding: 36px 28px; box-shadow: 0 10px 30px rgb(15 23 42 / 0.08);
            border-left: 4px solid #05645b;
        }
        img { width: 72px; height: 72px; border-radius: 18px; }
        h1 { margin: 18px 0 8px; font-size: 1.35rem; }
        p { margin: 0 0 22px; color: #475569; line-height: 1.5; }
        button {
            width: 100%; border: 0; border-radius: 12px; padding: 13px 18px; cursor: pointer;
            background: #05645b; color: #fff; font-size: 1rem; font-weight: 600;
        }
        button:focus-visible { outline: 3px solid #86cfc5; outline-offset: 2px; }
        @media (prefers-color-scheme: dark) {
            body { background: #0b1220; color: #e2e8f0; }
            .card { background: #111a2e; box-shadow: none; }
            p { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <main class="card">
        <img src="/icons/icon-192.png" alt="">
        <h1>{{ __('You are offline') }}</h1>
        <p>{{ __('Check your Wi-Fi or mobile data. :name continues as soon as you are back online.', ['name' => $name]) }}</p>
        <button type="button" onclick="location.reload()">{{ __('Try again') }}</button>
    </main>
    <script>
        window.addEventListener('online', () => location.reload());
    </script>
</body>
</html>
