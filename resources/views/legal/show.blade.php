<x-layouts.guest :title="$title" wide>

    <div class="text-center">
        <x-auth-badge :icon="$icon" />

        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">
            {{ $title }}
        </h1>
    </div>

    {{-- $content is an HtmlString sanitised in LegalPageController::toSafeHtml() --}}
    <div class="auth-legal-content mt-6 max-h-[60vh] overflow-y-auto">
        @if($content)
            {{ $content }}
        @else
            <p>{{ __('This document has not been published yet. Please contact the system administrator for details.') }}</p>
        @endif
    </div>

    <p class="mt-7 text-center text-sm text-slate-500">
        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="auth-link">
            <i class="fas fa-arrow-left mr-1"></i>
            {{ __('Back') }}
        </a>
    </p>

</x-layouts.guest>
