<x-layouts.guest :title="$title">

    <div class="text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-[#05645b]/10 text-[#05645b]">
            <i class="fas {{ $icon }} text-lg"></i>
        </div>

        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">
            {{ $title }}
        </h1>
    </div>

    <div class="mt-6 max-h-[60vh] overflow-y-auto whitespace-pre-line text-sm leading-6 text-slate-600">
        @if($content !== '')
            {{ $content }}
        @else
            This document has not been published yet. Please contact the system administrator for details.
        @endif
    </div>

    <p class="mt-7 text-center text-sm text-slate-500">
        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="auth-link">
            <i class="fas fa-arrow-left mr-1"></i>
            Back
        </a>
    </p>

</x-layouts.guest>
