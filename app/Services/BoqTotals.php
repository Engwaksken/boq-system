<?php

namespace App\Services;

use App\Models\Boq;
use Illuminate\Support\Facades\DB;

/**
 * Estimated and generated totals for BOQs and projects.
 *
 * - Estimated amount: quantity × the estimate rate that came with the file
 *   (original_rate, from the upload or an "estimated prices" file).
 * - Generated total: quantity × the priced rate (approved, else reviewed,
 *   else the AI suggestion).
 */
class BoqTotals
{
    private const PRICED_RATE = 'COALESCE(approved_rate, reviewed_rate, ai_suggested_rate)';

    /**
     * @param  array<int, int>  $boqIds
     * @return array<int, array{items: int, estimated_items: int, priced_items: int, estimated_amount: float, generated_total: float, difference: float}>
     */
    public function forBoqs(array $boqIds): array
    {
        if ($boqIds === []) {
            return [];
        }

        $rows = DB::table('boq_items')
            ->whereIn('boq_id', $boqIds)
            ->groupBy('boq_id')
            ->selectRaw('boq_id, COUNT(*) AS items')
            ->selectRaw('SUM(CASE WHEN original_rate IS NOT NULL THEN 1 ELSE 0 END) AS estimated_items')
            ->selectRaw('SUM(CASE WHEN '.self::PRICED_RATE.' IS NOT NULL THEN 1 ELSE 0 END) AS priced_items')
            ->selectRaw('SUM(quantity * COALESCE(original_rate, 0)) AS estimated_amount')
            ->selectRaw('SUM(quantity * COALESCE('.self::PRICED_RATE.', 0)) AS generated_total')
            ->get()
            ->keyBy('boq_id');

        $totals = [];
        foreach ($boqIds as $id) {
            $totals[$id] = $this->shape($rows->get($id));
        }

        return $totals;
    }

    /** @return array{items: int, estimated_items: int, priced_items: int, estimated_amount: float, generated_total: float, difference: float} */
    public function forBoq(Boq $boq): array
    {
        return $this->forBoqs([$boq->id])[$boq->id];
    }

    /**
     * Totals per project across its (not deleted) BOQs.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, array{boqs: int, items: int, estimated_items: int, priced_items: int, estimated_amount: float, generated_total: float, difference: float}>
     */
    public function forProjects(array $projectIds): array
    {
        $boqs = Boq::query()->whereIn('project_id', $projectIds)->get(['id', 'project_id']);
        $perBoq = $this->forBoqs($boqs->pluck('id')->all());

        $totals = [];
        foreach ($projectIds as $projectId) {
            $sum = ['boqs' => 0, 'items' => 0, 'estimated_items' => 0, 'priced_items' => 0, 'estimated_amount' => 0.0, 'generated_total' => 0.0];

            foreach ($boqs->where('project_id', $projectId) as $boq) {
                $sum['boqs']++;
                foreach (['items', 'estimated_items', 'priced_items', 'estimated_amount', 'generated_total'] as $key) {
                    $sum[$key] += $perBoq[$boq->id][$key];
                }
            }

            $sum['estimated_amount'] = round($sum['estimated_amount'], 2);
            $sum['generated_total'] = round($sum['generated_total'], 2);
            $sum['difference'] = round($sum['generated_total'] - $sum['estimated_amount'], 2);
            $totals[$projectId] = $sum;
        }

        return $totals;
    }

    private function shape(?object $row): array
    {
        $estimated = round((float) ($row->estimated_amount ?? 0), 2);
        $generated = round((float) ($row->generated_total ?? 0), 2);

        return [
            'items' => (int) ($row->items ?? 0),
            'estimated_items' => (int) ($row->estimated_items ?? 0),
            'priced_items' => (int) ($row->priced_items ?? 0),
            'estimated_amount' => $estimated,
            'generated_total' => $generated,
            'difference' => round($generated - $estimated, 2),
        ];
    }
}
