<?php

namespace App\Services;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ExpenseService
{
    public function create(User $user, array $data): Expense
    {
        $data = Validator::make($data, (new StoreExpenseRequest)->rules())->validate();
        $project = Project::where('organisation_id', $user->organisation_id)
            ->whereHas('assignments', fn ($query) => $query->where('user_id', $user->id)->whereNull('deleted_at'))
            ->find($data['project_id']);
        abort_unless($project, 404);
        Gate::forUser($user)->authorize('create', [Expense::class, $project]);

        $items = $this->normaliseItems($data);
        foreach ($items as $item) {
            $this->validateBoqLinks($project, $item);
        }
        $first = $items[0];
        $total = round(array_sum(array_column($items, 'total')), 2);
        $deduplicationHash = $this->deduplicationHash($project, $data, $items, $total);

        abort_if(
            Expense::where('organisation_id', $project->organisation_id)
                ->where('deduplication_hash', $deduplicationHash)
                ->exists(),
            409,
            'A matching expense already exists for this project.'
        );

        return DB::transaction(function () use ($data, $items, $first, $project, $user, $total, $deduplicationHash): Expense {
            $expense = Expense::create(array_merge($data, [
                'description' => $first['description'],
                'quantity' => $first['quantity'],
                'unit' => $first['unit'],
                'rate' => $first['rate'],
                'boq_id' => $first['boq_id'],
                'boq_item_id' => $first['boq_item_id'],
                'organisation_id' => $project->organisation_id,
                'creator_user_id' => $user->id,
                'purchaser_user_id' => $user->id,
                'total' => $total,
                'deduplication_hash' => $deduplicationHash,
            ]));

            foreach ($items as $item) {
                $expense->items()->create($item);
            }

            return $expense->load('items');
        });
    }

    public function update(User $user, Expense $expense, array $data): Expense
    {
        Gate::forUser($user)->authorize('update', $expense);
        $data = Validator::make($data, (new UpdateExpenseRequest)->rules())->validate();
        if (isset($data['quantity']) || isset($data['rate'])) {
            $data['total'] = round((float) ($data['quantity'] ?? $expense->quantity) * (float) ($data['rate'] ?? $expense->rate), 2);
        }
        if (($data['is_planned'] ?? false) === true) {
            $data['explanation'] = null;
        }

        $expense->update($data);

        $firstItem = $expense->items()->oldest('id')->first();
        if ($firstItem && (isset($data['description']) || isset($data['quantity']) || isset($data['unit']) || isset($data['rate']))) {
            $firstItem->fill([
                'description' => $data['description'] ?? $firstItem->description,
                'quantity' => $data['quantity'] ?? $firstItem->quantity,
                'unit' => $data['unit'] ?? $firstItem->unit,
                'rate' => $data['rate'] ?? $firstItem->rate,
                'total' => round((float) ($data['quantity'] ?? $firstItem->quantity) * (float) ($data['rate'] ?? $firstItem->rate), 2),
            ])->save();

            $expense->forceFill(['total' => round((float) $expense->items()->sum('total'), 2)])->save();
        }

        return $expense->refresh();
    }

    /**
     * A linked BOQ must be approved and belong to the expense's project and
     * organisation, and a linked BOQ item must be approved and belong to that
     * BOQ. This prevents cross-project or cross-tenant references and keeps
     * spending tied to the signed-off budget.
     */
    private function validateBoqLinks(Project $project, array $data): void
    {
        $boqId = isset($data['boq_id']) ? (int) $data['boq_id'] : null;
        $boqItemId = isset($data['boq_item_id']) ? (int) $data['boq_item_id'] : null;

        if ($boqItemId !== null && $boqId === null) {
            abort(422, 'Select a BOQ when linking a BOQ item.');
        }

        if ($boqId !== null) {
            $boq = Boq::find($boqId);
            abort_unless(
                $boq && $boq->project_id === $project->id && $boq->organisation_id === $project->organisation_id,
                422,
                'The selected BOQ does not belong to this project.'
            );
            abort_unless($boq->status === 'approved', 422, 'Only an approved BOQ can be linked to an expense.');
        }

        if ($boqItemId !== null) {
            $boqItem = BoqItem::find($boqItemId);
            abort_unless($boqItem && $boqItem->boq_id === $boqId, 422, 'The selected BOQ item does not belong to the selected BOQ.');
            abort_unless($boqItem->status === 'approved', 422, 'Only an approved BOQ item can be linked to an expense.');
        }
    }

    /** Normalize the legacy single-line form and new multi-line payload alike. */
    private function normaliseItems(array $data): array
    {
        $source = $data['items'] ?? [[
            'description' => $data['description'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'rate' => $data['rate'],
            'boq_id' => $data['boq_id'] ?? null,
            'boq_item_id' => $data['boq_item_id'] ?? null,
        ]];

        return array_map(static function (array $item): array {
            $quantity = round((float) $item['quantity'], 3);
            $rate = round((float) $item['rate'], 2);

            return [
                'description' => trim((string) $item['description']),
                'quantity' => $quantity,
                'unit' => trim((string) $item['unit']),
                'rate' => $rate,
                'total' => round($quantity * $rate, 2),
                'boq_id' => isset($item['boq_id']) ? (int) $item['boq_id'] : null,
                'boq_item_id' => isset($item['boq_item_id']) ? (int) $item['boq_item_id'] : null,
            ];
        }, $source);
    }

    /** Canonical digest of the fields that define a distinct expense record. */
    private function deduplicationHash(Project $project, array $data, array $items, float $total): string
    {
        return hash('sha256', implode('|', [
            (string) $project->id,
            (string) $data['purchase_date'],
            mb_strtolower(trim((string) ($data['supplier'] ?? ''))),
            json_encode(array_map(static fn (array $item): array => [
                mb_strtolower($item['description']),
                $item['quantity'],
                mb_strtolower($item['unit']),
                $item['rate'],
                $item['boq_id'],
                $item['boq_item_id'],
            ], $items), JSON_THROW_ON_ERROR),
            mb_strtolower(trim((string) $data['currency'])),
            (string) round($total, 2),
        ]));
    }
}
