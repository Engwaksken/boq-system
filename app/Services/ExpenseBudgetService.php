<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Project;

class ExpenseBudgetService
{
    /** Compare recorded spending plus a draft against explicitly selected BOQ lines. */
    public function compare(Project $project, array $lines, string $currency, ?int $excludeExpenseId = null): array
    {
        $items = BoqItem::with('boq')->whereHas('boq', fn ($query) => $query
            ->where('project_id', $project->id)->where('organisation_id', $project->organisation_id))
            ->whereIn('id', array_filter(array_column($lines, 'boq_item_id')))->get()->keyBy('id');
        $spending = ExpenseItem::query()->whereHas('expense', fn ($query) => $query
            ->where('project_id', $project->id)->where('organisation_id', $project->organisation_id)
            ->where('currency', $currency)->when($excludeExpenseId, fn ($q) => $q->where('id', '!=', $excludeExpenseId)))
            ->whereIn('boq_item_id', $items->keys())->selectRaw('boq_item_id, SUM(total) AS spent')
            ->groupBy('boq_item_id')->pluck('spent', 'boq_item_id');
        // Older expenses may not have child items. Do not count modern records twice.
        $legacy = Expense::where('project_id', $project->id)->where('organisation_id', $project->organisation_id)
            ->where('currency', $currency)->whereDoesntHave('items')
            ->when($excludeExpenseId, fn ($q) => $q->where('id', '!=', $excludeExpenseId))
            ->whereIn('boq_item_id', $items->keys())->selectRaw('boq_item_id, SUM(total) AS spent')
            ->groupBy('boq_item_id')->pluck('spent', 'boq_item_id');
        $draftTotals = [];
        foreach ($lines as $line) {
            $id = $line['boq_item_id'] ?? null;
            $draftTotals[$id] = ($draftTotals[$id] ?? 0) + round((float) ($line['quantity'] ?? 0) * (float) ($line['rate'] ?? 0), 2);
        }

        return array_map(function ($line) use ($items, $spending, $legacy, $draftTotals, $currency): array {
            $item = $items->get($line['boq_item_id'] ?? null);
            if (! $item) {
                return ['status' => 'unlinked'];
            }
            $itemCurrency = $item->currency ?: $item->boq->currency;
            if (strtoupper($itemCurrency) !== strtoupper($currency)) {
                return ['status' => 'currency_mismatch', 'description' => $item->description, 'currency' => $itemCurrency];
            }
            $rate = $item->approved_rate ?? $item->reviewed_rate ?? $item->original_rate;
            if ($rate === null) {
                return ['status' => 'unpriced', 'description' => $item->description];
            }
            $budget = round((float) $item->quantity * (float) $rate, 2);
            $spent = round((float) ($spending[$item->id] ?? 0) + (float) ($legacy[$item->id] ?? 0), 2);
            $projected = round($spent + $draftTotals[$item->id], 2);

            return [
                'status' => $projected > $budget ? 'over_budget' : 'within_budget',
                'description' => $item->description, 'currency' => $itemCurrency,
                'basis' => $item->approved_rate !== null ? 'Approved' : ($item->reviewed_rate !== null ? 'Reviewed' : 'Original estimate'),
                'budget' => $budget, 'spent' => $spent, 'projected' => $projected,
                'remaining' => round($budget - $projected, 2),
                'progress' => $budget > 0 ? round($projected / $budget * 100, 1) : null,
                'unit_mismatch' => mb_strtolower(trim($line['unit'] ?? '')) !== mb_strtolower(trim($item->unit)),
                'rate_over_budget' => (float) ($line['rate'] ?? 0) > (float) $rate,
            ];
        }, $lines);
    }
}
