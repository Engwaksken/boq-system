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
                    'Authorization' => 'Bearer ' . config('services.gemini.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent', [
                    'contents' => [[
                        'parts' => [['text' => $prompt]]
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 1024,
                    ],
                ]);

            if (!$response->successful()) {
                return $this->generateMockPrice($itemName, $category, $supplier, $location);
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            return $this->parsePriceResponse($text, $itemName, $category, $supplier, $location);
        } catch (\Exception $e) {
            Log::warning("AI price fetch failed, using mock data: {$e->getMessage()}");
            return $this->generateMockPrice($itemName, $category, $supplier, $location);
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

    private function generateMockPrice(string $itemName, string $category, string $supplier, string $location): array
    {
        $basePrices = [
            'Cement' => ['Portland Cement' => 35000, 'Waterproof Cement' => 42000, 'White Cement' => 55000],
            'Steel' => ['TMT Bars' => 3800000, 'Binding Wire' => 85000, 'Steel Mesh' => 45000, 'Steel Plates' => 280000],
            'Aggregates' => ['Sand' => 120000, 'Gravel' => 150000, 'Crushed Stone' => 180000, 'Murram' => 80000],
            'Bricks & Blocks' => ['Clay Bricks' => 800, 'Concrete Blocks' => 2500, 'Interlocking Blocks' => 3200],
            'Roofing' => ['Iron Sheets' => 45000, 'Roofing Tiles' => 85000, 'Ridges' => 15000, 'Valleys' => 18000, 'Gutters' => 25000],
            'Paint' => ['Emulsion Paint' => 85000, 'Oil Paint' => 95000, 'Weather Guard' => 120000, 'Primer' => 45000, 'Thinner' => 25000],
            'Plumbing' => ['PVC Pipes' => 35000, 'HDPE Pipes' => 55000, 'Fittings' => 8000, 'Valves' => 25000, 'Taps' => 35000, 'Water Tanks' => 450000],
            'Electrical' => ['Cables' => 180000, 'Conduits' => 12000, 'Switches' => 8000, 'Sockets' => 10000, 'DB Boxes' => 45000, 'Bulbs' => 12000],
            'Timber' => ['Treated Timber' => 4500, 'Plywood' => 85000, 'MDF' => 65000, 'Blockboard' => 75000, 'Cypress' => 5500, 'Pine' => 4000],
            'Tiles' => ['Ceramic Tiles' => 45000, 'Porcelain Tiles' => 65000, 'Floor Tiles' => 55000, 'Wall Tiles' => 48000],
            'Adhesives' => ['Tile Adhesive' => 35000, 'Grout' => 18000, 'Silicone' => 25000, 'Construction Adhesive' => 15000],
            'Tools' => ['Cement Mixers' => 1200000, 'Vibrators' => 850000, 'Trowels' => 15000, 'Levels' => 25000, 'Measuring Tools' => 35000],
            'Safety' => ['Helmets' => 25000, 'Boots' => 45000, 'Gloves' => 8000, 'Reflective Vests' => 15000, 'Safety Nets' => 85000],
        ];

        $units = [
            'Cement' => 'bag',
            'Steel' => 'ton',
            'Aggregates' => 'ton',
            'Bricks & Blocks' => 'piece',
            'Roofing' => 'sheet',
            'Paint' => 'litre',
            'Plumbing' => 'piece',
            'Electrical' => 'meter',
            'Timber' => 'meter',
            'Tiles' => 'sqm',
            'Adhesives' => 'bag',
            'Tools' => 'piece',
            'Safety' => 'piece',
        ];

        $basePrice = $basePrices[$category][$itemName] ?? 50000;
        $variation = 0.85 + (mt_rand(0, 30) / 100);
        $price = round($basePrice * $variation, 2);

        return [
            'item_name' => $itemName,
            'brand' => null,
            'category' => $category,
            'specification' => 'Standard grade',
            'unit' => $units[$category] ?? 'piece',
            'price' => $price,
            'currency' => 'UGX',
            'supplier' => $supplier,
            'location' => $location,
            'source_url' => null,
            'source_reference' => 'Mock market data',
            'fetched_at' => now(),
            'ai_metadata' => [
                'confidence' => 0.6,
                'notes' => 'Generated mock price for development',
                'source' => 'mock_data',
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