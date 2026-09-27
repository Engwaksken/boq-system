{{-- Password field with a show/hide toggle (handled in resources/js/app.js). --}}
<div class="boq-password-field">
    <input type="password" {{ $attributes->merge(['class' => 'boq-field']) }}>

    <button type="button" class="boq-password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
        <i class="fas fa-eye"></i>
    </button>
</div>
