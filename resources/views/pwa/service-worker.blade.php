/* BOQ System service worker, version {{ $version }}.
 * - The app's CSS/JS, icons and fonts are cached so pages open fast.
 * - Pages and data always come from the network (prices and BOQs change), with
 *   an offline page when there is no connection.
 * - Sign-in, Livewire, API, uploads and every non-GET request are never cached.
 */
const VERSION = @json($version);
const STATIC_CACHE = `boq-static-${VERSION}`;
const RUNTIME_CACHE = `boq-runtime-${VERSION}`;
const PRECACHE = {!! json_encode($precache, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
const OFFLINE_URL = '/offline';

const NEVER_CACHE = [
    /^\/livewire/,
    /^\/api\//,
    /^\/webauthn/,
    /^\/(login|logout|register|forgot-password|reset-password|two-factor)/,
    /^\/sw\.js/,
    /^\/storage\//,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith('boq-') && ! key.endsWith(VERSION)).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'skip-waiting') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    const sameOrigin = url.origin === self.location.origin;

    // Fonts and icon styles from the CDNs: cached after the first visit.
    if (! sameOrigin) {
        if (/fonts\.bunny\.net|cdnjs\.cloudflare\.com/.test(url.host)) {
            event.respondWith(staleWhileRevalidate(request));
        }

        return;
    }

    if (NEVER_CACHE.some((pattern) => pattern.test(url.pathname))) {
        return;
    }

    // Compiled assets and icons never change for a given file name.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/pwa/icons/')) {
        event.respondWith(cacheFirst(request));

        return;
    }

    // Pages: always fresh from the network; the offline page when offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => (await caches.match(OFFLINE_URL)) || Response.error())
        );
    }
});

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(STATIC_CACHE);
        cache.put(request, response.clone());
    }

    return response;
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((response) => {
            if (response.ok || response.type === 'opaque') {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => cached);

    return cached || network;
}
