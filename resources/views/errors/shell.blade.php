{{--
    Self-contained shell for error and maintenance pages. It must render when the
    app is broken: no Vite assets, and every database lookup is optional.
--}}
@php
    try {
        $errorSiteName = \App\Models\SiteSetting::get('system_name', config('app.name', 'BOQ System')) ?: config('app.name', 'BOQ System');
    } catch (\Throwable) {
        $errorSiteName = config('app.name', 'BOQ System');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#05645b">
    <title>{{ $pageTitle }} · {{ $errorSiteName }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root { --brand: #05645b; --brand-dark: #044f48; --brand-soft: #eef8f6; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 2.5rem 1rem;
            background: radial-gradient(circle at top left, rgba(5, 100, 91, .10), transparent 40%), #f5f7f9;
            color: var(--text); font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        main { width: 100%; max-width: 440px; }
        .card { border: 1px solid var(--border); border-radius: 1rem; background: #fff; padding: 2rem; text-align: center; box-shadow: 0 8px 24px rgba(15, 23, 42, .08); }
        .badge { display: inline-flex; width: 3rem; height: 3rem; align-items: center; justify-content: center; margin-bottom: 1rem; border-radius: .8rem; background: var(--brand-soft); color: var(--brand); font-size: 1.1rem; }
        .eyebrow { margin: 0; color: var(--brand); font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: .35rem 0 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -.01em; line-height: 1.25; }
        p.message { margin: .85rem 0 0; color: var(--muted); font-size: .9rem; line-height: 1.6; }
        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .6rem; margin-top: 1.75rem; }
        .btn { display: inline-flex; min-height: 2.75rem; align-items: center; justify-content: center; gap: .5rem; border: 1px solid transparent; border-radius: .5rem; padding: 0 1.1rem; font-size: .9rem; font-weight: 600; text-decoration: none; cursor: pointer; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-secondary { border-color: #cbd5e1; background: #fff; color: #334155; }
        .btn-secondary:hover { border-color: var(--brand); color: var(--brand-dark); }
        .btn:focus-visible, a:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
        .link { color: var(--brand); font-weight: 600; text-decoration: none; }
        footer { margin-top: 1.5rem; color: var(--muted); font-size: .75rem; text-align: center; }
    </style>
</head>
<body>
    <main>
        <section class="card">
            {{ $slot }}
        </section>

        <footer>&copy; {{ date('Y') }} {{ $errorSiteName }}</footer>
    </main>
</body>
</html>
