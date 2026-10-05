<?php

namespace App\Services;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Expense;
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

        $this->validateBoqLinks($project, $data);

        $total = round((float) $data['quantity'] * (float) $data['rate'], 2);
        $deduplicationHash = $this->deduplicationHash($project, $data, $total);

        abort_if(
            Expense::where('organisation_id', $project->organisation_id)
                ->where('deduplication_hash', $deduplicationHash)
                ->exists(),
            409,
            'A matching expense already exists for this project.'
        );

        return Expense::create(array_merge($data, [
            'organisation_id' => $project->organisation_id,
            'creator_user_id' => $user->id, 'purchaser_user_id' => $user->id,
            'total' => $total,
            'deduplication_hash' => $deduplicationHash,
        ]));
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

        return $expense->refresh();
    }

    /**
     * A linked BOQ must belong to the expense's project and organisation, and a
     * linked BOQ item must belong to that BOQ. This prevents cross-project or
     * cross-tenant references from being stored through an unchecked write path.
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
        }

        if ($boqItemId !== null) {
            $boqItem = BoqItem::find($boqItemId);
            abort_unless($boqItem && $boqItem->boq_id === $boqId, 422, 'The selected BOQ item does not belong to the selected BOQ.');
        }
    }

    /** Canonical digest of the fields that define a distinct expense record. */
    private function deduplicationHash(Project $project, array $data, float $total): string
    {
        return hash('sha256', implode('|', [
            (string) $project->id,
            (string) $data['purchase_date'],
            mb_strtolower(trim((string) ($data['supplier'] ?? ''))),
            mb_strtolower(trim((string) $data['description'])),
            (string) round((float) $data['quantity'], 3),
            mb_strtolower(trim((string) $data['unit'])),
            (string) round((float) $data['rate'], 2),
            mb_strtolower(trim((string) $data['currency'])),
            (string) round($total, 2),
        ]));
    }
}
