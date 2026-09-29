@php
    $companyName = $company['company_name'] ?? config('app.name');
    $project = $boq->project;
    $money = fn ($value) => \App\Support\Format::money($value, $totals['currency']);
@endphp

<x-layouts.guest :title="__('Sign the Bill of Quantities')" wide>
    <div class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ __('Signature requested by :company', ['company' => $companyName]) }}</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $boq->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Review the Bill of Quantities below, then sign to approve it.') }}</p>
        </div>

        {{-- Summary --}}
        <section class="rounded-xl border border-slate-200 p-4" aria-labelledby="boq-summary-title">
            <h2 id="boq-summary-title" class="mb-3 text-sm font-semibold text-slate-900">{{ __('Summary') }}</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Reference') }}</dt><dd class="text-right font-medium text-slate-900">{{ $boq->reference }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Project') }}</dt><dd class="text-right font-medium text-slate-900">{{ $project?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Client') }}</dt><dd class="text-right font-medium text-slate-900">{{ $project?->client ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Items') }}</dt><dd class="text-right font-medium text-slate-900">{{ \App\Support\Format::number($totals['lines']->count(), 0) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Subtotal') }}</dt><dd class="text-right font-medium text-slate-900">{{ $money($totals['subtotal']) }}</dd></div>
                @if($totals['taxRate'] > 0)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $totals['taxLabel'] }} ({{ rtrim(rtrim(number_format($totals['taxRate'], 2), '0'), '.') }}%)</dt><dd class="text-right font-medium text-slate-900">{{ $money($totals['tax']) }}</dd></div>
                @endif
            </dl>
            <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                <span class="text-sm font-semibold text-slate-900">{{ __('Grand Total') }}</span>
                <span class="text-lg font-bold text-brand-700">{{ $money($totals['total']) }}</span>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="boq-btn-secondary boq-btn-sm">
                    <i class="fas fa-eye" aria-hidden="true"></i> {{ __('Open PDF') }}
                </a>
                <a href="{{ $pdfUrl }}" download class="boq-btn-secondary boq-btn-sm">
                    <i class="fas fa-download" aria-hidden="true"></i> {{ __('Download PDF') }}
                </a>
            </div>

            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-medium text-brand-700">{{ __('Show PDF preview') }}</summary>
                <iframe src="{{ $pdfUrl }}" title="{{ __('PDF Preview') }}" class="mt-3 block h-[70vh] w-full rounded-lg border border-slate-200" loading="lazy"></iframe>
            </details>
        </section>

        {{-- Sign --}}
        <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-4" novalidate>
            @csrf

            <h2 class="text-sm font-semibold text-slate-900">{{ __('Your signature') }}</h2>

            @if($errors->any())
                <x-ui.alert type="error" :title="__('Please check the form')">
                    <ul class="list-disc pl-4">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field :label="__('Full name')" for="client-name" error="name" required>
                    <input id="client-name" name="name" type="text" value="{{ old('name') }}" maxlength="150" required autocomplete="name" class="boq-field @error('name') has-error @enderror">
                </x-ui.field>
                <x-ui.field :label="__('Title / role')" for="client-title" error="title">
                    <input id="client-title" name="title" type="text" value="{{ old('title') }}" maxlength="150" class="boq-field @error('title') has-error @enderror" placeholder="{{ __('e.g. Director') }}">
                </x-ui.field>
                <x-ui.field :label="__('Date')" for="client-date" error="date">
                    <input id="client-date" name="date" type="date" value="{{ old('date', now()->toDateString()) }}" class="boq-field @error('date') has-error @enderror">
                </x-ui.field>
            </div>

            <div>
                <span class="boq-field-label">{{ __('Draw your signature') }} <span class="boq-field-required" aria-hidden="true">*</span></span>
                <x-ui.signature-pad id="client-signature-data" name="signature_data" />
                @error('signature_data') <p class="boq-field-error" role="alert"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i> <span>{{ $message }}</span></p> @enderror
            </div>

            <x-ui.field :label="__('Or upload an image of your signature')" for="client-signature-file" error="signature_file" :hint="__('PNG, JPG or WebP, up to 2 MB. Used instead of the drawing when you choose a file.')">
                <input id="client-signature-file" name="signature_file" type="file" accept="image/png,image/jpeg,image/webp" class="boq-field">
            </x-ui.field>

            <div>
                <label class="boq-check">
                    <input type="checkbox" name="approve" value="1" @checked(old('approve')) required>
                    {{ __('I approve this Bill of Quantities') }}
                </label>
                @error('approve') <p class="boq-field-error" role="alert"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i> <span>{{ $message }}</span></p> @enderror
            </div>

            <button type="submit" class="boq-btn-primary boq-btn-block boq-btn-lg">
                <i class="fas fa-signature" aria-hidden="true"></i> {{ __('Sign and approve') }}
            </button>

            <p class="text-xs text-slate-500">
                {{ __('Your name, signature, the date, your IP address and browser are recorded with your approval. This link works once and expires on :date.', ['date' => \App\Support\Format::date($link->expires_at)]) }}
            </p>
        </form>
    </div>
</x-layouts.guest>
