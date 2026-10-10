<?php

namespace App\Services;

use App\Models\Boq;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Support\Regional;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BOQ-centric accounting: each approved BOQ is the budget unit. Budget is the
 * sum of its items at the best available rate (approved, then reviewed, then
 * original); expenditure is the amount recorded against that BOQ, attributed
 * through the expense lines. Expenses not linked to an approved BOQ are
 * reported separately and are not charged to any BOQ balance. Amounts in a
 * currency other than the project's are reported separately, never mixed in.
 */
class ProjectAccountingService
{
    /** One row per assigned project, rolled up from its approved BOQs. */
    public function portfolio(User $user): Collection
    {
        return Project::where('organisation_id', $user->organisation_id)
            ->whereHas('assignments', fn ($query) => $query->where('user_id', $user->id)->whereNull('deleted_at'))
            ->orderBy('name')->get()
            ->map(fn (Project $project) => $this->projectTotals($project));
    }

    /** One row for a single project, rolled up from its approved BOQs. */
    public function projectTotals(Project $project, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return array_merge([
            'id' => $project->id, 'name' => $project->name, 'code' => $project->code, 'status' => $project->status,
        ], $this->totals($project, $from, $to));
    }

    /** Per-approved-BOQ breakdown plus project totals for the project's currency. */
    public function breakdown(Project $project, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $currency = strtoupper((string) ($project->currency ?: Regional::currency()));

        $boqs = Boq::where('project_id', $project->id)
            ->where('organisation_id', $project->organisation_id)
            ->with(['items' => fn ($query) => $query->select('id', 'boq_id', 'quantity', 'approved_rate', 'reviewed_rate', 'original_rate', 'currency')])
            ->orderBy('name')->get();

        [$itemTotals, $legacyTotals] = $this->spendMaps($project, $currency, $from, $to);

        $rows = $boqs->where('status', 'approved')->values()->map(function (Boq $boq) use ($itemTotals, $legacyTotals, $currency) {
            $budget = round($boq->items->sum(fn ($item) => (float) $item->quantity
                * (float) ($item->approved_rate ?? $item->reviewed_rate ?? $item->original_rate ?? 0)), 2);
            $spent = round($this->linkedSpend($boq->id, $itemTotals, $legacyTotals), 2);

            return [
                'id' => $boq->id, 'name' => $boq->name, 'code' => $boq->code, 'status' => $boq->status,
                'currency' => strtoupper((string) ($boq->currency ?: $currency)),
                'items_count' => $boq->items->count(),
                'budget' => $budget, 'spent' => $spent, 'balance' => round($budget - $spent, 2),
                'progress' => $budget > 0 ? round($spent / $budget * 100, 1) : null,
                'over_budget' => $spent > $budget,
            ];
        });

        $budget = round((float) $rows->sum('budget'), 2);
        $spent = round((float) $rows->sum('spent'), 2);
        $totalSpend = round(array_sum($itemTotals) + array_sum($legacyTotals), 2);
        $unlinked = round($totalSpend - $spent, 2);

        return [
            'currency' => $currency, 'rows' => $rows,
            'excluded_count' => $boqs->where('status', '!=', 'approved')->count(),
            'unlinked_spent' => $unlinked,
            'other_currency' => $this->otherCurrencySpend($project, $currency, $from, $to),
            'budget' => $budget, 'spent' => $spent, 'balance' => round($budget - $spent, 2),
            'progress' => $budget > 0 ? round($spent / $budget * 100, 1) : null,
        ];
    }

    /** Approved-BOQ budget and linked expenditure for one project. */
    private function totals(Project $project, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $currency = strtoupper((string) ($project->currency ?: Regional::currency()));
        [$itemTotals, $legacyTotals] = $this->spendMaps($project, $currency, $from, $to);

        $budget = $this->approvedBudget($project, $currency);
        $spent = 0.0;
        foreach (Boq::where('project_id', $project->id)->where('organisation_id', $project->organisation_id)
            ->where('status', 'approved')->pluck('id') as $id) {
            $spent += $this->linkedSpend($id, $itemTotals, $legacyTotals);
        }
        $spent = round($spent, 2);
        $unlinked = round(array_sum($itemTotals) + array_sum($legacyTotals) - $spent, 2);

        return [
            'currency' => $currency, 'budget' => $budget, 'spent' => $spent,
            'unlinked' => $unlinked,
            'balance' => round($budget - $spent, 2),
            'progress' => $budget > 0 ? round($spent / $budget * 100, 1) : null,
        ];
    }

    /** Approved-BOQ budget in the project currency. */
    private function approvedBudget(Project $project, string $currency): float
    {
        return round((float) DB::table('boq_items')
            ->join('boqs', 'boqs.id', '=', 'boq_items.boq_id')
            ->where('boqs.project_id', $project->id)
            ->where('boqs.organisation_id', $project->organisation_id)
            ->where('boqs.status', 'approved')
            ->whereNull('boqs.deleted_at')
            ->whereRaw('UPPER(COALESCE(boq_items.currency, boqs.currency)) = ?', [$currency])
            ->selectRaw('SUM(boq_items.quantity * COALESCE(boq_items.approved_rate, boq_items.reviewed_rate, boq_items.original_rate, 0)) AS total')
            ->value('total'), 2);
    }

    /**
     * Expenditure in the project currency keyed by BOQ id ('' for unlinked).
     * Modern expenses use their item lines; older expenses without items use
     * their own BOQ link and total.
     *
     * @return array{0: array<string, float>, 1: array<string, float>}
     */
    private function spendMaps(Project $project, string $currency, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $itemTotals = [];
        foreach (DB::table('expense_items')
            ->join('expenses', 'expenses.id', '=', 'expense_items.expense_id')
            ->where('expenses.project_id', $project->id)
            ->where('expenses.organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(expenses.currency) = ?', [$currency])
            ->when($from, fn ($query) => $query->where('expenses.purchase_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->where('expenses.purchase_date', '<=', $to->toDateString()))
            ->groupBy('expense_items.boq_id')
            ->selectRaw('expense_items.boq_id AS boq_id, SUM(expense_items.total) AS spent')->get() as $row) {
            $itemTotals[(string) ($row->boq_id ?? '')] = (float) $row->spent;
        }

        $legacyTotals = [];
        foreach (Expense::where('project_id', $project->id)
            ->where('organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->when($from, fn ($query) => $query->where('purchase_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->where('purchase_date', '<=', $to->toDateString()))
            ->whereDoesntHave('items')
            ->groupBy('boq_id')->selectRaw('boq_id, SUM(total) AS spent')->get() as $row) {
            $legacyTotals[(string) ($row->boq_id ?? '')] = (float) $row->spent;
        }

        return [$itemTotals, $legacyTotals];
    }

    private function linkedSpend(int $boqId, array $itemTotals, array $legacyTotals): float
    {
        return ($itemTotals[(string) $boqId] ?? 0) + ($legacyTotals[(string) $boqId] ?? 0);
    }

    private function otherCurrencySpend(Project $project, string $currency, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return DB::table('expenses')
            ->where('project_id', $project->id)->where('organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(currency) != ?', [$currency])
            ->when($from, fn ($query) => $query->where('purchase_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->where('purchase_date', '<=', $to->toDateString()))
            ->groupBy('currency')->selectRaw('currency, SUM(total) AS spent')
            ->pluck('spent', 'currency');
    }
}
