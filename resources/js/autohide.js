/*
 * Messages marked [data-autohide="5000"] fade out after that many milliseconds.
 * Hovering or focusing a message pauses the countdown so it can be read.
 * Works for messages Livewire adds after the page has loaded.
 */
const FADE_MS = 500;

function schedule(element) {
    if (element.dataset.autohideBound) {
        return;
    }
    element.dataset.autohideBound = '1';

    const delay = Number.parseInt(element.dataset.autohide, 10);
    if (! Number.isFinite(delay) || delay <= 0) {
        return;
    }

    let timer = null;
    const start = () => {
        timer = window.setTimeout(() => {
            element.classList.add('is-fading');
            window.setTimeout(() => {
                element.style.display = 'none';
            }, FADE_MS);
        }, delay);
    };
    const pause = () => window.clearTimeout(timer);

    element.addEventListener('mouseenter', pause);
    element.addEventListener('focusin', pause);
    element.addEventListener('mouseleave', start);
    element.addEventListener('focusout', start);
    start();
}

function scan(root = document) {
    root.querySelectorAll?.('[data-autohide]').forEach(schedule);
}

document.addEventListener('DOMContentLoaded', () => {
    scan();

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) {
                    return;
                }
                if (node.matches?.('[data-autohide]')) {
                    schedule(node);
                }
                scan(node);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
});

document.addEventListener('livewire:navigated', () => scan());
