<?php

namespace App\Policies;

use App\Models\Boq;
use App\Models\Project;
use App\Models\User;

class BoqPolicy
{
    /**
     * Determine whether the user can view a BOQ in their tenant.
     */
    public function view(User $user, Boq $boq): bool
    {
        return $user->hasPermission('boq.view')
            && $this->canAccessBoq($user, $boq);
    }

    /**
     * Determine whether the user can change a BOQ.
     */
    public function update(User $user, Boq $boq): bool
    {
        return $user->hasPermission('boq.edit')
            && $this->canAccessBoq($user, $boq);
    }

    /**
     * Determine whether the user can process an uploaded BOQ.
     */
    public function process(User $user, Boq $boq): bool
    {
        return $this->update($user, $boq);
    }

    /**
     * Determine whether the user can approve BOQ pricing.
     */
    public function approve(User $user, Boq $boq): bool
    {
        return $user->hasPermission('boq.approve')
            && $this->canAccessBoq($user, $boq);
    }

    /**
     * Determine whether the user can create or import a BOQ for a project.
     */
    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission('boq.edit')
            && $this->canAccessProject($user, $project);
    }

    /**
     * Determine whether the user can import a BOQ for a project.
     */
    public function import(User $user, Project $project): bool
    {
        return $this->create($user, $project);
    }

    private function canAccessBoq(User $user, Boq $boq): bool
    {
        $project = $boq->project;

        return $project !== null
            && $boq->organisation_id === $project->organisation_id
            && $this->canAccessProject($user, $project);
    }

    private function canAccessProject(User $user, Project $project): bool
    {
        if ($user->organisation_id !== null) {
            return $project->organisation_id === $user->organisation_id;
        }

        return $project->organisation_id === null
            && $project->user_id === $user->id;
    }
}
