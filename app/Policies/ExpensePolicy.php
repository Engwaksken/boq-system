<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\Project;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $this->hasProjectScope($user, $expense)
            && ($expense->creator_user_id === $user->id || $expense->purchaser_user_id === $user->id);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->organisation_id !== null
            && $project->organisation_id === $user->organisation_id
            && $this->canAccessProject($user, $project);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense);
    }

    private function hasProjectScope(User $user, Expense $expense): bool
    {
        $project = $expense->project;

        return $project !== null
            && $user->organisation_id !== null
            && $expense->organisation_id === $user->organisation_id
            && $project->organisation_id === $user->organisation_id
            && $expense->project_id === $project->id
            && $this->canAccessProject($user, $project);
    }

    private function canAccessProject(User $user, Project $project): bool
    {
        return $project->assignments()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->exists();
    }
}
