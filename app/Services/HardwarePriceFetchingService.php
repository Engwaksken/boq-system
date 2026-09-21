<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiProvider;
use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Models\PriceHistory;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class HardwarePriceFetchingService
{
    public function __construct(
        private readonly AiProviderService $ai
    ) {
    }

    /**
     * Fetch a small number of current market prices for one category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchPricesForCategory(
        string $category,
        string $location,
        int $limit = 10,
        ?int $organisationId = null
    ): array {
        $categoryRecord = HardwareCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($category))])
            ->first();

        $items = $categoryRecord?->default_items ?? [];

        if (! is_array($items) || $items === []) {
            $items = [$category];
        }

        $items = array_values(array_unique(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            $items
        ))));

        $limit = max(1, min(50, $limit));

        // Repeat generic category only if there are fewer configured items.
        while (count($items) < $limit) {
            $items[] = $category.' item '.(count($items) + 1);
        }

        $items = array_slice($items, 0, $limit);

        $webSearch = $this->defaultProviderUsesWebSearch();

        $results = [];
        $lastException = null;

        foreach ($items as $itemName) {
            try {
                $prompt = $this->buildPrompt(
                    itemName: $itemName,
                    category: $categoryRecord?->name ?? $category,
                    location: $location,
                    webSearch: $webSearch
                );

                $response = $this->ai->json(
                    prompt: $prompt,
                    operation: 'hardware_price_scan',
                    organisationId: $organisationId,
                    context: [
                        'metadata' => [
                            'category' => $categoryRecord?->name ?? $category,
                            'location' => $location,
                        ],
                    ]
                );

                $grounding = $response['_grounding'] ?? [];

                unset($response['_grounding']);

                $price = (float) ($response['price'] ?? 0);

                if ($price <= 0) {
                    throw new RuntimeException('AI provider returned an invalid price.');
                }

                $sourceUrl = $this->nullableString($response['source_url'] ?? null)
                    ?? ($grounding[0]['uri'] ?? null);

                $groundedTitle = $grounding[0]['title'] ?? null;

                $sourceReference = $this->nullableString(
                    $response['source_reference'] ?? null
                ) ?? ($groundedTitle
                    ? 'Live web search result: '.$groundedTitle
                    : 'AI-assisted Uganda market estimate');

                $results[] = [
                    'hardware_category_id' => $categoryRecord?->id,
                    'item_name' => trim((string) ($response['item_name'] ?? $itemName)),
                    'brand' => $this->nullableString($response['brand'] ?? null),
                    'category' => $categoryRecord?->name ?? $category,
                    'specification' => $this->nullableString($response['specification'] ?? null),
                    'unit' => trim((string) ($response['unit'] ?? 'piece')),
                    'price' => $price,
                    'currency' => strtoupper(trim((string) ($response['currency'] ?? 'UGX'))),
                    'supplier' => trim((string) ($response['supplier'] ?? 'Uganda market estimate')),
                    'location' => trim((string) ($response['location'] ?? $location)),
                    'source_url' => $sourceUrl,
                    'source_reference' => $sourceReference,
                    'fetched_at' => now(),
                    'is_active' => true,
                    'ai_metadata' => [
                        'confidence' => $response['confidence'] ?? null,
                        'notes' => $response['notes'] ?? null,
                        'source' => 'configured_ai_provider',
                        'grounding_sources' => $grounding,
                    ],
                ];
            } catch (Throwable $exception) {
                $lastException = $exception;

                Log::warning('Hardware price scan item failed', [
                    'category' => $category,
                    'item' => $itemName,
                    'location' => $location,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($results === []) {
            $message = 'No hardware prices could be fetched. Check the default AI provider configuration and connection.';

            if ($lastException !== null) {
                $message .= ' Last attempt: '.$lastException->getMessage();
            }

            throw new RuntimeException($message, previous: $lastException);
        }

        return $results;
    }

    /**
     * Fetch and store daily prices for all active categories.
     *
     * @return array{fetched:int,created:int,updated:int,errors:array<int,string>}
     */
    public function fetchDailyPrices(
        int $organisationId,
        string $location = 'Kampala',
        int $limitPerCategory = 3
    ): array {
        $results = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        $categories = HardwareCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            $results['errors'][] = 'No active hardware categories are configured.';

            return $results;
        }

        foreach ($categories as $category) {
            try {
                $items = $this->fetchPricesForCategory(
                    category: $category->name,
                    location: $location,
                    limit: $limitPerCategory,
                    organisationId: $organisationId
                );

                foreach ($items as $item) {
                    $status = $this->storePrice(
                        organisationId: $organisationId,
                        priceData: $item
                    );

                    $results['fetched']++;

                    if ($status === 'created') {
                        $results['created']++;
                    } else {
                        $results['updated']++;
                    }
                }
            } catch (Throwable $exception) {
                $results['errors'][] = $category->name.': '.$exception->getMessage();

                Log::error('Daily hardware category fetch failed', [
                    'organisation_id' => $organisationId,
                    'category' => $category->name,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $results;
    }

    private function defaultProviderUsesWebSearch(): bool
    {
        try {
            $provider = AiProvider::query()
                ->enabled()
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
        } catch (Throwable) {
            return false;
        }

        return $provider !== null
            && (bool) data_get($provider->settings, 'web_search', false);
    }

    private function buildPrompt(
        string $itemName,
        string $category,
        string $location,
        bool $webSearch = false
    ): string {
        $instructions = $webSearch
            ? 'Search the live web for REAL, current retail construction-material prices in Uganda. Prefer actual supplier listings and market pages. Return the verified market price, not a guess.'
            : 'Estimate a current retail construction-material price in Uganda from your knowledge.';

        return <<<PROMPT
{$instructions}

Return JSON only in this exact structure:
{
  "item_name": "string",
  "brand": null,
  "category": "{$category}",
  "specification": "string or null",
  "unit": "bag|ton|piece|meter|litre|kg|roll|sheet|box|sqm",
  "price": 0,
  "currency": "UGX",
  "supplier": "supplier or market reference",
  "location": "{$location}",
  "source_url": null,
  "source_reference": "brief source/reference description",
  "confidence": 0,
  "notes": "brief note"
}

Item: {$itemName}
Category: {$category}
Location: {$location}, Uganda

Rules:
- price must be numeric and greater than zero;
- currency must be UGX unless there is a compelling reason otherwise;
- when web search is available, base the price only on the returned search results and fill source_url and source_reference from the cited sources;
- do not claim a named supplier if you cannot support it;
- reflect current Uganda market context;
- return JSON only.
PROMPT;
    }

    private function storePrice(
        int $organisationId,
        array $priceData
    ): string {
        $existing = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->where('item_name', $priceData['item_name'])
            ->where('category', $priceData['category'])
            ->where('supplier', $priceData['supplier'])
            ->where('location', $priceData['location'])
            ->first();

        if ($existing) {
            if ((float) $existing->price !== (float) $priceData['price']) {
                PriceHistory::create([
                    'organisation_id' => $organisationId,
                    'hardware_price_id' => $existing->id,
                    'price' => $existing->price,
                    'currency' => $existing->currency,
                    'supplier' => $existing->supplier,
                    'location' => $existing->location,
                    'source_url' => $existing->source_url,
                    'recorded_at' => $existing->fetched_at ?? now(),
                ]);
            }

            $existing->update(array_merge($priceData, [
                'organisation_id' => $organisationId,
                'fetched_at' => now(),
                'is_active' => true,
            ]));

            return 'updated';
        }

        HardwarePrice::create(array_merge($priceData, [
            'organisation_id' => $organisationId,
            'fetched_at' => now(),
            'is_active' => true,
        ]));

        return 'created';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
