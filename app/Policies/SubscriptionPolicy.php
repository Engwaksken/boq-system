<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    /**
     * Determine whether the user can view any subscriptions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('subscriptions.view');
    }

    /**
     * Determine whether the user can view the subscription.
     */
    public function view(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.view')) {
            return false;
        }

        // Super admin can view any subscription
        if ($user->isSuperAdmin()) {
            return true;
        }

        // User can view their own subscription (self or as beneficiary)
        $beneficiaryId = $subscription->beneficiary_id ?? $subscription->user_id;
        if ($beneficiaryId === $user->id) {
            return true;
        }

        // Payer can view subscriptions they paid for
        if ($subscription->payer_id === $user->id) {
            return true;
        }

        // Organisation admin can view organisation subscriptions
        if ($user->organisation_id !== null && $subscription->organisation_id === $user->organisation_id) {
            return $user->hasPermission('subscriptions.view');
        }

        return false;
    }

    /**
     * Determine whether the user can create a self-subscription.
     */
    public function create(User $user, Plan $plan): bool
    {
        if (! $user->hasPermission('subscriptions.manage')) {
            return false;
        }

        // Plan must be active and not archived
        if (! $plan->is_active || $plan->is_archived) {
            return false;
        }

        // User must not have an active subscription already (unless it's a renewal)
        $hasActiveSubscription = Subscription::query()
            ->where(function ($query) use ($user) {
                $query->where('beneficiary_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNull('beneficiary_id')->where('user_id', $user->id);
                    });
            })
            ->where('status', 'active')
            ->exists();

        if ($hasActiveSubscription) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create a proxy subscription for another user.
     *
     * Super Admin/Admin only. Beneficiary must not have an active subscription
     * unless the plan allows renewal (handled by the controller/service layer).
     */
    public function createProxy(User $user, Plan $plan, User $beneficiary): bool
    {
        // Only Super Admin or Administrator can create proxy subscriptions
        if (! $user->hasAnyRole(['super-admin', 'super_admin', 'administrator'])) {
            return false;
        }

        // Plan must be active and not archived
        if (! $plan->is_active || $plan->is_archived) {
            return false;
        }

        // Beneficiary must be in the same organisation (if user has organisation)
        if ($user->organisation_id !== null && $beneficiary->organisation_id !== $user->organisation_id) {
            return false;
        }

        // Beneficiary must not have an active subscription
        // (Renewal logic is handled by the controller/service layer)
        $hasActiveSubscription = Subscription::query()
            ->where(function ($query) use ($beneficiary) {
                $query->where('beneficiary_id', $beneficiary->id)
                    ->orWhere(function ($q) use ($beneficiary) {
                        $q->whereNull('beneficiary_id')->where('user_id', $beneficiary->id);
                    });
            })
            ->where('status', 'active')
            ->exists();

        if ($hasActiveSubscription) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can update the subscription.
     */
    public function update(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.manage')) {
            return false;
        }

        // Super admin can update any subscription
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Payer can update subscriptions they paid for
        if ($subscription->payer_id === $user->id) {
            return true;
        }

        // Organisation admin can update organisation subscriptions
        if ($user->organisation_id !== null && $subscription->organisation_id === $user->organisation_id) {
            return $user->hasPermission('subscriptions.manage');
        }

        return false;
    }

    /**
     * Determine whether the user can manage a proxy subscription.
     *
     * Payer OR admin can manage proxy subscriptions.
     */
    public function manageProxy(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.manage')) {
            return false;
        }

        // Super admin can manage any subscription
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Payer can manage subscriptions they paid for
        if ($subscription->payer_id === $user->id) {
            return true;
        }

        // Organisation admin can manage organisation subscriptions
        if ($user->organisation_id !== null && $subscription->organisation_id === $user->organisation_id) {
            return $user->hasPermission('subscriptions.manage');
        }

        return false;
    }

    /**
     * Determine whether the user can view a proxy subscription.
     *
     * Payer OR beneficiary OR admin can view proxy subscriptions.
     */
    public function viewProxy(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.view')) {
            return false;
        }

        // Super admin can view any subscription
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Beneficiary can view their subscription
        $beneficiaryId = $subscription->beneficiary_id ?? $subscription->user_id;
        if ($beneficiaryId === $user->id) {
            return true;
        }

        // Payer can view subscriptions they paid for
        if ($subscription->payer_id === $user->id) {
            return true;
        }

        // Organisation admin can view organisation subscriptions
        if ($user->organisation_id !== null && $subscription->organisation_id === $user->organisation_id) {
            return $user->hasPermission('subscriptions.view');
        }

        return false;
    }

    /**
     * Determine whether the user can cancel a proxy subscription.
     *
     * Payer OR admin can cancel proxy subscriptions.
     */
    public function cancelProxy(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.manage')) {
            return false;
        }

        // Super admin can cancel any subscription
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Payer can cancel subscriptions they paid for
        if ($subscription->payer_id === $user->id) {
            return true;
        }

        // Organisation admin can cancel organisation subscriptions
        if ($user->organisation_id !== null && $subscription->organisation_id === $user->organisation_id) {
            return $user->hasPermission('subscriptions.manage');
        }

        return false;
    }

    /**
     * Determine whether the user can cancel a subscription (self or proxy).
     */
    public function cancel(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.manage')) {
            return false;
        }

        // Super admin can cancel any subscription
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Beneficiary can cancel their own subscription
        $beneficiaryId = $subscription->beneficiary_id ?? $subscription->user_id;
        if ($beneficiaryId === $user->id) {
            return true;
        }

        // Payer can cancel subscriptions they paid for
        if ($subscription->payer_id === $user->id) {
            return true;
        }

        // Organisation admin can cancel organisation subscriptions
        if ($user->organisation_id !== null && $subscription->organisation_id === $user->organisation_id) {
            return $user->hasPermission('subscriptions.manage');
        }

        return false;
    }

    /**
     * Determine whether the user can delete the subscription.
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.manage')) {
            return false;
        }

        // Only super admin can delete subscriptions (hard delete)
        if ($user->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the subscription.
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.manage')) {
            return false;
        }

        // Only super admin can restore soft-deleted subscriptions
        if ($user->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can force delete the subscription.
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.manage')) {
            return false;
        }

        // Only super admin can force delete subscriptions
        if ($user->isSuperAdmin()) {
            return true;
        }

        return false;
    }
}