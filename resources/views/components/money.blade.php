@props(['amount' => 0, 'currency' => null])
<span {{ $attributes }}>{{ \App\Support\Format::money($amount, $currency) }}</span>
