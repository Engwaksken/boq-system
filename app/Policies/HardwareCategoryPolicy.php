<?php

namespace App\Policies;

use App\Models\HardwareCategory;
use App\Models\User;

class HardwareCategoryPolicy
{
    /**
     * Determine whether the user can view any hardware categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hardware-prices.view')
            || $user->hasPermission('hardware-prices.manage');
    }

    /**
     * Determine whether the user can view the hardware category.
     */
    public function view(User $user, HardwareCategory $category): bool
    {
        if (! $user->hasPermission('hardware-prices.view')
            && ! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can view any category
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Categories are global - any organisation member with permission can view
        if ($user->organisation_id !== null) {
            return true;
        }

        // Users without organisation can view if they have permission
        return true;
    }

    /**
     * Determine whether the user can create hardware categories.
     */
    public function create(User $user): bool
    {
        if (! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can create categories
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Organisation admin can create categories
        return $user->organisation_id !== null;
    }

    /**
     * Determine whether the user can update the hardware category.
     */
    public function update(User $user, HardwareCategory $category): bool
    {
        if (! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can update any category
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Organisation admin can update categories
        return $user->organisation_id !== null;
    }

    /**
     * Determine whether the user can delete the hardware category.
     */
    public function delete(User $user, HardwareCategory $category): bool
    {
        if (! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can delete any category
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Organisation admin can delete categories
        return $user->organisation_id !== null;
    }

    /**
     * Determine whether the user can toggle the hardware category active status.
     */
    public function toggleActive(User $user, HardwareCategory $category): bool
    {
        if (! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can toggle any category
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Organisation admin can toggle categories
        return $user->organisation_id !== null;
    }
}