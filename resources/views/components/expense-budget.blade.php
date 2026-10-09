@props(['comparison'])
<div class="mt-2 rounded-lg border border-slate-200 p-3 text-sm">
    @if($comparison['status'] === 'unlinked')
        <p class="text-amber-700">{{ __('Not linked to a BOQ item — budget cannot be checked.') }}</p>
    @elseif($comparison['status'] === 'currency_mismatch')
        <p class="text-amber-700">{{ __('Currency differs from the BOQ item (:currency). Budget comparison requires the same currency.', ['currency' => $comparison['currency']]) }}</p>
    @elseif($comparison['status'] === 'unpriced')
        <p class="text-amber-700">{{ __('The selected BOQ item has no budget rate yet.') }}</p>
    @else
        <p class="font-semibold {{ $comparison['status'] === 'over_budget' ? 'text-red-700' : 'text-emerald-700' }}">{{ $comparison['status'] === 'over_budget' ? __('Over budget') : __('Within budget') }} · {{ __($comparison['basis']) }}</p>
        <p>{{ $comparison['description'] }}</p>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <p>{{ __('BOQ budget') }}: <x-money :amount="$comparison['budget']" :currency="$comparison['currency']" /></p>
            <p>{{ __('Previously recorded') }}: <x-money :amount="$comparison['spent']" :currency="$comparison['currency']" /></p>
            <p>{{ __('Including this expense') }}: <x-money :amount="$comparison['projected']" :currency="$comparison['currency']" /></p>
            <p>{{ __('Remaining budget') }}: <x-money :amount="$comparison['remaining']" :currency="$comparison['currency']" /></p>
        </div>
        @if($comparison['progress'] !== null)
            <p class="mt-2">{{ __('Budget used') }}: {{ $comparison['progress'] }}%</p>
            <progress class="w-full" value="{{ min(100, max(0, $comparison['progress'])) }}" max="100" aria-label="{{ __('Budget used') }}"></progress>
        @endif
        @if($comparison['unit_mismatch'])<p class="mt-1 text-amber-700">{{ __('Unit differs from the BOQ item. Review the unit and any conversion.') }}</p>@endif
        @if($comparison['rate_over_budget'])<p class="mt-1 text-amber-700">{{ __('Purchase rate exceeds the BOQ rate.') }}</p>@endif
        <p class="mt-2 text-xs text-slate-500">{{ __('Includes all recorded project expenses linked to this BOQ item in this currency. Repeated links in this expense are combined. Other currencies are excluded.') }}</p>
    @endif
</div>
