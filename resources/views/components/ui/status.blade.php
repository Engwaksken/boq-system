{{--
    Status pill for any model status string (active, pending, cancelled, ...).
    Colour is derived from the status; the label is the humanised, translated status
    unless a slot is given.
--}}
@props(['status' => null])

@php
    $key = strtolower((string) $status);

    $color = match (true) {
        in_array($key, ['active', 'paid', 'success', 'successful', 'completed', 'approved', 'verified', 'accepted', 'priced', 'processed', 'enabled', 'published', 'ready', 'done', 'analysed', 'analyzed', 'succeeded'], true) => 'success',
        in_array($key, ['pending', 'processing', 'queued', 'running', 'on_hold', 'on-hold', 'trial', 'trialing', 'draft', 'in_review', 'pending_payment', 'awaiting_payment', 'partial', 'importing', 'under_review', 'completed_with_errors', 'grace'], true) => 'warning',
        in_array($key, ['cancelled', 'canceled', 'failed', 'rejected', 'expired', 'overdue', 'inactive', 'disabled', 'error', 'refunded', 'suspended', 'blocked'], true) => 'danger',
        in_array($key, ['info', 'new', 'sent', 'submitted', 'uploaded', 'extracted', 'reviewed'], true) => 'info',
        default => 'neutral',
    };

    $label = $key === '' ? __('Unknown') : __(ucwords(str_replace(['_', '-'], ' ', $key)));
@endphp

<x-ui.badge :color="$color" dot {{ $attributes }}>
    {{ $slot->isEmpty() ? $label : $slot }}
</x-ui.badge>
