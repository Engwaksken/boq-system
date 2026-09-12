<?php

namespace App\Services\Mcp;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\HardwarePrice;
use App\Models\PriceHistory;
use App\Models\Project;
use App\Models\User;
use App\Services\PriceMatchingService;
use Illuminate\Support\Collection;

class McpService
{
    public function __construct(private PriceMatchingService $matching) {}

    public function execute(string $tool, array $parameters, User $user): array
    {
        return match ($tool) {
            'list_boq_projects' => $this->projects($parameters, $user),
            'get_boq_project' => $this->project($this->projectFor($parameters, $user)),
            'get_boq_items' => $this->boqItems($parameters, $user),
            'search_boq_items' => $this->searchBoqItems($parameters, $user),
            'calculate_boq_total', 'estimate_boq_cost', 'recalculate_boq' => $this->boqTotal($parameters, $user),
            'analyse_boq' => $this->analyseBoq($parameters, $user),
            'search_materials', 'search_hardware_prices' => $this->prices($parameters, $user),
            'get_material', 'get_material_current_price' => $this->material($parameters, $user),
            'get_material_price_history', 'get_hardware_price_history' => $this->history($parameters, $user),
            'compare_material_prices', 'compare_hardware_prices' => $this->comparePrices($parameters, $user),
            'get_best_hardware_suppliers', 'get_best_suppliers' => $this->bestSuppliers($parameters, $user),
            'get_recent_price_changes' => $this->recentChanges($parameters, $user),
            'search_suppliers' => $this->suppliers($parameters, $user),
            'get_supplier' => $this->supplier($parameters, $user),
            'compare_suppliers' => $this->compareSuppliers($parameters, $user),
            'get_supplier_materials' => $this->supplierMaterials($parameters, $user),
            'get_supplier_price_history' => $this->supplierHistory($parameters, $user),
            'estimate_material_cost' => $this->estimateMaterial($parameters, $user),
            'recommend_material_supplier', 'find_cheaper_alternatives' => $this->recommendSupplier($parameters, $user),
            'find_material_substitutes' => $this->substitutes($parameters, $user),
            'get_project_cost_summary', 'get_material_cost_report', 'get_price_variance_report', 'get_supplier_comparison_report', 'get_price_trend_report' => $this->report($tool, $parameters, $user),
            'get_imported_boq', 'get_boq_extraction_status', 'get_unmatched_boq_items' => $this->importStatus($tool, $parameters, $user),
            default => throw new McpException('TOOL_NOT_FOUND', 'The requested MCP tool is not available.', 404),
        };
    }

    private function projects(array $parameters, User $user): array
    {
        $perPage = $this->perPage($parameters);
        $query = Project::where('organisation_id', $user->organisation_id)->withCount('boqs')->latest();
        if (!empty($parameters['search'])) $query->where('name', 'like', '%'.$parameters['search'].'%');
        return $this->paginated($query->paginate($perPage), fn (Project $p) => $this->projectSummary($p));
    }

    private function project(Project $project): array
    {
        $project->load(['boqs' => fn ($q) => $q->with('items')->latest()]);
        return [
            'project' => $this->projectSummary($project),
            'client' => $project->client,
            'currency' => $project->currency ?: 'UGX',
            'boqs' => $project->boqs->map(fn (Boq $boq) => [
                'id' => $boq->id, 'name' => $boq->name, 'status' => $boq->status,
                'currency' => $boq->currency, 'items' => $boq->items->map(fn (BoqItem $item) => $this->item($item)),
                'total' => round($boq->items->sum(fn (BoqItem $i) => (float) $i->quantity * (float) ($i->approved_rate ?? $i->original_rate ?? 0)), 2),
            ]),
        ];
    }

    private function boqItems(array $parameters, User $user): array
    {
        $boq = $this->boqFor($parameters, $user);
        return $this->paginated($boq->items()->orderBy('id')->paginate($this->perPage($parameters)), fn (BoqItem $i) => $this->item($i));
    }

    private function searchBoqItems(array $parameters, User $user): array
    {
        $term = trim((string) ($parameters['query'] ?? ''));
        if ($term === '') throw new McpException('VALIDATION_ERROR', 'A BOQ item search query is required.', 422);
        $query = BoqItem::whereHas('boq.project', fn ($q) => $q->where('organisation_id', $user->organisation_id))
            ->where(fn ($q) => $q->where('description', 'like', "%{$term}%")->orWhere('item_code', 'like', "%{$term}%"));
        return $this->paginated($query->paginate($this->perPage($parameters)), fn (BoqItem $i) => $this->item($i));
    }

    private function boqTotal(array $parameters, User $user): array
    {
        $boq = $this->boqFor($parameters, $user);
        $items = $boq->items()->get();
        $total = 0.0; $missing = [];
        foreach ($items as $item) {
            $rate = $item->approved_rate ?? $item->original_rate;
            if ($rate === null) { $missing[] = $item->id; continue; }
            $total += (float) $item->quantity * (float) $rate;
        }
        return ['boq_id' => $boq->id, 'currency' => $boq->currency ?: 'UGX', 'total' => round($total, 2), 'formula' => 'quantity x approved_rate (or original_rate)', 'missing_price_item_ids' => $missing];
    }

    private function analyseBoq(array $parameters, User $user): array
    {
        $boq = $this->boqFor($parameters, $user); $boq->load('project', 'items');
        $current = 0.0; $uploaded = 0.0; $missing = []; $increases = []; $reductions = []; $outdated = []; $recommendations = [];
        foreach ($boq->items as $item) {
            $uploaded += (float) $item->quantity * (float) ($item->approved_rate ?? $item->original_rate ?? 0);
            $matches = $this->matching->findMatches($item, 1);
            if ($matches->isEmpty()) { $missing[] = ['item_id' => $item->id, 'description' => $item->description]; continue; }
            $price = $matches->first()['hardware_price']; $rate = (float) $price->price;
            // Only compare like currencies; this application has no approved exchange-rate service yet.
            if ($price->currency !== ($boq->currency ?: 'UGX')) { $missing[] = ['item_id' => $item->id, 'description' => $item->description, 'reason' => 'Currency conversion unavailable']; continue; }
            $current += (float) $item->quantity * $rate;
            $oldRate = (float) ($item->approved_rate ?? $item->original_rate ?? 0);
            $variance = $oldRate > 0 ? round((($rate - $oldRate) / $oldRate) * 100, 2) : null;
            $entry = ['item_id' => $item->id, 'description' => $item->description, 'variance_percentage' => $variance, 'price_date' => $price->fetched_at->toDateString()];
            if ($variance !== null && $variance >= 10) $increases[] = $entry;
            if ($variance !== null && $variance <= -10) $reductions[] = $entry;
            if ($item->pricing_date?->lt(now()->subDays(30))) $outdated[] = $entry;
            $recommendations[] = ['item_id' => $item->id, 'supplier' => $price->supplier, 'price' => $price->price, 'currency' => $price->currency, 'source_date' => $price->fetched_at->toDateString()];
        }
        $variance = $current - $uploaded;
        return ['boq_id' => $boq->id, 'currency' => $boq->currency ?: 'UGX', 'current_estimated_cost' => round($current, 2), 'uploaded_estimated_cost' => round($uploaded, 2), 'variance' => round($variance, 2), 'variance_percentage' => $uploaded > 0 ? round(($variance / $uploaded) * 100, 2) : null, 'major_price_increases' => $increases, 'major_price_reductions' => $reductions, 'potentially_outdated_rates' => $outdated, 'missing_prices' => $missing, 'recommended_suppliers' => $recommendations, 'confidence_level' => $missing === [] ? 'medium' : 'low'];
    }

    private function prices(array $parameters, User $user): array
    {
        $query = $this->priceQuery($parameters, $user);
        return $this->paginated($query->paginate($this->perPage($parameters)), fn (HardwarePrice $price) => $this->price($price));
    }

    private function material(array $parameters, User $user): array
    {
        $price = $this->priceFor($parameters, $user);
        return ['material' => $this->price($price), 'price_status' => 'available'];
    }

    private function history(array $parameters, User $user): array
    {
        $price = $this->priceFor($parameters, $user);
        $history = $price->priceHistories()->orderByDesc('recorded_at')->paginate($this->perPage($parameters));
        return ['material' => $this->price($price), 'history' => $this->paginated($history, fn (PriceHistory $h) => $this->historyRecord($h))];
    }

    private function comparePrices(array $parameters, User $user): array
    {
        $prices = $this->priceQuery($parameters, $user)->get();
        if ($prices->isEmpty()) return ['price_status' => 'unavailable', 'message' => 'Current pricing information is not available for this material.'];
        if (!empty($parameters['currency'])) return ['currency' => $parameters['currency'], 'items' => $prices->sortBy('price')->map(fn (HardwarePrice $p) => $this->price($p))->values()];
        return ['currency_status' => $prices->pluck('currency')->unique()->count() > 1 ? 'separate_currency_groups' : 'single_currency', 'price_groups' => $prices->groupBy('currency')->map(fn ($group, $currency) => ['currency' => $currency, 'items' => $group->sortBy('price')->map(fn (HardwarePrice $p) => $this->price($p))->values()])->values()];
    }

    private function bestSuppliers(array $parameters, User $user): array { return ['suppliers' => $this->rankSuppliers($this->priceQuery($parameters, $user)->get())]; }
    private function suppliers(array $parameters, User $user): array { return ['suppliers' => $this->rankSuppliers($this->priceQuery($parameters, $user)->get(), (string) ($parameters['query'] ?? ''))]; }
    private function supplier(array $parameters, User $user): array { $name = $this->supplierName($parameters); $prices = $this->priceQuery(['supplier' => $name], $user)->get(); if ($prices->isEmpty()) throw new McpException('SUPPLIER_NOT_FOUND', 'Supplier not found.', 404); return ['supplier' => $this->rankSuppliers($prices)->first(), 'materials' => $prices->map(fn ($p) => $this->price($p))->values()]; }
    private function compareSuppliers(array $parameters, User $user): array { return ['suppliers' => $this->rankSuppliers($this->priceQuery($parameters, $user)->get())]; }
    private function supplierMaterials(array $parameters, User $user): array { return $this->prices(['supplier' => $this->supplierName($parameters)] + $parameters, $user); }
    private function supplierHistory(array $parameters, User $user): array { $name = $this->supplierName($parameters); $history = PriceHistory::where('organisation_id', $user->organisation_id)->where('supplier', $name)->latest('recorded_at')->paginate($this->perPage($parameters)); return $this->paginated($history, fn (PriceHistory $h) => $this->historyRecord($h)); }
    private function recentChanges(array $parameters, User $user): array { $days = min(max((int) ($parameters['days'] ?? 30), 1), 365); return ['changes' => $this->priceQuery($parameters, $user)->get()->map(fn ($p) => $this->price($p))->filter(fn ($p) => abs($p['price_change_percentage'] ?? 0) >= (float) ($parameters['minimum_change'] ?? 10))->values(), 'period_days' => $days]; }
    private function estimateMaterial(array $parameters, User $user): array { $price = $this->priceFor($parameters, $user); $quantity = (float) ($parameters['quantity'] ?? 0); if ($quantity <= 0) throw new McpException('VALIDATION_ERROR', 'A positive quantity is required.', 422); return ['material' => $this->price($price), 'quantity' => $quantity, 'total' => round($quantity * (float) $price->price, 2), 'currency' => $price->currency, 'formula' => 'quantity x current unit rate']; }
    private function recommendSupplier(array $parameters, User $user): array { $ranked = $this->rankSuppliers($this->priceQuery($parameters, $user)->get()); if ($ranked->isEmpty()) return ['price_status' => 'unavailable']; $best = $ranked->first(); return ['recommended_supplier' => $best, 'reasons' => $best['reasons']]; }
    private function substitutes(array $parameters, User $user): array { $category = $parameters['category'] ?? null; if (!$category) throw new McpException('VALIDATION_ERROR', 'A material category is required.', 422); return $this->prices(['category' => $category] + $parameters, $user); }

    private function report(string $tool, array $parameters, User $user): array
    {
        if ($tool === 'get_project_cost_summary') return $this->boqTotal($parameters, $user);
        if ($tool === 'get_supplier_comparison_report') return $this->compareSuppliers($parameters, $user);
        if ($tool === 'get_price_trend_report') return $this->recentChanges($parameters, $user);
        if ($tool === 'get_price_variance_report') return $this->analyseBoq($parameters, $user);
        return $this->boqItems($parameters, $user);
    }

    private function importStatus(string $tool, array $parameters, User $user): array
    {
        $boq = $this->boqFor($parameters, $user);
        if ($tool === 'get_unmatched_boq_items') return ['boq_id' => $boq->id, 'items' => $boq->items()->whereNull('approved_rate')->whereNull('original_rate')->paginate($this->perPage($parameters))];
        return ['boq_id' => $boq->id, 'source_type' => $boq->source_type, 'status' => $boq->status, 'extraction_status' => data_get($boq->metadata, 'extraction_status', $boq->status), 'source_document_exposed' => false];
    }

    private function projectFor(array $p, User $u): Project { return Project::where('organisation_id', $u->organisation_id)->findOr($p['project_id'] ?? 0, fn () => throw new McpException('PROJECT_NOT_FOUND', 'Project not found.', 404)); }
    private function boqFor(array $p, User $u): Boq { $id = $p['boq_id'] ?? null; if (!$id && !empty($p['project_id'])) $id = $this->projectFor($p, $u)->boqs()->latest()->value('id'); return Boq::where('organisation_id', $u->organisation_id)->findOr($id ?: 0, fn () => throw new McpException('BOQ_NOT_FOUND', 'BOQ not found.', 404)); }
    private function priceFor(array $p, User $u): HardwarePrice { $id = $p['material_id'] ?? $p['hardware_price_id'] ?? null; if ($id) return HardwarePrice::active()->where('organisation_id', $u->organisation_id)->findOr($id, fn () => throw new McpException('PRICE_NOT_AVAILABLE', 'Current pricing information is not available for this material.', 404)); $matches = $this->priceQuery($p, $u)->latest('fetched_at')->get(); if ($matches->isEmpty()) throw new McpException('PRICE_NOT_AVAILABLE', 'Current pricing information is not available for this material.', 404); return $matches->first(); }
    private function priceQuery(array $p, User $u) { $q = HardwarePrice::active()->where('organisation_id', $u->organisation_id); if ($term = $p['query'] ?? $p['material'] ?? null) $q->search($term); foreach (['category', 'supplier', 'location', 'currency'] as $field) if (!empty($p[$field])) $q->where($field, $p[$field]); return $q->latest('fetched_at'); }
    private function supplierName(array $p): string { $name = trim((string) ($p['supplier'] ?? '')); if ($name === '') throw new McpException('VALIDATION_ERROR', 'A supplier is required.', 422); return $name; }
    private function perPage(array $p): int { return min(max((int) ($p['per_page'] ?? 25), 1), 100); }
    private function paginated($page, callable $map): array { return ['items' => $page->getCollection()->map($map)->values(), 'pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]; }
    private function projectSummary(Project $p): array { return ['id' => $p->id, 'name' => $p->name, 'client' => $p->client, 'currency' => $p->currency ?: 'UGX', 'location' => $p->location, 'status' => $p->status]; }
    private function item(BoqItem $i): array { $rate = $i->approved_rate ?? $i->original_rate; return ['id' => $i->id, 'item_code' => $i->item_code, 'description' => $i->description, 'unit' => $i->unit, 'quantity' => $i->quantity, 'rate' => $rate, 'total' => $rate === null ? null : round((float) $i->quantity * (float) $rate, 2), 'currency' => $i->currency, 'status' => $i->status]; }
    private function price(HardwarePrice $p): array { return ['material_id' => $p->id, 'name' => $p->item_name, 'category' => $p->category, 'brand' => $p->brand, 'unit' => $p->unit, 'current_price' => $p->price, 'price' => $p->price, 'currency' => $p->currency, 'supplier' => $p->supplier, 'location' => $p->location, 'price_date' => $p->fetched_at->toDateString(), 'date_collected' => $p->fetched_at->toIso8601String(), 'price_change_percentage' => $p->price_change_percent, 'source' => $p->source_reference, 'source_url' => $p->source_url, 'confidence_score' => data_get($p->ai_metadata, 'confidence_score')]; }
    private function historyRecord(PriceHistory $h): array { return ['material_id' => $h->hardware_price_id, 'supplier' => $h->supplier, 'price' => $h->price, 'currency' => $h->currency, 'location' => $h->location, 'source_url' => $h->source_url, 'captured_at' => $h->recorded_at->toIso8601String(), 'verified_at' => data_get($h->metadata, 'verified_at'), 'confidence_score' => data_get($h->metadata, 'confidence_score')]; }
    private function rankSuppliers(Collection $prices, string $search = ''): Collection { return $prices->groupBy(fn (HardwarePrice $price) => $price->supplier.'|'.$price->currency)->filter(fn ($items) => $search === '' || str_contains(strtolower($items->first()->supplier), strtolower($search)))->map(function ($items) { $supplier = $items->first()->supplier; $freshness = max(0, 100 - $items->min(fn ($p) => $p->fetched_at->diffInDays(now())) * 3); $confidence = $items->avg(fn ($p) => data_get($p->ai_metadata, 'confidence_score', 50)); $avg = $items->avg('price'); $stability = 100 - min(100, $items->avg(fn ($p) => abs((float) ($p->price_change_percent ?? 0)))); $score = round($freshness * .3 + $confidence * .3 + $stability * .2 + 20); return ['supplier' => $supplier, 'materials_count' => $items->count(), 'average_price' => round($avg, 2), 'currency' => $items->first()->currency, 'ranking_score' => min(100, $score), 'availability' => 'not reported', 'reviews' => 'not available', 'reasons' => ['Price competitiveness is assessed per comparable material.', 'Freshness, recorded confidence, and historical price stability are included.', 'Availability and user reviews are not available in the current data.']]; })->sortByDesc('ranking_score')->values(); }
}
