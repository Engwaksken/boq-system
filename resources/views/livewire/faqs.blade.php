<div class="boq-page-stack">
    <x-ui.page-header :title="__('FAQs')" icon="fa-circle-question" :subtitle="__('Find answers about BOQs, plans, projects and expenses.')" />
    <x-ui.card>
        <x-ui.field :label="__('Search FAQs')" for="faq-search">
            <input id="faq-search" type="search" wire:model.live.debounce.300ms="search" class="boq-field" maxlength="255" placeholder="{{ __('Search questions and answers...') }}">
        </x-ui.field>
    </x-ui.card>
    <section class="space-y-3" aria-label="{{ __('Frequently asked questions') }}">
        @forelse($faqs as $faq)
            <details wire:key="user-faq-{{ $faq->id }}" class="boq-card">
                <summary class="cursor-pointer px-5 py-4 font-semibold text-slate-900">{{ $faq->question }}</summary>
                <div class="whitespace-pre-line border-t border-slate-100 px-5 py-4 text-sm text-slate-700">{{ $faq->answer }}</div>
            </details>
        @empty
            <x-ui.card><x-ui.empty-state icon="fa-circle-question" :title="__('No FAQs found.')" :description="__('Try a different search or check back for new answers.')" /></x-ui.card>
        @endforelse
    </section>
    {{ $faqs->links() }}
</div>
