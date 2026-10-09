<?php

namespace App\Livewire\Admin;

use App\Models\Faq;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FaqsManager extends Component
{
    use \App\Livewire\Concerns\ExportsTables;

    protected function exportViewData(): array
    {
        return ['faqs' => $this->loadFaqPage()['data']];
    }
    private const MAX_FAQ_ENTRIES = 20;

    public array $faqs = [];
    public array $meta = [];
    public int $page = 1;
    public bool $loading = false;
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $question = '';
    public string $answer = '';
    public int $sort_order = 0;
    public bool $is_active = true;
    public string $successMessage = '';
    public string $serverError = '';
    /** @var array<int, array{question: string, answer: string}> */
    public array $newFaqs = [['question' => '', 'answer' => '']];

    public function boot(): void
    {
        $this->authorizeAdmin();
    }

    public function mount(): void
    {
        $this->loadFaqs();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    private function loadFaqPage(): array
    {
        $this->authorizeAdmin();
        $this->authorize('viewAny', Faq::class);

        $faqs = Faq::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->exportPageSize(20), ['*'], 'page', $this->page);

        return [
            'data' => array_map(static fn (Faq $faq): array => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => $faq->sort_order,
                'is_active' => $faq->is_active,
            ], $faqs->items()),
            'meta' => [
                'current_page' => $faqs->currentPage(),
                'last_page' => $faqs->lastPage(),
                'per_page' => $faqs->perPage(),
                'total' => $faqs->total(),
            ],
        ];
    }

    private function storeFaq(array $data): array
    {
        $this->authorizeAdmin();
        $this->authorize('create', Faq::class);

        $faq = Faq::create($data);

        return ['message' => __('FAQ created successfully.'), 'data' => $faq->toArray()];
    }

    private function updateFaq(int $id, array $data): array
    {
        $this->authorizeAdmin();
        $faq = Faq::findOrFail($id);
        $this->authorize('update', $faq);

        $faq->update($data);

        return ['message' => __('FAQ updated successfully.'), 'data' => $faq->fresh()->toArray()];
    }

    public function loadFaqs(): void
    {
        $this->authorizeAdmin();
        $this->loading = true;
        $this->serverError = '';
        try {
            $payload = $this->loadFaqPage();
            $this->faqs = $payload['data'] ?? [];
            $this->meta = $payload['meta'] ?? [];
        } catch (\Throwable $exception) {
            report($exception);
            $this->serverError = __('FAQs could not be loaded due to a server error. Please try again.');
        } finally {
            $this->loading = false;
        }
    }

    public function create(): void
    {
        $this->authorizeAdmin();
        $this->resetForm();
        $this->showForm = true;
    }

    public function addFaqEntry(): void
    {
        $this->authorizeAdmin();
        $this->authorize('create', Faq::class);
        if (count($this->newFaqs) >= self::MAX_FAQ_ENTRIES) {
            $this->addError('newFaqs', __('You can add no more than :count FAQs at a time.', ['count' => self::MAX_FAQ_ENTRIES]));

            return;
        }

        $this->newFaqs[] = ['question' => '', 'answer' => ''];
        $this->resetValidation('newFaqs');
    }

    public function removeFaqEntry(int $index): void
    {
        $this->authorizeAdmin();
        // Removing an unsaved batch entry is still part of the FAQ create flow.
        $this->authorize('create', Faq::class);
        if (count($this->newFaqs) > 1 && isset($this->newFaqs[$index])) {
            unset($this->newFaqs[$index]);
            $this->newFaqs = array_values($this->newFaqs);
            $this->resetValidation();
        }
    }

    public function edit(int $id): void
    {
        $this->authorizeAdmin();
        $faq = collect($this->faqs)->firstWhere('id', $id);
        abort_unless($faq, 404);
        $this->editingId = $id;
        $this->question = (string) ($faq['question'] ?? '');
        $this->answer = (string) ($faq['answer'] ?? '');
        $this->sort_order = (int) ($faq['sort_order'] ?? 0);
        $this->is_active = (bool) ($faq['is_active'] ?? true);
        $this->showForm = true;
        $this->successMessage = '';
        $this->newFaqs = [['question' => '', 'answer' => '']];
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $this->successMessage = '';
        $this->serverError = '';
        if ($this->editingId) {
            $data = $this->validate([
                'question' => ['required', 'string', 'max:255'],
                'answer' => ['required', 'string'],
                'sort_order' => ['required', 'integer', 'min:0'],
                'is_active' => ['required', 'boolean'],
            ]);
            $payload = $this->updateFaq($this->editingId, $data);
        } else {
            $data = $this->validate([
                'newFaqs' => ['required', 'array', 'min:1', 'max:'.self::MAX_FAQ_ENTRIES],
                'newFaqs.*.question' => ['required', 'string', 'max:255'],
                'newFaqs.*.answer' => ['required', 'string'],
            ]);
            DB::transaction(function () use ($data): void {
                foreach ($data['newFaqs'] as $index => $entry) {
                    $this->storeFaq([
                        'question' => $entry['question'],
                        'answer' => $entry['answer'],
                        'sort_order' => $this->sort_order + $index,
                        'is_active' => $this->is_active,
                    ]);
                }
            });
            $payload = ['message' => __('FAQs created successfully.')];
        }

        $this->successMessage = $payload['message'] ?? __('FAQ saved successfully.');
        $this->cancel();
        $this->loadFaqs();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function changePage(int $page): void
    {
        $this->authorizeAdmin();
        $lastPage = max(1, (int) ($this->meta['last_page'] ?? 1));
        $this->page = max(1, min($page, $lastPage));
        $this->loadFaqs();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->question = '';
        $this->answer = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->newFaqs = [['question' => '', 'answer' => '']];
    }

    public function render()
    {
        return view('livewire.admin.faqs-manager');
    }
}
