/*
 * Progressive Web App: registers the service worker and powers the
 * [data-pwa-install] buttons ("Install app").
 *
 * - Chrome, Edge, Samsung Internet and other Chromium browsers fire
 *   `beforeinstallprompt`; the buttons appear and open the install prompt.
 * - Safari on iPhone/iPad has no prompt: the buttons open short
 *   "Share > Add to Home Screen" instructions ([data-pwa-ios-help]).
 * - Once installed (or opened as the app) the buttons stay hidden.
 */

let deferredPrompt = null;

const isStandalone = () =>
    window.matchMedia?.('(display-mode: standalone)').matches
    || window.matchMedia?.('(display-mode: window-controls-overlay)').matches
    || window.navigator.standalone === true;

const isIosSafari = () => {
    const ua = window.navigator.userAgent;
    const ios = /iPad|iPhone|iPod/.test(ua) || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);

    return ios && /Safari/.test(ua) && ! /CriOS|FxiOS|EdgiOS/.test(ua);
};

function buttons() {
    return document.querySelectorAll('[data-pwa-install]');
}

function updateButtons() {
    const show = ! isStandalone() && (deferredPrompt !== null || isIosSafari());

    buttons().forEach((button) => {
        button.hidden = ! show;
    });
}

async function install() {
    if (deferredPrompt) {
        const prompt = deferredPrompt;
        deferredPrompt = null;
        prompt.prompt();

        try {
            await prompt.userChoice;
        } finally {
            updateButtons();
        }

        return;
    }

    const help = document.querySelector('[data-pwa-ios-help]');
    if (help?.showModal) {
        help.showModal();
    }
}

window.addEventListener('beforeinstallprompt', (event) => {
    // Show our own button instead of the browser's mini info bar.
    event.preventDefault();
    deferredPrompt = event;
    updateButtons();
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    updateButtons();
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-pwa-install]')) {
        event.preventDefault();
        install();
    }
});

document.addEventListener('DOMContentLoaded', updateButtons);
document.addEventListener('livewire:navigated', updateButtons);

window.boqPwa = { install, isStandalone };

function registerServiceWorker() {
    const url = document.querySelector('meta[name="pwa-sw"]')?.content;

    if (! url || ! ('serviceWorker' in navigator) || ! window.isSecureContext) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register(url, { scope: '/' }).then((registration) => {
            // A new version was deployed: activate it and reload once it takes over.
            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;

                worker?.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        worker.postMessage('skip-waiting');
                    }
                });
            });
        }).catch(() => {
            // The site keeps working without the service worker.
        });

        // Only reload when an older version was in control (not on the first visit).
        const hadController = Boolean(navigator.serviceWorker.controller);
        let reloading = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (hadController && ! reloading) {
                reloading = true;
                window.location.reload();
            }
        });
    });
}

registerServiceWorker();
