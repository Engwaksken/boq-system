<?php

namespace App\Policies;

use App\Models\AiProvider;
use App\Models\User;

class AiProviderPolicy
{
    /**
     * Determine whether the user can view any AI providers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hardware-prices.view')
            || $user->hasPermission('hardware-prices.manage');
    }

    /**
     * Determine whether the user can view the AI provider.
     */
    public function view(User $user, AiProvider $provider): bool
    {
        if (! $user->hasPermission('hardware-prices.view')
            && ! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can view any provider
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Global providers (no organisation) are viewable by any user with permission
        if ($provider->organisation_id === null) {
            return true;
        }

        // Organisation members can view their organisation's providers
        if ($user->organisation_id !== null) {
            return $provider->organisation_id === $user->organisation_id;
        }

        // Users without organisation can only view global providers
        return false;
    }

    /**
     * Determine whether the user can create AI providers.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('hardware-prices.manage');
    }

    /**
     * Determine whether the user can update the AI provider.
     */
    public function update(User $user, AiProvider $provider): bool
    {
        if (! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can update any provider
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Global providers can only be updated by super admin
        if ($provider->organisation_id === null) {
            return false;
        }

        // Organisation admin can update their organisation's providers
        if ($user->organisation_id !== null) {
            return $provider->organisation_id === $user->organisation_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the AI provider.
     */
    public function delete(User $user, AiProvider $provider): bool
    {
        if (! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can delete any provider
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Global providers can only be deleted by super admin
        if ($provider->organisation_id === null) {
            return false;
        }

        // Organisation admin can delete their organisation's providers
        if ($user->organisation_id !== null) {
            return $provider->organisation_id === $user->organisation_id;
        }

        return false;
    }

    /**
     * Determine whether the user can test the AI provider.
     */
    public function test(User $user, AiProvider $provider): bool
    {
        if (! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can test any provider
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Global providers can only be tested by super admin
        if ($provider->organisation_id === null) {
            return false;
        }

        // Organisation admin can test their organisation's providers
        if ($user->organisation_id !== null) {
            return $provider->organisation_id === $user->organisation_id;
        }

        return false;
    }

    /**
     * Determine whether the user can set the AI provider as default.
     */
    public function setDefault(User $user, AiProvider $provider): bool
    {
        if (! $user->hasPermission('hardware-prices.manage')) {
            return false;
        }

        // Super admin can set any provider as default
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Global providers can only be set as default by super admin
        if ($provider->organisation_id === null) {
            return false;
        }

        // Organisation admin can set their organisation's providers as default
        if ($user->organisation_id !== null) {
            return $provider->organisation_id === $user->organisation_id;
        }

        return false;
    }
}