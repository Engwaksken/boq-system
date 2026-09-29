/*
 * Signature pad: draw a signature with a mouse, finger or pen.
 *
 * Markup (the pad itself is wire:ignore so Livewire never redraws the canvas):
 *
 *   <input type="hidden" id="sig-data" wire:model="drawnSignature">
 *   <div data-signature-pad data-signature-input="sig-data" wire:ignore>
 *       <canvas data-signature-canvas></canvas>
 *       <button type="button" data-signature-undo>Undo</button>
 *       <button type="button" data-signature-clear>Clear</button>
 *   </div>
 *
 * After every stroke the drawing is written to the input as a PNG data URL
 * (transparent background, dark ink) and an "input" event is fired, so it
 * works with wire:model and with plain forms. An empty pad writes "".
 * Dispatch "signature-pad-clear" on window (e.g. $this->dispatch() from
 * Livewire) to clear every pad on the page.
 *
 * Strokes are kept in coordinates relative to the pad, so resizing (or
 * rotating a phone) redraws them sharply; the canvas is sized for the
 * screen's pixel density.
 */
const INK = '#0f172a';

class SignaturePad {
    constructor(root) {
        this.root = root;
        this.canvas = root.querySelector('[data-signature-canvas]') ?? root.querySelector('canvas');
        this.ctx = this.canvas.getContext('2d');
        this.strokes = [];
        this.current = null;
        this.width = 0;
        this.height = 0;

        this.canvas.style.touchAction = 'none';
        this.canvas.addEventListener('pointerdown', (event) => this.down(event));
        this.canvas.addEventListener('pointermove', (event) => this.move(event));
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((type) => {
            this.canvas.addEventListener(type, () => this.up());
        });

        root.querySelector('[data-signature-undo]')?.addEventListener('click', () => this.undo());
        root.querySelector('[data-signature-clear]')?.addEventListener('click', () => this.clear());

        if (window.ResizeObserver) {
            new ResizeObserver(() => this.resize()).observe(this.canvas);
        } else {
            window.addEventListener('resize', () => this.resize());
        }

        this.resize();
        this.updateState();
    }

    get input() {
        const id = this.root.dataset.signatureInput;

        return id ? document.getElementById(id) : this.root.querySelector('input[type="hidden"]');
    }

    /** Thinner lines on small pads, thicker on large ones. */
    get scale() {
        return Math.min(1.4, Math.max(0.75, this.width / 520));
    }

    resize() {
        const rect = this.canvas.getBoundingClientRect();

        if (rect.width < 1 || rect.height < 1) {
            return; // hidden (e.g. in a closed tab); redrawn when it appears
        }

        const ratio = Math.max(1, Math.min(3, window.devicePixelRatio || 1));
        this.width = rect.width;
        this.height = rect.height;
        this.canvas.width = Math.round(rect.width * ratio);
        this.canvas.height = Math.round(rect.height * ratio);
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.redraw();
    }

    point(event) {
        const rect = this.canvas.getBoundingClientRect();

        return {
            x: (event.clientX - rect.left) / rect.width,
            y: (event.clientY - rect.top) / rect.height,
            time: event.timeStamp,
            pressure: event.pointerType === 'pen' && event.pressure > 0 ? event.pressure : null,
        };
    }

    down(event) {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        event.preventDefault();
        this.canvas.setPointerCapture?.(event.pointerId);

        const point = this.point(event);
        point.width = this.widthFor(point, null, null);
        this.current = { points: [point] };
        this.strokes.push(this.current);
        this.drawDot(point);
    }

    move(event) {
        if (! this.current) {
            return;
        }

        event.preventDefault();
        const events = event.getCoalescedEvents?.() ?? [event];

        for (const item of events.length ? events : [event]) {
            const point = this.point(item);
            const points = this.current.points;
            const last = points[points.length - 1];
            const dx = (point.x - last.x) * this.width;
            const dy = (point.y - last.y) * this.height;

            if (dx * dx + dy * dy < 1.5) {
                continue; // ignore jitter below ~1px
            }

            point.width = this.widthFor(point, last, Math.sqrt(dx * dx + dy * dy));
            points.push(point);
            this.drawLatest(points);
        }
    }

    up() {
        if (! this.current) {
            return;
        }

        this.current = null;
        this.commit();
    }

    /**
     * Line width from pen pressure, or from speed (faster = thinner, like ink),
     * smoothed so the width never jumps. Stored relative to the pad width.
     */
    widthFor(point, previous, distance) {
        const min = 1.1 * this.scale;
        const max = 3.4 * this.scale;
        let target;

        if (point.pressure !== null) {
            target = min + (max - min) * point.pressure;
        } else if (! previous || distance === null) {
            target = (min + max) / 2;
        } else {
            const elapsed = Math.max(1, point.time - previous.time);
            const velocity = distance / elapsed; // px per ms
            target = Math.max(min, Math.min(max, max - velocity * 0.9));
        }

        const previousWidth = previous ? previous.width * this.width : target;

        return (previousWidth * 0.65 + target * 0.35) / this.width;
    }

    toPx(point) {
        return { x: point.x * this.width, y: point.y * this.height, w: point.width * this.width };
    }

    drawDot(point) {
        const p = this.toPx(point);
        this.ctx.fillStyle = INK;
        this.ctx.beginPath();
        this.ctx.arc(p.x, p.y, p.w / 2, 0, Math.PI * 2);
        this.ctx.fill();
    }

    /** Quadratic curve through the midpoints of the last three points. */
    drawSegment(a, b, c) {
        const p0 = this.toPx(a);
        const p1 = this.toPx(b);
        const p2 = this.toPx(c);
        const ctx = this.ctx;

        ctx.strokeStyle = INK;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = p1.w;
        ctx.beginPath();
        ctx.moveTo((p0.x + p1.x) / 2, (p0.y + p1.y) / 2);
        ctx.quadraticCurveTo(p1.x, p1.y, (p1.x + p2.x) / 2, (p1.y + p2.y) / 2);
        ctx.stroke();
    }

    /** Draws the segment ending at point n (default: the newest point). */
    drawLatest(points, n = points.length) {

        if (n === 2) {
            // First move: a straight half-segment so the stroke starts at the dot.
            const a = this.toPx(points[0]);
            const b = this.toPx(points[1]);
            this.ctx.strokeStyle = INK;
            this.ctx.lineCap = 'round';
            this.ctx.lineWidth = b.w;
            this.ctx.beginPath();
            this.ctx.moveTo(a.x, a.y);
            this.ctx.lineTo((a.x + b.x) / 2, (a.y + b.y) / 2);
            this.ctx.stroke();

            return;
        }

        this.drawSegment(points[n - 3], points[n - 2], points[n - 1]);
    }

    redraw() {
        this.ctx.clearRect(0, 0, this.width, this.height);

        for (const stroke of this.strokes) {
            const points = stroke.points;
            this.drawDot(points[0]);

            for (let i = 2; i <= points.length; i++) {
                this.drawLatest(points, i);
            }

            if (points.length > 1) {
                // Finish the tail: from the last midpoint to the last point.
                const a = this.toPx(points[points.length - 2]);
                const b = this.toPx(points[points.length - 1]);
                this.ctx.strokeStyle = INK;
                this.ctx.lineWidth = b.w;
                this.ctx.beginPath();
                this.ctx.moveTo((a.x + b.x) / 2, (a.y + b.y) / 2);
                this.ctx.lineTo(b.x, b.y);
                this.ctx.stroke();
            }
        }
    }

    isEmpty() {
        return this.strokes.length === 0;
    }

    undo() {
        this.strokes.pop();
        this.redraw();
        this.commit();
    }

    clear() {
        this.strokes = [];
        this.current = null;
        this.redraw();
        this.commit();
    }

    /** Redraw cleanly (finishing stroke tails), then write the PNG to the input. */
    commit() {
        this.redraw();
        this.updateState();

        const input = this.input;
        if (! input) {
            return;
        }

        input.value = this.isEmpty() ? '' : this.canvas.toDataURL('image/png');
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    updateState() {
        const empty = this.isEmpty();
        this.root.classList.toggle('has-ink', ! empty);
        this.root.querySelectorAll('[data-signature-undo], [data-signature-clear]').forEach((button) => {
            button.disabled = empty;
        });
    }
}

function init(root = document) {
    const pads = root.matches?.('[data-signature-pad]') ? [root] : root.querySelectorAll?.('[data-signature-pad]') ?? [];

    pads.forEach((element) => {
        if (! element.signaturePad) {
            element.signaturePad = new SignaturePad(element);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    init();

    // Pads that Livewire (or Alpine) adds later, e.g. in a modal.
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    init(node);
                }
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
});

document.addEventListener('livewire:navigated', () => init());

window.addEventListener('signature-pad-clear', () => {
    document.querySelectorAll('[data-signature-pad]').forEach((element) => element.signaturePad?.clear());
});

window.boqSignaturePad = { init };
