<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\AiProvider;
use App\Services\AiProviderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
class AiProviders extends Component
{
    use WithPagination;

    private const SECRET_MASK = '***stored***';

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    public string $search = '';

    public string $typeFilter = 'all';

    public string $statusFilter = 'all';

    public int $perPage = 10;


    /*
    |--------------------------------------------------------------------------
    | Modal State
    |--------------------------------------------------------------------------
    */

    public bool $showForm = false;

    public bool $showDeleteModal = false;

    public bool $showTestModal = false;

    public ?int $editingId = null;

    public ?int $deleteId = null;

    public ?int $testingId = null;

    public ?array $testResult = null;


    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public array $form = [];


    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->resetForm();
    }


    /*
    |--------------------------------------------------------------------------
    | Filter Events
    |--------------------------------------------------------------------------
    */

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }


    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(): void
    {
        $this->resetValidation();

        $this->editingId = null;

        $this->resetForm();

        $this->showForm = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(int $id): void
    {
        $this->resetValidation();

        $provider = AiProvider::query()->findOrFail($id);

        $settings = is_array($provider->settings)
            ? $provider->settings
            : [];

        $this->editingId = $provider->id;

        $this->form = [
            'name' => (string) $provider->name,

            'key' => (string) $provider->key,

            'provider_type' => (string) $provider->provider_type,

            'api_base_url' => (string) ($provider->api_base_url ?? ''),

            'default_model' => (string) ($provider->default_model ?? ''),

            /*
             * Never send a stored secret back to the browser.
             */
            'api_key' => filled($provider->api_key)
                ? self::SECRET_MASK
                : '',

            'organisation_id' => $provider->organisation_id,

            'is_enabled' => (bool) $provider->is_enabled,

            'is_default' => (bool) $provider->is_default,

            'sort_order' => (int) ($provider->sort_order ?? 100),

            'temperature' => (float) data_get(
                $settings,
                'temperature',
                0.2
            ),

            'timeout' => (int) data_get(
                $settings,
                'timeout',
                45
            ),

            'max_tokens' => data_get(
                $settings,
                'max_tokens'
            ),

            'web_search' => (bool) data_get(
                $settings,
                'web_search',
                false
            ),
        ];

        $this->showForm = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    public function save(): void
    {
        $validated = $this->validate(
            $this->rules(),
            $this->validationMessages()
        );

        DB::transaction(function () use ($validated): void {

            $provider = $this->editingId
                ? AiProvider::query()->findOrFail($this->editingId)
                : new AiProvider();

            $form = $validated['form'];

            /*
             * Keep the existing encrypted API key when the edit form
             * contains the secret mask.
             */
            $apiKey = $form['api_key'] ?? '';

            if ($apiKey === self::SECRET_MASK) {
                $apiKey = $provider->api_key;
            }

            /*
             * Normalise blank organisation values.
             */
            $organisationId = filled($form['organisation_id'] ?? null)
                ? (int) $form['organisation_id']
                : null;

            $isDefault = (bool) ($form['is_default'] ?? false);

            /*
             * Only one default provider is allowed for the same scope.
             *
             * Global providers:
             * organisation_id = NULL
             *
             * Organisation providers:
             * organisation_id = organisation ID
             */
            if ($isDefault) {
                $defaultQuery = AiProvider::query();

                if ($organisationId !== null) {
                    $defaultQuery->where(
                        'organisation_id',
                        $organisationId
                    );
                } else {
                    $defaultQuery->whereNull(
                        'organisation_id'
                    );
                }

                /*
                 * Exclude the provider currently being edited.
                 *
                 * Do not use whereKeyNot(); use a standard ID condition.
                 */
                if ($provider->exists) {
                    $defaultQuery->where(
                        $provider->getKeyName(),
                        '!=',
                        $provider->getKey()
                    );
                }

                $defaultQuery->update([
                    'is_default' => false,
                ]);
            }

            $payload = [
                'name' => trim($form['name']),

                'key' => Str::lower(
                    trim($form['key'])
                ),

                'provider_type' => $form['provider_type'],

                'api_base_url' => filled(
                    $form['api_base_url'] ?? null
                )
                    ? rtrim(
                        trim($form['api_base_url']),
                        '/'
                    )
                    : null,

                'default_model' => filled(
                    $form['default_model'] ?? null
                )
                    ? trim($form['default_model'])
                    : null,

                'api_key' => filled($apiKey)
                    ? $apiKey
                    : null,

                'organisation_id' => $organisationId,

                'is_enabled' => (bool) (
                    $form['is_enabled'] ?? false
                ),

                'is_default' => $isDefault,

                'sort_order' => (int) (
                    $form['sort_order'] ?? 100
                ),

                'settings' => [
                    'temperature' => isset(
                        $form['temperature']
                    )
                        && $form['temperature'] !== ''
                            ? (float) $form['temperature']
                            : 0.2,

                    'timeout' => isset(
                        $form['timeout']
                    )
                        && $form['timeout'] !== ''
                            ? (int) $form['timeout']
                            : 45,

                    'max_tokens' => filled(
                        $form['max_tokens'] ?? null
                    )
                        ? (int) $form['max_tokens']
                        : null,

                    'web_search' => (bool) (
                        $form['web_search'] ?? false
                    ),
                ],

                'updated_by' => auth()->id(),
            ];

            if (! $provider->exists) {
                $payload['created_by'] = auth()->id();
            }

            /*
             * A default provider must be enabled.
             */
            if ($payload['is_default']) {
                $payload['is_enabled'] = true;
            }

            $provider->fill($payload);

            $provider->save();
        });

        session()->flash(
            'message',
            $this->editingId
                ? 'AI provider updated successfully.'
                : 'AI provider created successfully.'
        );

        $this->cancel();
    }


    /*
    |--------------------------------------------------------------------------
    | Enable / Disable
    |--------------------------------------------------------------------------
    */

    public function toggleEnabled(int $id): void
    {
        $provider = AiProvider::query()->findOrFail($id);

        /*
         * Do not allow the current default provider to be disabled.
         */
        if ($provider->is_default && $provider->is_enabled) {
            session()->flash(
                'error',
                'The default AI provider cannot be disabled. Set another provider as default first.'
            );

            return;
        }

        $provider->update([
            'is_enabled' => ! $provider->is_enabled,

            'updated_by' => auth()->id(),
        ]);

        session()->flash(
            'message',
            'AI provider status updated successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Set Default
    |--------------------------------------------------------------------------
    */

    public function setDefault(int $id): void
    {
        DB::transaction(function () use ($id): void {

            $provider = AiProvider::query()->findOrFail($id);

            $query = AiProvider::query();

            if ($provider->organisation_id !== null) {
                $query->where(
                    'organisation_id',
                    $provider->organisation_id
                );
            } else {
                $query->whereNull(
                    'organisation_id'
                );
            }

            $query->update([
                'is_default' => false,
            ]);

            $provider->update([
                'is_default' => true,

                'is_enabled' => true,

                'updated_by' => auth()->id(),
            ]);
        });

        session()->flash(
            'message',
            'Default AI provider updated successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function confirmDelete(int $id): void
    {
        $provider = AiProvider::query()->findOrFail($id);

        if ($provider->is_default) {
            session()->flash(
                'error',
                'The default AI provider cannot be deleted. Set another provider as default first.'
            );

            return;
        }

        $this->deleteId = $provider->id;

        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;

        $this->deleteId = null;
    }

    public function delete(): void
    {
        if ($this->deleteId === null) {
            $this->cancelDelete();

            return;
        }

        $provider = AiProvider::query()->findOrFail(
            $this->deleteId
        );

        if ($provider->is_default) {
            $this->cancelDelete();

            session()->flash(
                'error',
                'The default AI provider cannot be deleted.'
            );

            return;
        }

        $provider->delete();

        $this->cancelDelete();

        session()->flash(
            'message',
            'AI provider deleted successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Test Connection
    |--------------------------------------------------------------------------
    */

    public function testConnection(
        int $id,
        AiProviderService $service
    ): void {
        $provider = AiProvider::query()->findOrFail($id);

        $this->testingId = $provider->id;

        $this->showTestModal = true;

        $this->testResult = null;

        try {
            $result = $service->test($provider);

            $this->testResult = [
                'ok' => (bool) (
                    $result['ok'] ?? false
                ),

                'message' => (string) (
                    $result['message']
                    ?? 'Connection test completed.'
                ),
            ];
        } catch (Throwable $exception) {
            report($exception);

            $this->testResult = [
                'ok' => false,

                /*
                 * Do not expose raw provider exceptions because they may
                 * contain credentials or request details.
                 */
                'message' => 'The AI provider connection test failed. Check the provider configuration and try again.',
            ];
        } finally {
            $this->testingId = null;
        }
    }

    public function closeTestModal(): void
    {
        $this->showTestModal = false;

        $this->testingId = null;

        $this->testResult = null;
    }


    /*
    |--------------------------------------------------------------------------
    | Cancel Form
    |--------------------------------------------------------------------------
    */

    public function cancel(): void
    {
        $this->showForm = false;

        $this->editingId = null;

        $this->resetValidation();

        $this->resetForm();
    }


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    protected function rules(): array
    {
        return [
            'form.name' => [
                'required',
                'string',
                'max:255',
            ],

            'form.key' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_-]+$/',

                Rule::unique(
                    'ai_providers',
                    'key'
                )->ignore(
                    $this->editingId
                ),
            ],

            'form.provider_type' => [
                'required',

                Rule::in(
                    array_keys(
                        $this->providerTypes()
                    )
                ),
            ],

            'form.api_base_url' => [
                'nullable',
                'url',
                'max:1000',
            ],

            'form.default_model' => [
                'nullable',
                'string',
                'max:255',
            ],

            'form.api_key' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'form.organisation_id' => [
                'nullable',
                'integer',
                'exists:organisations,id',
            ],

            'form.is_enabled' => [
                'boolean',
            ],

            'form.is_default' => [
                'boolean',
            ],

            'form.sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:100000',
            ],

            'form.temperature' => [
                'nullable',
                'numeric',
                'min:0',
                'max:2',
            ],

            'form.timeout' => [
                'nullable',
                'integer',
                'min:5',
                'max:300',
            ],

            'form.max_tokens' => [
                'nullable',
                'integer',
                'min:1',
                'max:1000000',
            ],

            'form.web_search' => [
                'boolean',
            ],
        ];
    }

    protected function validationMessages(): array
    {
        return [
            'form.name.required' =>
                'Provider name is required.',

            'form.key.required' =>
                'Provider key is required.',

            'form.key.regex' =>
                'Provider key may only contain lowercase letters, numbers, hyphens and underscores.',

            'form.key.unique' =>
                'This provider key is already in use.',

            'form.provider_type.required' =>
                'Provider type is required.',

            'form.provider_type.in' =>
                'Select a valid AI provider type.',

            'form.api_base_url.url' =>
                'Enter a valid API base URL.',

            'form.organisation_id.exists' =>
                'The selected organisation could not be found.',

            'form.temperature.min' =>
                'Temperature cannot be below 0.',

            'form.temperature.max' =>
                'Temperature cannot exceed 2.',

            'form.timeout.min' =>
                'Timeout must be at least 5 seconds.',

            'form.timeout.max' =>
                'Timeout cannot exceed 300 seconds.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Provider Types
    |--------------------------------------------------------------------------
    */

    public function providerTypes(): array
    {
        return [
            'openai' =>
                'OpenAI',

            'gemini' =>
                'Google Gemini',

            'groq' =>
                'Groq',

            'mistral' =>
                'Mistral',

            'deepseek' =>
                'DeepSeek',

            'openrouter' =>
                'OpenRouter',

            'ollama' =>
                'Ollama',

            'openai_compatible' =>
                'OpenAI-compatible API',

            'custom' =>
                'Custom REST API',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Reset Form
    |--------------------------------------------------------------------------
    */

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',

            'key' => '',

            'provider_type' =>
                'gemini',

            'api_base_url' =>
                'https://generativelanguage.googleapis.com',

            'default_model' =>
                'gemini-2.5-flash',

            'api_key' => '',

            'organisation_id' => null,

            'is_enabled' => true,

            'is_default' => false,

            'sort_order' => 100,

            'temperature' => 0.2,

            'timeout' => 45,

            'max_tokens' => null,

            'web_search' => false,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render()
    {
        $providers = AiProvider::query()
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {

                    $term = '%'
                        .trim($this->search)
                        .'%';

                    $query->where(
                        function (Builder $inner) use ($term): void {

                            $inner
                                ->where(
                                    'name',
                                    'like',
                                    $term
                                )
                                ->orWhere(
                                    'key',
                                    'like',
                                    $term
                                )
                                ->orWhere(
                                    'default_model',
                                    'like',
                                    $term
                                )
                                ->orWhere(
                                    'provider_type',
                                    'like',
                                    $term
                                );
                        }
                    );
                }
            )

            ->when(
                $this->typeFilter !== 'all',

                fn (Builder $query) =>
                    $query->where(
                        'provider_type',
                        $this->typeFilter
                    )
            )

            ->when(
                $this->statusFilter === 'enabled',

                fn (Builder $query) =>
                    $query->where(
                        'is_enabled',
                        true
                    )
            )

            ->when(
                $this->statusFilter === 'disabled',

                fn (Builder $query) =>
                    $query->where(
                        'is_enabled',
                        false
                    )
            )

            ->orderByDesc(
                'is_default'
            )

            ->orderBy(
                'sort_order'
            )

            ->orderBy(
                'name'
            )

            ->paginate(
                $this->perPage
            );

        return view(
            'livewire.admin.ai-providers',
            [
                'providers' =>
                    $providers,

                'providerTypes' =>
                    $this->providerTypes(),

                'stats' => [
                    'providers' =>
                        AiProvider::query()->count(),

                    'enabled' =>
                        AiProvider::query()
                            ->where(
                                'is_enabled',
                                true
                            )
                            ->count(),

                    'default' =>
                        AiProvider::query()
                            ->where(
                                'is_default',
                                true
                            )
                            ->value('name')
                        ?? 'None',

                    'failed' =>
                        AiProvider::query()
                            ->where(
                                'last_test_status',
                                'failed'
                            )
                            ->count(),
                ],
            ]
        );
    }
}
