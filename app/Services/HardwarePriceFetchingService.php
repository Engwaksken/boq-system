<?php

namespace App\Services;

use App\Models\HardwarePrice;
use App\Models\PriceHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class HardwarePriceFetchingService
{
    private const UGANDA_HARDWARE_CATEGORIES = [
        'Cement' => ['Portland Cement', 'Waterproof Cement', 'White Cement'],
        'Steel' => ['TMT Bars', 'Binding Wire', 'Steel Mesh', 'Steel Plates'],
        'Aggregates' => ['Sand', 'Gravel', 'Crushed Stone', 'Murram'],
        'Bricks & Blocks' => ['Clay Bricks', 'Concrete Blocks', 'Interlocking Blocks'],
        'Roofing' => ['Iron Sheets', 'Roofing Tiles', 'Ridges', 'Valleys', 'Gutters'],
        'Paint' => ['Emulsion Paint', 'Oil Paint', 'Weather Guard', 'Primer', 'Thinner'],
        'Plumbing' => ['PVC Pipes', 'HDPE Pipes', 'Fittings', 'Valves', 'Taps', 'Water Tanks'],
        'Electrical' => ['Cables', 'Conduits', 'Switches', 'Sockets', 'DB Boxes', 'Bulbs'],
        'Timber' => ['Treated Timber', 'Plywood', 'MDF', 'Blockboard', 'Cypress', 'Pine'],
        'Tiles' => ['Ceramic Tiles', 'Porcelain Tiles', 'Floor Tiles', 'Wall Tiles'],
        'Adhesives' => ['Tile Adhesive', 'Grout', 'Silicone', 'Construction Adhesive'],
        'Tools' => ['Cement Mixers', 'Vibrators', 'Trowels', 'Levels', 'Measuring Tools'],
        'Safety' => ['Helmets', 'Boots', 'Gloves', 'Reflective Vests', 'Safety Nets'],
    ];

    private const UGANDA_SUPPLIERS = [
        'Hardware World Uganda',
        'Kampala Hardware',
        'Uganda Building Supplies',
        'Mukwano Hardware',
        'Steel & Tube Industries',
        'Hima Cement',
        'Tororo Cement',
        'Rwenzori Cement',
        'National Cement',
        'Afrisam Cement',
        'Simba Cement',
        'Crown Paints',
        'Sadolin Paints',
        'Kansai Plascon',
        'Berger Paints',
        'Bamburi Cement',
        'Savannah Hardware',
        'Quality Hardware',
        'Prime Hardware',
        'Buildmart Uganda',
    ];

    private const UGANDA_LOCATIONS = [
        'Kampala', 'Wakiso', 'Mukono', 'Entebbe', 'Jinja', 'Mbale', 'Mbarara',
        'Gulu', 'Arua', 'Fort Portal', 'Masaka', 'Lira', 'Soroti', 'Moroto',
        'Kabale', 'Kasese', 'Hoima', 'Masindi', 'Kiboga', 'Luweero',
    ];

    public function fetchDailyPrices(): array
    {
        $results = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        foreach (self::UGANDA_HARDWARE_CATEGORIES as $category => $items) {
            foreach ($items as $itemName) {
                foreach (self::UGANDA_SUPPLIERS as $supplier) {
                    $location = self::UGANDA_LOCATIONS[array_rand(self::UGANDA_LOCATIONS)];
                    
                    try {
                        $priceData = $this->fetchPriceForItem($itemName, $category, $supplier, $location);
                        
                        if ($priceData) {
                            $result = $this->storePrice($priceData);
                            $results['fetched']++;
                            if ($result === 'created') {
                                $results['created']++;
                            } else {
                                $results['updated']++;
                            }
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "{$itemName} - {$supplier}: {$e->getMessage()}";
                        Log::error("Failed to fetch price for {$itemName} from {$supplier}: {$e->getMessage()}");
                    }
                }
            }
        }

        return $results;
    }

    private function fetchPriceForItem(string $itemName, string $category, string $supplier, string $location): ?array
    {
        $prompt = $this->buildPricePrompt($itemName, $category, $supplier, $location);
        
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-goog-api-key' => (string) config('services.gemini.key'),
                    'Content-Type' => 'application/json',
                ])
                ->post(rtrim((string) config('services.gemini.base_url'), '/') . '/v1beta/models/' . urlencode((string) config('services.gemini.model')) . ':generateContent', [
                    'contents' => [[
                        'parts' => [['text' => $prompt]]
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 1024,
                    ],
                ]);

            if (! $response->successful()) {
                $message = $response->json('error.message') ?: 'Gemini price request failed.';
                throw new \RuntimeException($message);
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            return $this->parsePriceResponse($text, $itemName, $category, $supplier, $location);
        } catch (\Throwable $e) {
            Log::warning('AI price fetch failed.', [
                'item' => $itemName,
                'category' => $category,
                'supplier' => $supplier,
                'location' => $location,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function buildPricePrompt(string $itemName, string $category, string $supplier, string $location): string
    {
        return <<<PROMPT
You are a construction material price researcher for Uganda market. Provide current market price for:

Item: {$itemName}
Category: {$category}
Supplier: {$supplier}
Location: {$location}, Uganda
Currency: UGX (Uganda Shillings)

Return ONLY a valid JSON object with these exact fields:
{
  "item_name": "exact item name",
  "brand": "brand/manufacturer or null",
  "category": "category",
  "specification": "specification/grade/size",
  "unit": "unit of measure (bag, ton, piece, meter, litre, kg, roll, sheet, box)",
  "price": 123456.78,
  "currency": "UGX",
  "supplier": "supplier name",
  "location": "location in Uganda",
  "source_reference": "market survey / supplier website / price list date",
  "confidence": 0.85,
  "notes": "any relevant notes about price conditions"
}

Use realistic current Uganda market prices. Price should be numeric only.
PROMPT;
    }

    private function parsePriceResponse(string $text, string $itemName, string $category, string $supplier, string $location): ?array
    {
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');
        
        if ($jsonStart === false || $jsonEnd === false) {
            return null;
        }

        $json = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($json, true);

        if (!$data || !isset($data['price'])) {
            return null;
        }

        return [
            'item_name' => $data['item_name'] ?? $itemName,
            'brand' => $data['brand'] ?? null,
            'category' => $data['category'] ?? $category,
            'specification' => $data['specification'] ?? null,
            'unit' => $data['unit'] ?? 'piece',
            'price' => (float) $data['price'],
            'currency' => $data['currency'] ?? 'UGX',
            'supplier' => $data['supplier'] ?? $supplier,
            'location' => $data['location'] ?? $location,
            'source_url' => null,
            'source_reference' => $data['source_reference'] ?? 'AI market survey',
            'fetched_at' => now(),
            'ai_metadata' => [
                'confidence' => $data['confidence'] ?? 0.7,
                'notes' => $data['notes'] ?? null,
                'source' => 'gemini_ai',
            ],
        ];
    }

    private function storePrice(array $priceData): string
    {
        $existing = HardwarePrice::where('organisation_id', auth()->id() ? auth()->user()->organisation_id : 1)
            ->where('item_name', $priceData['item_name'])
            ->where('brand', $priceData['brand'])
            ->where('category', $priceData['category'])
            ->where('supplier', $priceData['supplier'])
            ->where('location', $priceData['location'])
            ->first();

        $orgId = auth()->id() ? auth()->user()->organisation_id : 1;

        if ($existing) {
            if ($existing->price != $priceData['price']) {
                PriceHistory::create([
                    'organisation_id' => $orgId,
                    'hardware_price_id' => $existing->id,
                    'price' => $existing->price,
                    'currency' => $existing->currency,
                    'supplier' => $existing->supplier,
                    'location' => $existing->location,
                    'source_url' => $existing->source_url,
                    'recorded_at' => $existing->fetched_at,
                ]);

                $existing->update(array_merge($priceData, ['organisation_id' => $orgId]));
            } else {
                $existing->update(['fetched_at' => $priceData['fetched_at'], 'ai_metadata' => $priceData['ai_metadata']]);
            }
            return 'updated';
        } else {
            HardwarePrice::create(array_merge($priceData, ['organisation_id' => $orgId]));
            return 'created';
        }
    }

    public function getCategories(): array
    {
        return array_keys(self::UGANDA_HARDWARE_CATEGORIES);
    }

    public function getItemsByCategory(string $category): array
    {
        return self::UGANDA_HARDWARE_CATEGORIES[$category] ?? [];
    }

    public function getSuppliers(): array
    {
        return self::UGANDA_SUPPLIERS;
    }

    public function getLocations(): array
    {
        return self::UGANDA_LOCATIONS;
    }
}