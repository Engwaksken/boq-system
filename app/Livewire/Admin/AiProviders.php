<?php

namespace App\Livewire\Admin;

use App\Models\AiProvider;
use App\Services\AiProviderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AiProviders extends Component
{
    use WithPagination;

    private const SECRET_MASK = '***stored***';

    public string $search = '';
    public string $typeFilter = 'all';
    public string $statusFilter = 'all';
    public int $perPage = 10;

    public bool $showForm = false;
    public bool $showDeleteModal = false;
    public bool $showTestModal = false;
    public ?int $editingId = null;
    public ?int $deleteId = null;
    public ?int $testingId = null;
    public ?array $testResult = null;

    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $provider = AiProvider::findOrFail($id);
        $settings = $provider->settings ?? [];

        $this->editingId = $provider->id;
        $this->form = [
            'name' => $provider->name,
            'key' => $provider->key,
            'provider_type' => $provider->provider_type,
            'api_base_url' => $provider->api_base_url,
            'default_model' => $provider->default_model,
            'api_key' => filled($provider->api_key) ? self::SECRET_MASK : '',
            'organisation_id' => $provider->organisation_id,
            'is_enabled' => $provider->is_enabled,
            'is_default' => $provider->is_default,
            'sort_order' => $provider->sort_order,
            'temperature' => data_get($settings, 'temperature', 0.2),
            'timeout' => data_get($settings, 'timeout', 45),
            'max_tokens' => data_get($settings, 'max_tokens'),
        ];

        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $types = array_keys($this->providerTypes());

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/', Rule::unique('ai_providers', 'key')->ignore($this->editingId)],
            'form.provider_type' => ['required', Rule::in($types)],
            'form.api_base_url' => ['nullable', 'url', 'max:1000'],
            'form.default_model' => ['nullable', 'string', 'max:255'],
            'form.api_key' => ['nullable', 'string', 'max:10000'],
            'form.organisation_id' => ['nullable', 'integer', 'exists:organisations,id'],
            'form.is_enabled' => ['boolean'],
            'form.is_default' => ['boolean'],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'form.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'form.timeout' => ['nullable', 'integer', 'min:5', 'max:300'],
            'form.max_tokens' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        DB::transaction(function () use ($validated) {
            $provider = $this->editingId
                ? AiProvider::findOrFail($this->editingId)
                : new AiProvider();

            $apiKey = $validated['form']['api_key'] ?? '';

            if ($apiKey === self::SECRET_MASK) {
                $apiKey = $provider->api_key;
            }

            $payload = [
                'name' => $validated['form']['name'],
                'key' => strtolower($validated['form']['key']),
                'provider_type' => $validated['form']['provider_type'],
                'api_base_url' => $validated['form']['api_base_url'] ?: null,
                'default_model' => $validated['form']['default_model'] ?: null,
                'api_key' => $apiKey ?: null,
                'organisation_id' => $validated['form']['organisation_id'] ?: null,
                'is_enabled' => (bool) $validated['form']['is_enabled'],
                'is_default' => (bool) $validated['form']['is_default'],
                'sort_order' => (int) $validated['form']['sort_order'],
                'settings' => [
                    'temperature' => (float) ($validated['form']['temperature'] ?? 0.2),
                    'timeout' => (int) ($validated['form']['timeout'] ?? 45),
                    'max_tokens' => $validated['form']['max_tokens'] ?: null,
                ],
                'updated_by' => auth()->id(),
            ];

            if (! $provider->exists) {
                $payload['created_by'] = auth()->id();
            }

            if ($payload['is_default']) {
                AiProvider::query()
                    ->when(
                        $payload['organisation_id'],
                        fn ($q) => $q->where('organisation_id', $payload['organisation_id']),
                        fn ($q) => $q->whereNull('organisation_id')
                    )
                    ->when($provider->exists, fn ($q) => $q->whereKeyNot($provider->getKey()))
                    ->update(['is_default' => false]);
            }

            $provider->fill($payload)->save();
        });

        session()->flash('message', $this->editingId ? 'AI provider updated successfully.' : 'AI provider created successfully.');
        $this->cancel();
    }

    public function toggleEnabled(int $id): void
    {
        $provider = AiProvider::findOrFail($id);
        $provider->update([
            'is_enabled' => ! $provider->is_enabled,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('message', 'AI provider status updated.');
    }

    public function setDefault(int $id): void
    {
        DB::transaction(function () use ($id) {
            $provider = AiProvider::findOrFail($id);

            AiProvider::query()
                ->when(
                    $provider->organisation_id,
                    fn ($q) => $q->where('organisation_id', $provider->organisation_id),
                    fn ($q) => $q->whereNull('organisation_id')
                )
                ->update(['is_default' => false]);

            $provider->update([
                'is_default' => true,
                'is_enabled' => true,
                'updated_by' => auth()->id(),
            ]);
        });

        session()->flash('message', 'Default AI provider updated.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if ($this->deleteId) {
            AiProvider::findOrFail($this->deleteId)->delete();
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;

        session()->flash('message', 'AI provider deleted.');
    }

    public function testConnection(int $id, AiProviderService $service): void
    {
        $this->testingId = $id;
        $this->showTestModal = true;
        $this->testResult = $service->test(AiProvider::findOrFail($id));
        $this->testingId = null;
    }

    public function closeTestModal(): void
    {
        $this->showTestModal = false;
        $this->testResult = null;
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
    }

    public function providerTypes(): array
    {
        return [
            'openai' => 'OpenAI',
            'gemini' => 'Google Gemini',
            'groq' => 'Groq',
            'mistral' => 'Mistral',
            'deepseek' => 'DeepSeek',
            'openrouter' => 'OpenRouter',
            'ollama' => 'Ollama',
            'openai_compatible' => 'OpenAI-compatible API',
            'custom' => 'Custom REST API',
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'key' => '',
            'provider_type' => 'gemini',
            'api_base_url' => 'https://generativelanguage.googleapis.com',
            'default_model' => 'gemini-2.5-flash',
            'api_key' => '',
            'organisation_id' => null,
            'is_enabled' => true,
            'is_default' => false,
            'sort_order' => 100,
            'temperature' => 0.2,
            'timeout' => 45,
            'max_tokens' => null,
        ];
    }

    public function render()
    {
        $providers = AiProvider::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('key', 'like', $term)
                        ->orWhere('default_model', 'like', $term);
                });
            })
            ->when($this->typeFilter !== 'all', fn ($q) => $q->where('provider_type', $this->typeFilter))
            ->when($this->statusFilter === 'enabled', fn ($q) => $q->where('is_enabled', true))
            ->when($this->statusFilter === 'disabled', fn ($q) => $q->where('is_enabled', false))
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->paginate($this->perPage);

        return view('livewire.admin.ai-providers', [
            'providers' => $providers,
            'providerTypes' => $this->providerTypes(),
            'stats' => [
                'providers' => AiProvider::count(),
                'enabled' => AiProvider::where('is_enabled', true)->count(),
                'default' => AiProvider::where('is_default', true)->value('name') ?? 'None',
                'failed' => AiProvider::where('last_test_status', 'failed')->count(),
            ],
        ]);
    }
}
