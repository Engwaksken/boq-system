<?php

namespace App\Policies;

use App\Models\BoqPricingJob;
use App\Models\User;

class BoqPricingJobPolicy
{
    /**
     * Determine whether the user can view any pricing jobs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('boq.view');
    }

    /**
     * Determine whether the user can view the pricing job.
     */
    public function view(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.view')) {
            return false;
        }

        return $this->canAccessJob($user, $job);
    }

    /**
     * Determine whether the user can create pricing jobs.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('boq.edit');
    }

    /**
     * Determine whether the user can update the pricing job.
     */
    public function update(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Super admin can update any job
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Owner can update their own job
        if ($job->user_id === $user->id) {
            return true;
        }

        // Org admin can update org jobs
        return $this->canAccessJob($user, $job);
    }

    /**
     * Determine whether the user can delete the pricing job.
     */
    public function delete(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Super admin can delete any job
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Owner can delete their own job
        if ($job->user_id === $user->id) {
            return true;
        }

        // Org admin can delete org jobs
        return $this->canAccessJob($user, $job);
    }

    /**
     * Determine whether the user can start the pricing job.
     */
    public function start(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Must be the owner
        if ($job->user_id !== $user->id) {
            return false;
        }

        // Job must be in queued status
        return $job->status === 'queued';
    }

    /**
     * Determine whether the user can pause the pricing job.
     */
    public function pause(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Must be the owner
        if ($job->user_id !== $user->id) {
            return false;
        }

        // Job must be in processing status
        return $job->status === 'processing';
    }

    /**
     * Determine whether the user can resume the pricing job.
     */
    public function resume(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Must be the owner
        if ($job->user_id !== $user->id) {
            return false;
        }

        // Job must be in paused status
        return $job->status === 'paused';
    }

    /**
     * Determine whether the user can cancel the pricing job.
     */
    public function cancel(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Must be the owner
        if ($job->user_id !== $user->id) {
            return false;
        }

        // Job must not be in a completed state
        return ! in_array($job->status, ['completed', 'completed_with_errors', 'cancelled']);
    }

    /**
     * Determine whether the user can retry failed items in the pricing job.
     */
    public function retryFailed(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Must be the owner
        if ($job->user_id !== $user->id) {
            return false;
        }

        // Job must have failed items
        return $job->failed_items > 0;
    }

    /**
     * Determine whether the user can lock the pricing job.
     */
    public function lock(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Must be the owner
        if ($job->user_id !== $user->id) {
            return false;
        }

        // Job must not already be locked
        return ! $job->isLocked();
    }

    /**
     * Determine whether the user can unlock the pricing job.
     */
    public function unlock(User $user, BoqPricingJob $job): bool
    {
        if (! $user->hasPermission('boq.edit')) {
            return false;
        }

        // Must be the owner
        if ($job->user_id === $user->id) {
            return true;
        }

        // Or must be the user who locked it
        if ($job->locked_by === $user->id) {
            return true;
        }

        // Super admin can unlock any job
        if ($user->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user can access the pricing job through organisation membership.
     */
    private function canAccessJob(User $user, BoqPricingJob $job): bool
    {
        // If user has no organisation, they can only access their own jobs
        if ($user->organisation_id === null) {
            return $job->user_id === $user->id;
        }

        // Check if job belongs to user's organisation
        return $job->organisation_id === $user->organisation_id;
    }
}