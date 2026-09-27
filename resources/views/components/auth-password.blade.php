@props(['name', 'label', 'icon' => 'fa-lock', 'autocomplete' => 'current-password', 'placeholder' => ''])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>

    <div class="auth-input-wrap has-toggle">
        <i class="fas {{ $icon }} auth-input-icon"></i>

        <input
            id="{{ $name }}"
            type="password"
            name="{{ $name }}"
            required
            autocomplete="{{ $autocomplete }}"
            placeholder="{{ $placeholder }}"
            {{ $attributes->class(['auth-field', 'has-error' => $errors->has($name)]) }}
        >

        <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
            <i class="fas fa-eye"></i>
        </button>
    </div>

    @error($name)
        <p class="auth-error"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>
    @enderror
</div>
