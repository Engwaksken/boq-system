<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Models\PriceHistory;
use App\Models\Supplier;
use App\Support\Regional;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class HardwarePriceFetchingService
{
    public function fetchPricesForCategory(
        string $category,
        string $location,
        int $limit,
        int $organisationId,
        string $priceType = HardwarePrice::TYPE_HARDWARE
    ): array {
        $priceType =
            $this->normalisePriceType(
                $priceType
            );

        $items =
            $this->itemsForCategory(
                $category
            );

        if ($items === []) {
            $items = [
                $category,
            ];
        }

        $items =
            array_slice(
                array_values(
                    array_unique(
                        $items
                    )
                ),
                0,
                max(
                    1,
                    min(
                        50,
                        $limit
                    )
                )
            );

        $results = [];

        foreach (
            $items
            as $itemName
        ) {
            $result =
                $this->fetchPriceForItem(
                    itemName:
                        $itemName,

                    category:
                        $category,

                    location:
                        $location,

                    priceType:
                        $priceType
                );

            if ($result !== null) {
                $results[] =
                    $result;
            }
        }

        return $results;
    }

    public function fetchDailyPrices(
        ?int $organisationId = null,
        ?string $location = null,
        int $limit = 3,
        string $priceType = HardwarePrice::TYPE_HARDWARE
    ): array {
        $location = trim((string) $location) ?: Regional::marketLocation();

        $results = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        $organisationId =
            $organisationId
            ?? auth()
                ->user()
                ?->organisation_id;

        if (! $organisationId) {
            throw new RuntimeException(
                'An organisation is required to store fetched prices.'
            );
        }

        $categories =
            HardwareCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->pluck('name')
                ->all();

        foreach (
            $categories
            as $category
        ) {
            try {
                $items =
                    $this->fetchPricesForCategory(
                        category:
                            $category,

                        location:
                            $location,

                        limit:
                            $limit,

                        organisationId:
                            $organisationId,

                        priceType:
                            $priceType
                    );

                foreach (
                    $items
                    as $item
                ) {
                    $status =
                        $this->storePrice(
                            $organisationId,
                            $item
                        );

                    $results['fetched']++;

                    $results[$status]++;
                }
            } catch (Throwable $exception) {
                report(
                    $exception
                );

                $results['errors'][] =
                    "{$category}: {$exception->getMessage()}";
            }
        }

        return $results;
    }

    private function fetchPriceForItem(
        string $itemName,
        string $category,
        string $location,
        string $priceType
    ): ?array {
        $sourceNames = $this->getSuppliers($priceType);

        $source = $sourceNames !== []
            ? $sourceNames[array_rand($sourceNames)]
            : ($priceType === HardwarePrice::TYPE_FACTORY ? 'a local manufacturer' : 'a local hardware supplier');

        $prompt =
            $this->buildPricePrompt(
                itemName:
                    $itemName,

                category:
                    $category,

                source:
                    $source,

                location:
                    $location,

                priceType:
                    $priceType
            );

        try {
            $response =
                Http::timeout(45)
                    ->withHeaders([
                        'x-goog-api-key' =>
                            (string) config(
                                'services.gemini.key'
                            ),

                        'Content-Type' =>
                            'application/json',
                    ])
                    ->post(
                        rtrim(
                            (string) config(
                                'services.gemini.base_url'
                            ),
                            '/'
                        )
                        .'/v1beta/models/'
                        .urlencode(
                            (string) config(
                                'services.gemini.model'
                            )
                        )
                        .':generateContent',
                        [
                            'contents' => [
                                [
                                    'parts' => [
                                        [
                                            'text' =>
                                                $prompt,
                                        ],
                                    ],
                                ],
                            ],

                            'generationConfig' => [
                                'temperature' =>
                                    0.2,

                                'maxOutputTokens' =>
                                    1024,
                            ],
                        ]
                    );

            if (
                ! $response->successful()
            ) {
                throw new RuntimeException(
                    $response->json(
                        'error.message'
                    )
                    ?: 'AI price request failed.'
                );
            }

            $text =
                $response->json(
                    'candidates.0.content.parts.0.text'
                )
                ?? '';

            return $this->parsePriceResponse(
                text:
                    $text,

                fallbackItem:
                    $itemName,

                fallbackCategory:
                    $category,

                fallbackSource:
                    $source,

                fallbackLocation:
                    $location,

                priceType:
                    $priceType
            );
        } catch (Throwable $exception) {
            Log::warning(
                'AI price fetch failed.',
                [
                    'item' =>
                        $itemName,

                    'category' =>
                        $category,

                    'price_type' =>
                        $priceType,

                    'location' =>
                        $location,

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw $exception;
        }
    }

    private function buildPricePrompt(
        string $itemName,
        string $category,
        string $source,
        string $location,
        string $priceType
    ): string {
        $typeInstruction =
            $priceType
            === HardwarePrice::TYPE_FACTORY
                ? <<<TEXT
Find an ex-factory, manufacturer-direct or authorised factory-gate price.
Prefer manufacturer price lists, factory quotations or direct manufacturer pricing.
Do not present a retail hardware-shop price as a factory price.
TEXT
                : <<<TEXT
Find a current hardware supplier, distributor, retail or wholesale market price.
Use a realistic construction-market supplier price.
TEXT;

        $country = Regional::countryName();
        $place = trim(implode(', ', array_filter([$location, $country]))) ?: 'the local market';
        $currency = Regional::currency();

        return <<<PROMPT
You are a construction material price researcher for {$place}.

{$typeInstruction}

Item: {$itemName}
Category: {$category}
Suggested source: {$source}
Location: {$place}
Price type: {$priceType}
Currency: {$currency} (convert if the source uses another currency)

Return ONLY one valid JSON object.

{
  "item_name": "exact item name",
  "brand": "brand/manufacturer or null",
  "category": "{$category}",
  "price_type": "{$priceType}",
  "specification": "grade, size, thickness or packaging",
  "unit": "bag, tonne, piece, metre, litre, kg, roll, sheet or box",
  "price": 123456.78,
  "currency": "{$currency}",
  "supplier": "supplier, distributor, manufacturer or factory name",
  "location": "{$location}",
  "source_url": null,
  "source_reference": "manufacturer price list, supplier quotation, market survey or dated source",
  "confidence": 0.80,
  "notes": "short note explaining whether price is retail, wholesale or ex-factory"
}

Rules:
- price must be numeric.
- price_type must be "{$priceType}".
- do not invent a source URL.
- if the price cannot be reasonably estimated, return {"price": null}.
PROMPT;
    }

    private function parsePriceResponse(
        string $text,
        string $fallbackItem,
        string $fallbackCategory,
        string $fallbackSource,
        string $fallbackLocation,
        string $priceType
    ): ?array {
        $jsonStart =
            strpos(
                $text,
                '{'
            );

        $jsonEnd =
            strrpos(
                $text,
                '}'
            );

        if (
            $jsonStart === false
            || $jsonEnd === false
        ) {
            return null;
        }

        $json =
            substr(
                $text,
                $jsonStart,
                $jsonEnd
                    - $jsonStart
                    + 1
            );

        $data =
            json_decode(
                $json,
                true
            );

        if (
            ! is_array($data)
            || ! isset(
                $data['price']
            )
            || ! is_numeric(
                $data['price']
            )
            || (float) $data['price']
                <= 0
        ) {
            return null;
        }

        return [
            'item_name' =>
                trim(
                    (string) (
                        $data['item_name']
                        ?? $fallbackItem
                    )
                ),

            'brand' =>
                $this->nullable(
                    $data['brand']
                    ?? null
                ),

            'category' =>
                trim(
                    (string) (
                        $data['category']
                        ?? $fallbackCategory
                    )
                ),

            'price_type' =>
                $priceType,

            'specification' =>
                $this->nullable(
                    $data['specification']
                    ?? null
                ),

            'unit' =>
                trim(
                    (string) (
                        $data['unit']
                        ?? 'piece'
                    )
                ),

            'price' =>
                (float) $data['price'],

            'currency' =>
                strtoupper(
                    trim(
                        (string) (
                            $data['currency']
                            ?? Regional::currency()
                        )
                    )
                ),

            'supplier' =>
                trim(
                    (string) (
                        $data['supplier']
                        ?? $fallbackSource
                    )
                ),

            'location' =>
                trim(
                    (string) (
                        $data['location']
                        ?? $fallbackLocation
                    )
                ),

            'source_url' =>
                $this->nullable(
                    $data['source_url']
                    ?? null
                ),

            'source_reference' =>
                $this->nullable(
                    $data['source_reference']
                    ?? 'AI-assisted market research'
                ),

            'fetched_at' =>
                now(),

            'is_active' =>
                true,

            'ai_metadata' => [
                'confidence' =>
                    (float) (
                        $data['confidence']
                        ?? 0.7
                    ),

                'notes' =>
                    $data['notes']
                    ?? null,

                'source' =>
                    'gemini_ai',

                'price_type' =>
                    $priceType,
            ],
        ];
    }

    private function storePrice(
        int $organisationId,
        array $priceData
    ): string {
        $existing =
            HardwarePrice::query()
                ->where(
                    'organisation_id',
                    $organisationId
                )
                ->where(
                    'price_type',
                    $priceData['price_type']
                )
                ->where(
                    'item_name',
                    $priceData['item_name']
                )
                ->where(
                    'category',
                    $priceData['category']
                )
                ->where(
                    'supplier',
                    $priceData['supplier']
                )
                ->where(
                    'location',
                    $priceData['location']
                )
                ->first();

        if ($existing) {
            if (
                number_format(
                    (float) $existing->price,
                    2,
                    '.',
                    ''
                )
                !==
                number_format(
                    (float) $priceData['price'],
                    2,
                    '.',
                    ''
                )
            ) {
                PriceHistory::create([
                    'organisation_id' =>
                        $organisationId,

                    'hardware_price_id' =>
                        $existing->id,

                    'price' =>
                        $existing->price,

                    'currency' =>
                        $existing->currency,

                    'supplier' =>
                        $existing->supplier,

                    'location' =>
                        $existing->location,

                    'source_url' =>
                        $existing->source_url,

                    'recorded_at' =>
                        $existing->fetched_at,

                    'metadata' => [
                        'price_type' =>
                            $existing->price_type,
                    ],
                ]);
            }

            $existing->update(
                $priceData
            );

            return 'updated';
        }

        HardwarePrice::create([
            ...$priceData,
            'organisation_id' =>
                $organisationId,
        ]);

        return 'created';
    }

    private function itemsForCategory(
        string $category
    ): array {
        $configured =
            HardwareCategory::query()
                ->where(
                    'name',
                    $category
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        return $configured?->itemNames() ?? [];
    }

    private function normalisePriceType(
        string $priceType
    ): string {
        return $priceType
            === HardwarePrice::TYPE_FACTORY
                ? HardwarePrice::TYPE_FACTORY
                : HardwarePrice::TYPE_HARDWARE;
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

    public function getCategories(): array
    {
        $categories =
            HardwareCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->pluck(
                    'name'
                )
                ->all();

        return $categories;
    }

    public function getItemsByCategory(
        string $category
    ): array {
        return $this->itemsForCategory(
            $category
        );
    }

    /**
     * Supplier/manufacturer names known to the system for the price type.
     *
     * @return list<string>
     */
    public function getSuppliers(
        string $priceType =
            HardwarePrice::TYPE_HARDWARE
    ): array {
        $recorded = HardwarePrice::query()
            ->where('price_type', $this->normalisePriceType($priceType))
            ->whereNotNull('supplier')
            ->distinct()
            ->pluck('supplier');

        $managed = $priceType === HardwarePrice::TYPE_FACTORY
            ? collect()
            : Supplier::query()->where('is_active', true)->pluck('name');

        return $recorded->merge($managed)
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Locations already used on prices, plus the configured market location.
     *
     * @return list<string>
     */
    public function getLocations(): array
    {
        return collect([Regional::marketLocation()])
            ->merge(HardwarePrice::query()->whereNotNull('location')->distinct()->orderBy('location')->pluck('location'))
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->unique()
            ->values()
            ->all();
    }
}