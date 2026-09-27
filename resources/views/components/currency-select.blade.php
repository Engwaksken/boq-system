@props(['current' => null])

@php
    $codes = \App\Models\Currency::activeCodes();

    // Keep a legacy value selectable even if that currency was later deactivated.
    if (filled($current) && ! in_array(strtoupper((string) $current), $codes, true)) {
        $codes[] = strtoupper((string) $current);
    }
@endphp

<select {{ $attributes->merge(['class' => 'boq-field']) }}>
    @foreach($codes as $code)
        <option value="{{ $code }}">{{ $code }}</option>
    @endforeach
</select>
