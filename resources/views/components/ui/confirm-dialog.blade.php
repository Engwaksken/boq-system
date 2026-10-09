{{--
    Global styled confirmation dialog. Replaces the browser's native confirm()
    used by wire:confirm; resources/js/app.js swaps Livewire's per-element
    handler for one that opens this dialog instead. Included once per layout.
--}}
<div
    x-data="{
        open: false,
        message: '',
        onConfirm: null,
        onCancel: null,
        offer(detail) {
            this.message = detail.message || 'Are you sure?';
            this.onConfirm = detail.action;
            this.onCancel = detail.instead;
            this.open = true;
        },
        accept() {
            const run = this.onConfirm;
            this.dismiss();
            if (run) run();
        },
        reject() {
            const run = this.onCancel;
            this.dismiss();
            if (run) run();
        },
        dismiss() {
            this.open = false;
            this.onConfirm = null;
            this.onCancel = null;
        },
    }"
    x-on:boq-confirm.window="offer($event.detail)"
    x-cloak
>
    <div x-show="open" x-trap.noscroll="open" class="boq-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="boq-confirm-title" @keydown.escape.window="reject()">
        <div class="boq-modal boq-modal-sm">
            <div class="boq-modal-head">
                <h2 id="boq-confirm-title">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    {{ __('Please confirm') }}
                </h2>
            </div>
            <div class="boq-modal-body">
                <p class="boq-modal-message" x-text="message"></p>
            </div>
            <div class="boq-modal-foot">
                <button type="button" class="boq-btn-secondary" @click="reject()">{{ __('Cancel') }}</button>
                <button type="button" class="boq-btn-danger" @click="accept()">{{ __('Confirm') }}</button>
            </div>
        </div>
    </div>
</div>
