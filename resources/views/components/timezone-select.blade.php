{{-- Every IANA timezone, grouped by region, with the current UTC offset. --}}
@php
    $groups = collect(timezone_identifiers_list())
        ->groupBy(fn (string $tz) => str_contains($tz, '/') ? strstr($tz, '/', true) : 'Other');
@endphp

<select {{ $attributes->merge(['class' => 'boq-field']) }}>
    @foreach($groups as $region => $zones)
        <optgroup label="{{ $region }}">
            @foreach($zones as $tz)
                <option value="{{ $tz }}">{{ str_replace(['_', '/'], [' ', ' / '], $tz) }} (UTC{{ now($tz)->format('P') }})</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
