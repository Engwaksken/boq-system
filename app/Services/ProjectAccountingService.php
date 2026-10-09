<?php

namespace App\Services;

use App\Models\Boq;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Support\Regional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads the BOQ budget and recorded expenditure of a project so the balance can
 * be tracked. Budget uses each item's best available rate (approved, then
 * reviewed, then original). Amounts in a currency other than the project's are
 * reported separately rather than mixed into the totals.
 */
class ProjectAccountingService
{
    /** One row per assigned project: budget, expenditure and balance. */
    public function portfolio(User $user): Collection
    {
        return Project::where('organisation_id', $user->organisation_id)
            ->whereHas('assignments', fn ($query) => $query->where('user_id', $user->id)->whereNull('deleted_at'))
            ->orderBy('name')->get()
            ->map(fn (Project $project) => array_merge([
                'id' => $project->id, 'name' => $project->name, 'code' => $project->code, 'status' => $project->status,
            ], $this->totals($project)));
    }

    /** Per-BOQ breakdown plus project totals for the project's currency. */
    public function breakdown(Project $project): array
    {
        $currency = strtoupper((string) ($project->currency ?: Regional::currency()));

        $boqs = Boq::where('project_id', $project->id)
            ->where('organisation_id', $project->organisation_id)
            ->with(['items' => fn ($query) => $query->select('id', 'boq_id', 'quantity', 'approved_rate', 'reviewed_rate', 'original_rate', 'currency')])
            ->orderBy('name')->get();

        $itemSpend = DB::table('expense_items')
            ->join('expenses', 'expenses.id', '=', 'expense_items.expense_id')
            ->where('expenses.project_id', $project->id)
            ->where('expenses.organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(expenses.currency) = ?', [$currency])
            ->whereNotNull('expense_items.boq_id')
            ->groupBy('expense_items.boq_id')
            ->selectRaw('expense_items.boq_id, SUM(expense_items.total) AS spent')
            ->pluck('spent', 'expense_items.boq_id');

        $legacySpend = Expense::where('project_id', $project->id)
            ->where('organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->whereDoesntHave('items')->whereNotNull('boq_id')
            ->groupBy('boq_id')->selectRaw('boq_id, SUM(total) AS spent')
            ->pluck('spent', 'boq_id');

        $rows = $boqs->map(function (Boq $boq) use ($itemSpend, $legacySpend, $currency) {
            $budget = round($boq->items->sum(fn ($item) => (float) $item->quantity
                * (float) ($item->approved_rate ?? $item->reviewed_rate ?? $item->original_rate ?? 0)), 2);
            $spent = round((float) ($itemSpend[$boq->id] ?? 0) + (float) ($legacySpend[$boq->id] ?? 0), 2);

            return [
                'id' => $boq->id, 'name' => $boq->name, 'code' => $boq->code, 'status' => $boq->status,
                'currency' => strtoupper((string) ($boq->currency ?: $currency)),
                'items_count' => $boq->items->count(),
                'budget' => $budget, 'spent' => $spent, 'balance' => round($budget - $spent, 2),
                'progress' => $budget > 0 ? round($spent / $budget * 100, 1) : null,
                'over_budget' => $spent > $budget,
            ];
        });

        $unlinked = round((float) Expense::where('project_id', $project->id)
            ->where('organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->whereNull('boq_id')->sum('total'), 2);

        $otherCurrency = DB::table('expenses')
            ->where('project_id', $project->id)->where('organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(currency) != ?', [$currency])
            ->groupBy('currency')->selectRaw('currency, SUM(total) AS spent')
            ->pluck('spent', 'currency');

        $budget = round((float) $rows->sum('budget'), 2);
        $spent = round((float) $rows->sum('spent') + $unlinked, 2);

        return [
            'currency' => $currency, 'rows' => $rows, 'unlinked_spent' => $unlinked,
            'other_currency' => $otherCurrency,
            'budget' => $budget, 'spent' => $spent, 'balance' => round($budget - $spent, 2),
            'progress' => $budget > 0 ? round($spent / $budget * 100, 1) : null,
        ];
    }

    /** Budget and expenditure for one project in its currency. */
    private function totals(Project $project): array
    {
        $currency = strtoupper((string) ($project->currency ?: Regional::currency()));

        $budget = DB::table('boq_items')
            ->join('boqs', 'boqs.id', '=', 'boq_items.boq_id')
            ->where('boqs.project_id', $project->id)
            ->where('boqs.organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(COALESCE(boq_items.currency, boqs.currency)) = ?', [$currency])
            ->selectRaw('SUM(boq_items.quantity * COALESCE(boq_items.approved_rate, boq_items.reviewed_rate, boq_items.original_rate, 0)) AS total')
            ->value('total');

        $spent = DB::table('expenses')
            ->where('project_id', $project->id)->where('organisation_id', $project->organisation_id)
            ->whereRaw('UPPER(currency) = ?', [$currency])->sum('total');

        $budget = round((float) $budget, 2);
        $spent = round((float) $spent, 2);

        return [
            'currency' => $currency, 'budget' => $budget, 'spent' => $spent,
            'balance' => round($budget - $spent, 2),
            'progress' => $budget > 0 ? round($spent / $budget * 100, 1) : null,
        ];
    }
}
