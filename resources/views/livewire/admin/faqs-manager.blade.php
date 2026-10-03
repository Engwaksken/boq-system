<div class="boq-page-stack">
    <x-ui.page-header :title="__('FAQ Management')" icon="fa-circle-question" :subtitle="__('Create and maintain the questions and answers shown to users.')">
        <x-slot:actions>
            <x-ui.button type="button" icon="fa-plus" wire:click="create">{{ __('Add FAQ') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($successMessage)
        <div role="status" aria-live="polite" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ $successMessage }}</div>
    @endif
    @if($serverError)
        <div role="alert" aria-live="assertive" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $serverError }}</div>
    @endif

    @if($showForm)
        <section class="boq-panel p-6 sm:p-8" aria-labelledby="faq-form-heading">
            <h2 id="faq-form-heading" class="mb-6 text-lg font-semibold">{{ $editingId ? __('Edit FAQ') : __('Create FAQ entries') }}</h2>
            <form wire:submit="save" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @if(!$editingId)
                    @foreach($newFaqs as $index => $entry)
                        <fieldset wire:key="new-faq-{{ $index }}" class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 p-5 md:col-span-2 md:grid-cols-2">
                            <legend class="px-2 text-sm font-semibold">{{ __('FAQ :number', ['number' => $index + 1]) }}</legend>
                            <x-ui.field :label="__('Question')" for="faq-question-{{ $index }}" :error="'newFaqs.'.$index.'.question'" required class="md:col-span-2">
                                <input id="faq-question-{{ $index }}" type="text" wire:model="newFaqs.{{ $index }}.question" maxlength="255" required class="boq-field @error('newFaqs.'.$index.'.question') has-error @enderror">
                            </x-ui.field>
                            <x-ui.field :label="__('Answer')" for="faq-answer-{{ $index }}" :error="'newFaqs.'.$index.'.answer'" required class="md:col-span-2">
                                <textarea id="faq-answer-{{ $index }}" wire:model="newFaqs.{{ $index }}.answer" rows="5" required class="boq-field @error('newFaqs.'.$index.'.answer') has-error @enderror"></textarea>
                            </x-ui.field>
                            @if(count($newFaqs) > 1)<button type="button" wire:click="removeFaqEntry({{ $index }})" class="boq-btn-ghost justify-self-start">{{ __('Remove FAQ') }}</button>@endif
                        </fieldset>
                    @endforeach
                    @error('newFaqs')<p role="alert" class="md:col-span-2 text-sm text-rose-700">{{ $message }}</p>@enderror
                    @if(count($newFaqs) < 20)
                        <x-ui.button type="button" variant="secondary" icon="fa-plus" wire:click="addFaqEntry" class="md:col-span-2">{{ __('Add another FAQ') }}</x-ui.button>
                    @else
                        <p class="md:col-span-2 text-sm text-slate-600">{{ __('Maximum 20 FAQs per batch.') }}</p>
                    @endif
                @else
                <x-ui.field :label="__('Question')" for="faq-question" error="question" required class="md:col-span-2">
                    <input id="faq-question" type="text" wire:model="question" maxlength="1000" required class="boq-field @error('question') has-error @enderror" aria-describedby="faq-question-error">
                </x-ui.field>
                @endif
                @if($editingId)
                    <x-ui.field :label="__('Answer')" for="faq-answer" error="answer" required class="md:col-span-2">
                        <textarea id="faq-answer" wire:model="answer" rows="5" maxlength="10000" required class="boq-field @error('answer') has-error @enderror" aria-describedby="faq-answer-error"></textarea>
                    </x-ui.field>
                @endif
                <x-ui.field :label="__('Sort order')" for="faq-sort-order" error="sort_order">
                    <input id="faq-sort-order" type="number" wire:model="sort_order" min="0" step="1" required class="boq-field @error('sort_order') has-error @enderror">
                </x-ui.field>
                <label for="faq-is-active" class="inline-flex min-h-11 items-center gap-2">
                    <input id="faq-is-active" type="checkbox" wire:model="is_active" class="rounded border-slate-300">
                    <span>{{ __('Active') }}</span>
                </label>
                <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5 md:col-span-2">
                    <x-ui.button type="submit" icon="fa-floppy-disk" wire:target="save" wire:loading.attr="disabled">{{ $editingId ? __('Save FAQ') : __('Save FAQs') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                    <span wire:loading wire:target="save" role="status" class="self-center text-sm text-slate-600">{{ __('Saving…') }}</span>
                </div>
            </form>
        </section>
    @endif

    <section class="boq-panel p-6 sm:p-8" aria-labelledby="faq-list-heading" aria-busy="{{ $loading ? 'true' : 'false' }}">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 id="faq-list-heading" class="text-lg font-semibold">{{ __('FAQs') }}</h2>
            <button type="button" wire:click="loadFaqs" class="boq-btn-secondary" wire:loading.attr="disabled" wire:target="loadFaqs">{{ __('Refresh') }}</button>
        </div>
        @if($loading)
            <p role="status" class="py-6 text-center text-sm text-slate-600">{{ __('Loading FAQs…') }}</p>
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead><tr><th>{{ __('Question') }}</th><th>{{ __('Answer') }}</th><th>{{ __('Order') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse($faqs as $faq)
                            <tr wire:key="faq-{{ $faq['id'] }}">
                                <td class="min-w-48 font-medium">{{ $faq['question'] ?? '' }}</td>
                                <td class="min-w-64 whitespace-pre-line">{{ $faq['answer'] ?? '' }}</td>
                                <td>{{ $faq['sort_order'] ?? 0 }}</td>
                                <td><x-ui.badge :color="($faq['is_active'] ?? false) ? 'success' : 'neutral'">{{ ($faq['is_active'] ?? false) ? __('Active') : __('Inactive') }}</x-ui.badge></td>
                                <td class="text-right"><button type="button" wire:click="edit({{ (int) $faq['id'] }})" class="boq-btn-ghost" aria-label="{{ __('Edit FAQ: :question', ['question' => $faq['question'] ?? '']) }}"><i class="fas fa-pen" aria-hidden="true"></i> {{ __('Edit') }}</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-0"><x-ui.empty-state icon="fa-circle-question" :title="__('No FAQs found.')" :description="__('Create an FAQ to get started.')" /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>
            @if(($meta['last_page'] ?? 1) > 1)
                <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('FAQ pagination') }}">
                    <button type="button" wire:click="changePage({{ max(1, $page - 1) }})" @disabled($page <= 1) class="boq-btn-secondary">{{ __('Previous') }}</button>
                    <span class="text-sm" aria-live="polite">{{ __('Page :current of :last', ['current' => $page, 'last' => $meta['last_page']]) }} · {{ $meta['total'] ?? count($faqs) }} {{ __('FAQs') }}</span>
                    <button type="button" wire:click="changePage({{ min((int) $meta['last_page'], $page + 1) }})" @disabled($page >= (int) $meta['last_page']) class="boq-btn-secondary">{{ __('Next') }}</button>
                </nav>
            @endif
        @endif
    </section>
</div>
