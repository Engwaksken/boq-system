@props(['value' => null, 'time' => false])
<time {{ $attributes }} @if($value) datetime="{{ \Illuminate\Support\Carbon::parse($value)->toIso8601String() }}" @endif>{{ \App\Support\Format::date($value, $time) ?? '—' }}</time>
