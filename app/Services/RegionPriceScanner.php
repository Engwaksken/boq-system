<?php

namespace App\Services;

use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Models\Supplier;
use App\Support\Regional;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Scans the prices of one category across a region: one AI request returns
 * prices from different hardware shops and factories in the region's towns,
 * each stored at its town with the region set, so prices can be compared and
 * filtered by region.
 */
class RegionPriceScanner
{
    public const MAX_ITEMS = 40;

    public const MAX_TOWNS = 25;

    public function __construct(private AiProviderService $ai) {}

    /** Regions known from suppliers and prices. */
    public static function regions(): array
    {
        return Supplier::query()->whereNotNull('region')->where('region', '!=', '')->distinct()->pluck('region')
            ->merge(HardwarePrice::query()->whereNotNull('region')->where('region', '!=', '')->distinct()->pluck('region'))
            ->map(fn ($r) => trim((string) $r))->filter()
            ->unique(fn ($r) => mb_strtolower($r))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /** Towns/locations known to be in the region (from suppliers and prices). */
    public static function towns(string $region): array
    {
        $lower = mb_strtolower(trim($region));
        if ($lower === '') {
            return [];
        }

        return Supplier::query()->whereRaw('LOWER(region) = ?', [$lower])->whereNotNull('location')->where('location', '!=', '')->distinct()->pluck('location')
            ->merge(HardwarePrice::query()->whereRaw('LOWER(region) = ?', [$lower])->whereNotNull('location')->where('location', '!=', '')->distinct()->pluck('location'))
            ->map(fn ($t) => trim((string) $t))->filter()
            ->reject(fn ($t) => mb_strtolower($t) === $lower)
            ->unique(fn ($t) => mb_strtolower($t))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /**
     * Active suppliers or factories with a website in the region.
     */
    public static function suppliersInRegion(string $region, string $priceType)
    {
        $type = $priceType === HardwarePrice::TYPE_FACTORY ? Supplier::TYPE_FACTORY : Supplier::TYPE_SUPPLIER;
        $towns = self::towns($region);

        return Supplier::query()
            ->where('is_active', true)
            ->where('type', $type)
            ->whereNotNull('website_url')->where('website_url', '!=', '')
            ->where(fn ($q) => $q->whereRaw('LOWER(region) = ?', [mb_strtolower(trim($region))])
                ->orWhereIn('location', $towns === [] ? ['__none__'] : $towns))
            ->orderBy('name');
    }

    /**
     * Scan and store. Returns one row per stored price.
     *
     * @param  list<string>  $towns  towns to cover (empty = the whole region)
     * @return list<array{status: string, item: string, price_type: string, price: float, currency: string, supplier: ?string, location: string}>
     */
    public function scan(string $category, string $region, array $towns, int $limit, ?int $organisationId, string $priceType): array
    {
        $region = trim($region);
        if ($region === '') {
            throw new RuntimeException('Choose a region to scan.');
        }

        $priceType = $priceType === HardwarePrice::TYPE_FACTORY ? HardwarePrice::TYPE_FACTORY : HardwarePrice::TYPE_HARDWARE;
        $limit = max(1, min(self::MAX_ITEMS, $limit));
        $towns = array_slice(array_values(array_filter(array_map('trim', $towns))), 0, self::MAX_TOWNS);
        $currency = Regional::currency();
        $country = Regional::marketLocation();
        $items = HardwareCategory::where('name', $category)->value('default_items');
        $items = is_array($items) ? array_slice(array_filter($items), 0, 20) : [];

        $known = Supplier::query()->where('is_active', true)
            ->where('type', $priceType === HardwarePrice::TYPE_FACTORY ? Supplier::TYPE_FACTORY : Supplier::TYPE_SUPPLIER)
            ->where(fn ($q) => $q->whereRaw('LOWER(region) = ?', [mb_strtolower($region)])->orWhereIn('location', $towns === [] ? ['__none__'] : $towns))
            ->limit(30)->pluck('name')->all();

        $kind = $priceType === HardwarePrice::TYPE_FACTORY ? 'factories and manufacturers' : 'hardware shops and building material suppliers';
        $prompt = implode("\n", array_filter([
            'You are a construction material price researcher.',
            "Find current {$category} prices from different {$kind} in the {$region} region ({$country}).",
            $towns !== [] ? 'Cover these towns, spreading the results across them: '.implode(', ', $towns).'.' : 'Cover the main towns of the region.',
            $items !== [] ? 'Typical items: '.implode(', ', $items).'.' : null,
            $known !== [] ? 'Known businesses in the region (include them when they have prices): '.implode(', ', $known).'.' : null,
            "Return at most {$limit} prices. Compare the same items at different businesses and towns where possible.",
            "Prices in {$currency} (convert if needed). Only include prices you are confident about; never invent them.",
            'Return JSON only: {"items":[{"item_name":string,"brand":string|null,"specification":string|null,"unit":string,"price":number,"currency":string,"supplier":string,"town":string,"source_url":string|null,"confidence":number}]}',
        ]));

        $answer = $this->ai->json($prompt, 'region_price_scan', $organisationId, context: ['metadata' => ['region' => $region, 'category' => $category, 'price_type' => $priceType]]);
        $grounded = ! empty($answer['_grounding']);
        $found = is_array($answer['items'] ?? null) ? array_slice($answer['items'], 0, $limit) : [];

        $townsByLower = collect($towns ?: self::towns($region))->keyBy(fn ($t) => mb_strtolower($t));
        $results = [];

        foreach ($found as $row) {
            $name = trim((string) ($row['item_name'] ?? ''));
            $price = is_numeric($row['price'] ?? null) ? (float) $row['price'] : 0.0;
            if ($name === '' || $price <= 0) {
                continue;
            }

            $supplierName = mb_substr(trim((string) ($row['supplier'] ?? '')), 0, 150) ?: ($priceType === HardwarePrice::TYPE_FACTORY ? 'Local manufacturer' : 'Local hardware');
            $town = trim((string) ($row['town'] ?? ''));
            // Keep the location to a known town of the region, else the region itself.
            $location = $townsByLower->get(mb_strtolower($town)) ?? ($town !== '' && $towns === [] ? $town : $region);
            $supplierId = Supplier::whereRaw('LOWER(name) = ?', [mb_strtolower($supplierName)])->value('id');

            $attributes = [
                'item_name' => mb_substr($name, 0, 255),
                'brand' => filled($row['brand'] ?? null) ? mb_substr(trim((string) $row['brand']), 0, 255) : null,
                'category' => $category,
                'price_type' => $priceType,
                'specification' => filled($row['specification'] ?? null) ? trim((string) $row['specification']) : null,
                'unit' => mb_substr(trim((string) ($row['unit'] ?? '')) ?: 'piece', 0, 50),
                'price' => round($price, 2),
                'currency' => strtoupper(trim((string) ($row['currency'] ?? ''))) ?: $currency,
                'supplier' => $supplierName,
                'supplier_id' => $supplierId,
                'location' => mb_substr($location, 0, 150),
                'region' => mb_substr($region, 0, 100),
                'source_url' => filled($row['source_url'] ?? null) ? mb_substr(trim((string) $row['source_url']), 0, 2048) : null,
                'source_reference' => 'Region price scan',
                'fetched_at' => now(),
                'is_active' => true,
                'ai_metadata' => [
                    'source' => 'region_scan',
                    'region' => $region,
                    'confidence' => (float) ($row['confidence'] ?? 0.7),
                    'grounded' => $grounded,
                ],
            ];

            $existing = HardwarePrice::query()->ownedBy($organisationId)
                ->where('price_type', $priceType)
                ->where('item_name', $attributes['item_name'])
                ->where('category', $category)
                ->where('supplier', $supplierName)
                ->where('location', $attributes['location'])
                ->first();

            if ($existing) {
                $existing->update($attributes);
                $status = 'updated';
            } else {
                HardwarePrice::create($attributes + ['organisation_id' => $organisationId]);
                $status = 'created';
            }

            $results[] = [
                'status' => $status,
                'item' => $attributes['item_name'],
                'price_type' => $priceType,
                'price' => (float) $attributes['price'],
                'currency' => $attributes['currency'],
                'supplier' => $supplierName,
                'location' => $attributes['location'],
            ];
        }

        return $results;
    }
}
