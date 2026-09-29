<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Services\HardwarePriceFetchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

#[Layout('layouts.app')]
class HardwareScanner extends Component
{
    use WithFileUploads;

    /**
     * Livewire update requests skip route middleware, so re-check on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public array $scanForm = [
        'price_type' =>
            HardwarePrice::TYPE_HARDWARE,

        'category' => '',

        'location' => '',

        'limit' => 5,
    ];

    public $csvFile = null;

    public bool $isScanning = false;

    public array $scanResults = [];

    public array $importResults = [];

    public bool $showCategoryModal = false;

    public bool $showDeleteCategoryModal = false;

    public ?int $editingCategoryId = null;

    public ?int $deleteCategoryId = null;

    public array $categoryForm = [
        'name' => '',
        'description' => '',
        'default_items' => '',
        'sort_order' => 100,
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->scanForm['location'] = \App\Support\Regional::marketLocation();

        $this->ensureSelectedCategory();
    }

    public function scanPrices(
        HardwarePriceFetchingService $service
    ): void {
        $this->validate([
            'scanForm.price_type' => [
                'required',
                Rule::in([
                    HardwarePrice::TYPE_HARDWARE,
                    HardwarePrice::TYPE_FACTORY,
                ]),
            ],

            'scanForm.category' => [
                'required',
                'string',
                'max:100',
            ],

            'scanForm.location' => [
                'required',
                'string',
                'max:150',
            ],

            'scanForm.limit' => [
                'required',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $organisationId =
            auth()
                ->user()
                ?->organisation_id;

        if (! $organisationId) {
            session()->flash(
                'modal_error',
                'Your account is not assigned to an organisation.'
            );

            return;
        }

        $this->isScanning = true;

        $this->scanResults = [];

        try {
            $items =
                $service
                    ->fetchPricesForCategory(
                        category:
                            $this->scanForm['category'],

                        location:
                            $this->scanForm['location'],

                        limit:
                            (int) $this->scanForm['limit'],

                        organisationId:
                            (int) $organisationId,

                        priceType:
                            $this->scanForm['price_type']
                    );

            foreach (
                $items
                as $item
            ) {
                $existing =
                    HardwarePrice::query()
                        ->where(
                            'organisation_id',
                            $organisationId
                        )
                        ->where(
                            'price_type',
                            $item['price_type']
                        )
                        ->where(
                            'item_name',
                            $item['item_name']
                        )
                        ->where(
                            'category',
                            $item['category']
                        )
                        ->where(
                            'supplier',
                            $item['supplier']
                        )
                        ->where(
                            'location',
                            $item['location']
                        )
                        ->first();

                if ($existing) {
                    $existing->update([
                        ...$item,
                        'fetched_at' =>
                            now(),
                        'is_active' =>
                            true,
                    ]);

                    $status =
                        'updated';
                } else {
                    HardwarePrice::create([
                        ...$item,
                        'organisation_id' =>
                            $organisationId,
                        'fetched_at' =>
                            now(),
                        'is_active' =>
                            true,
                    ]);

                    $status =
                        'created';
                }

                $this->scanResults[] = [
                    'status' =>
                        $status,

                    'item' =>
                        $item['item_name'],

                    'price_type' =>
                        $item['price_type'],

                    'price' =>
                        (float) $item['price'],

                    'currency' =>
                        $item['currency']
                        ?? \App\Support\Regional::currency(),

                    'supplier' =>
                        $item['supplier']
                        ?? null,
                ];
            }

            session()->flash(
                'modal_success',
                'Price scan completed. '
                .count(
                    $this->scanResults
                )
                .' items processed.'
            );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            session()->flash(
                'modal_error',
                'Price scan failed. '
                .$this->safeError(
                    $exception
                )
            );
        } finally {
            $this->isScanning = false;
        }
    }

    public function downloadCsvTemplate(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                $stream =
                    fopen(
                        'php://output',
                        'wb'
                    );

                fputcsv(
                    $stream,
                    [
                        'item_name',
                        'brand',
                        'category',
                        'price_type',
                        'specification',
                        'unit',
                        'price',
                        'currency',
                        'supplier',
                        'location',
                        'source_url',
                        'source_reference',
                    ]
                );

                fputcsv(
                    $stream,
                    [
                        'Portland Cement',
                        'Hima',
                        'Cement',
                        'factory',
                        '42.5N 50kg',
                        'bag',
                        '38000',
                        \App\Support\Regional::currency(),
                        'Example Cement Factory',
                        \App\Support\Regional::marketLocation() ?: 'Main market',
                        '',
                        'Factory price list',
                    ]
                );

                fclose(
                    $stream
                );
            },
            'market-price-import-template.csv',
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    public function createCategory(): void
    {
        $this->resetCategoryForm();

        $this->showCategoryModal = true;
    }

    public function editCategory(
        int $id
    ): void {
        $category =
            HardwareCategory::query()
                ->findOrFail($id);

        $this->editingCategoryId =
            $category->id;

        $this->categoryForm = [
            'name' =>
                $category->name,

            'description' =>
                (string) (
                    $category->description
                    ?? ''
                ),

            'default_items' =>
                implode(
                    ', ',
                    $category->itemNames()
                ),

            'sort_order' =>
                $category->sort_order,

            'is_active' =>
                $category->is_active,
        ];

        $this->showCategoryModal =
            true;

        $this->resetValidation();
    }

    public function saveCategory(): void
    {
        $validated =
            $this->validate([
                'categoryForm.name' => [
                    'required',
                    'string',
                    'max:100',

                    Rule::unique(
                        'hardware_categories',
                        'name'
                    )->ignore(
                        $this->editingCategoryId
                    ),
                ],

                'categoryForm.description' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'categoryForm.default_items' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'categoryForm.sort_order' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:100000',
                ],

                'categoryForm.is_active' => [
                    'boolean',
                ],
            ]);

        DB::transaction(
            function () use (
                $validated
            ): void {
                $category =
                    $this->editingCategoryId
                        ? HardwareCategory::query()
                            ->findOrFail(
                                $this->editingCategoryId
                            )
                        : new HardwareCategory();

                $oldName =
                    $category->exists
                        ? $category->name
                        : null;

                $items =
                    array_values(
                        array_unique(
                            array_filter(
                                array_map(
                                    static fn ($item) =>
                                        trim(
                                            (string) $item
                                        ),
                                    explode(
                                        ',',
                                        (string) (
                                            $validated[
                                                'categoryForm'
                                            ][
                                                'default_items'
                                            ]
                                            ?? ''
                                        )
                                    )
                                )
                            )
                        )
                    );

                $category->fill([
                    'name' =>
                        trim(
                            $validated[
                                'categoryForm'
                            ]['name']
                        ),

                    'description' =>
                        $this->nullable(
                            $validated[
                                'categoryForm'
                            ]['description']
                            ?? null
                        ),

                    'default_items' =>
                        $items,

                    'sort_order' =>
                        (int) $validated[
                            'categoryForm'
                        ]['sort_order'],

                    'is_active' =>
                        (bool) $validated[
                            'categoryForm'
                        ]['is_active'],

                    'updated_by' =>
                        auth()->id(),
                ]);

                if (
                    ! $category->exists
                ) {
                    $category->created_by =
                        auth()->id();
                }

                $category->save();

                if (
                    $oldName
                    && $oldName
                        !== $category->name
                ) {
                    HardwarePrice::query()
                        ->where(
                            'hardware_category_id',
                            $category->id
                        )
                        ->update([
                            'category' =>
                                $category->name,
                        ]);
                }
            }
        );

        session()->flash(
            'modal_success',
            $this->editingCategoryId
                ? 'Category updated.'
                : 'Category created.'
        );

        $this->cancelCategory();

        $this->ensureSelectedCategory();
    }

    public function toggleCategory(
        int $id
    ): void {
        $category =
            HardwareCategory::query()
                ->findOrFail($id);

        $category->update([
            'is_active' =>
                ! $category->is_active,

            'updated_by' =>
                auth()->id(),
        ]);

        $this->ensureSelectedCategory();
    }

    public function confirmDeleteCategory(
        int $id
    ): void {
        $this->deleteCategoryId =
            $id;

        $this->showDeleteCategoryModal =
            true;
    }

    public function deleteCategory(): void
    {
        if (! $this->deleteCategoryId) {
            return;
        }

        $category =
            HardwareCategory::query()
                ->findOrFail(
                    $this->deleteCategoryId
                );

        if (
            $category
                ->hardwarePrices()
                ->exists()
        ) {
            session()->flash(
                'modal_error',
                'This category is already used by price records. Deactivate it instead.'
            );

            $this->showDeleteCategoryModal =
                false;

            return;
        }

        $category->delete();

        $this->showDeleteCategoryModal =
            false;

        $this->deleteCategoryId =
            null;

        session()->flash(
            'modal_success',
            'Category deleted.'
        );

        $this->ensureSelectedCategory();
    }

    public function cancelCategory(): void
    {
        $this->showCategoryModal =
            false;

        $this->resetCategoryForm();

        $this->resetValidation();
    }

    public function render()
    {
        $categories =
            HardwareCategory::query()
                ->withCount(['items' => fn ($query) => $query->where('is_active', true)])
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'name'
                )
                ->get();

        $activeCategories =
            $categories
                ->where(
                    'is_active',
                    true
                )
                ->values();

        $stats = [
            'categories' =>
                $categories->count(),

            'active_categories' =>
                $activeCategories->count(),

            'prices' =>
                HardwarePrice::query()
                    ->count(),

            'active_prices' =>
                HardwarePrice::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->count(),

            'hardware_prices' =>
                HardwarePrice::query()
                    ->where(
                        'price_type',
                        HardwarePrice::TYPE_HARDWARE
                    )
                    ->count(),

            'factory_prices' =>
                HardwarePrice::query()
                    ->where(
                        'price_type',
                        HardwarePrice::TYPE_FACTORY
                    )
                    ->count(),
        ];

        return view(
            'livewire.admin.hardware-scanner',
            [
                'categories' =>
                    $categories,

                'activeCategories' =>
                    $activeCategories,

                'stats' =>
                    $stats,
            ]
        );
    }

    private function ensureSelectedCategory(): void
    {
        if (
            $this->scanForm['category']
            !== ''
        ) {
            $exists =
                HardwareCategory::query()
                    ->where(
                        'name',
                        $this->scanForm[
                            'category'
                        ]
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->exists();

            if ($exists) {
                return;
            }
        }

        $this->scanForm['category'] =
            HardwareCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->value(
                    'name'
                )
            ?? '';
    }

    private function resetCategoryForm(): void
    {
        $this->editingCategoryId =
            null;

        $this->categoryForm = [
            'name' => '',
            'description' => '',
            'default_items' => '',
            'sort_order' => 100,
            'is_active' => true,
        ];
    }

    private function nullable(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        return $value === ''
            ? null
            : $value;
    }

    private function safeError(
        Throwable $exception
    ): string {
        // Explain the actual cause; provider error details can contain keys, so they
        // are summarised, never shown raw.
        $root = $exception;
        while ($root->getPrevious() !== null) {
            $root = $root->getPrevious();
        }

        if ($root instanceof \Illuminate\Http\Client\RequestException) {
            return match (true) {
                in_array($root->response->status(), [401, 403], true) => 'The AI provider rejected the API key. Check it under AI API Settings.',
                $root->response->status() === 404 => 'The AI model was not found. Check the model name under AI API Settings.',
                $root->response->status() === 429 => 'The AI provider rate limit or quota was reached. Try again later or use another provider.',
                $root->response->status() === 402 => 'The AI provider account has no credit left. Top it up or use another provider.',
                default => 'The AI provider returned an error ('.$root->response->status().'). Try again or use another provider.',
            };
        }

        if ($root instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'The AI provider could not be reached (timeout). Try again.';
        }

        $message = $root->getMessage();
        foreach (['No AI provider is set up', 'No enabled AI provider', 'invalid JSON', 'API key is missing'] as $known) {
            if (str_contains($message, $known)) {
                return Str::limit($message, 200);
            }
        }

        if (
            app()->environment(
                'production'
            )
        ) {
            return 'Please check the AI provider configuration and try again.';
        }

        return Str::limit(
            $exception->getMessage(),
            300
        );
    }
}