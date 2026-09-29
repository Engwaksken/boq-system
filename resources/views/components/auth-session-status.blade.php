@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }} data-autohide="5000">
        {{ $status }}
    </div>
@endif
