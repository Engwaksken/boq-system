<x-layouts.guest :title="__('Thank you')">
    <div class="space-y-4 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-2xl text-brand-700" aria-hidden="true">
            <i class="fas fa-circle-check"></i>
        </span>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Thank you, the BOQ is signed') }}</h1>
        <p class="text-sm text-slate-600">
            {{ __(':name was signed and approved. :company has been notified.', ['name' => $boq->name, 'company' => $company['company_name'] ?? config('app.name')]) }}
        </p>
        @if($signature)
            <p class="text-xs text-slate-500">{{ __('Signed by :name on :date.', ['name' => $signature->name, 'date' => \App\Support\Format::date($signature->signed_at)]) }}</p>
        @endif
        <p class="text-xs text-slate-500">{{ __('You can close this page.') }}</p>
    </div>
</x-layouts.guest>
