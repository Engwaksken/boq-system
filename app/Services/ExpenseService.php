<?php

namespace App\Services;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
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

        return Expense::create(array_merge($data, [
            'organisation_id' => $project->organisation_id,
            'creator_user_id' => $user->id, 'purchaser_user_id' => $user->id,
            'total' => round((float) $data['quantity'] * (float) $data['rate'], 2),
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
}
