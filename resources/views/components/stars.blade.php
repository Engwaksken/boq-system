{{--
    Read-only star rating. value: 0..5 (halves shown), count: optional number of ratings.
--}}
@props(['value' => 0, 'count' => null, 'size' => 'sm', 'showValue' => true])

@php
    $value = max(0, min(5, (float) $value));
    $rounded = round($value * 2) / 2;
@endphp

<span {{ $attributes->class(['boq-stars', 'boq-stars-'.$size]) }} role="img" aria-label="{{ __(':value out of 5 stars', ['value' => number_format($value, 1)]) }}{{ $count !== null ? ', '.trans_choice(':count rating|:count ratings', $count, ['count' => $count]) : '' }}">
    @for($i = 1; $i <= 5; $i++)
        <i class="{{ $rounded >= $i ? 'fas fa-star' : ($rounded >= $i - 0.5 ? 'fas fa-star-half-stroke' : 'far fa-star') }}" aria-hidden="true"></i>
    @endfor
    @if($showValue)<span class="boq-stars-value">{{ number_format($value, 1) }}</span>@endif
    @if($count !== null)<span class="boq-stars-count">({{ \App\Support\Format::number($count, 0) }})</span>@endif
</span>
