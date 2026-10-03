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
        // Listing and "current" are always scoped to the authenticated account, so
        // every signed-in user may review their own subscriptions. Individual records
        // remain protected by view().
        return true;
    }

    /**
     * Determine whether the user can view the subscription.
     */
    public function view(User $user, Subscription $subscription): bool
    {
        // Super admin can view any subscription
        if ($user->isSuperAdmin()) {
            return true;
        }

        // User can view their own subscription (self or as beneficiary)
        $beneficiaryId = $subscription->beneficiary_id ?? $subscription->user_id;
        if ($beneficiaryId === $user->id) {
            return true;
        }

        // Payers may view subscriptions they paid for.
        if ($subscription->payer_id === $user->id) {
            return true;
        }

        // Organisation membership alone is not sufficient to view another
        // member's subscription; require both explicit admin role and permission.
        return $user->hasPermission('subscriptions.view')
            && $this->isOrganisationAdmin($user, $subscription->organisation_id)
            && $user->organisation_id !== null
            && $subscription->organisation_id === $user->organisation_id;
    }

    /**
     * Determine whether the user can create a self-subscription.
     */
    public function create(User $user, Plan $plan): bool
    {
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
        if (! $user->isSuperAdmin()
            && ! $this->isOrganisationAdmin($user, $beneficiary->organisation_id)) {
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
        return $this->canPay($user, $subscription);
    }

    /**
     * Determine whether the user can manage a proxy subscription.
     *
     * Payer OR admin can manage proxy subscriptions.
     */
    public function manageProxy(User $user, Subscription $subscription): bool
    {
        // Payment authority belongs to the payer, never the beneficiary solely
        // by virtue of being the beneficiary.
        return $this->canPay($user, $subscription);
    }

    public function canPay(User $user, Subscription $subscription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // A populated beneficiary identifies a proxy subscription. Payer and
        // billing-administrator authority must not grant access to self-subscriptions.
        if ($subscription->beneficiary_id !== null) {
            return $subscription->payer_id === $user->id
                || ($user->hasPermission('subscriptions.manage')
                    && $this->isBillingAdministrator($user, $subscription->organisation_id));
        }

        // The owner may pay a regular self-subscription explicitly.
        return $subscription->user_id === $user->id;
    }

    /**
     * Determine whether the user can view a proxy subscription.
     *
     * Payer OR beneficiary OR admin can view proxy subscriptions.
     */
    public function viewProxy(User $user, Subscription $subscription): bool
    {
        if (! $user->hasPermission('subscriptions.view')
            && ! $user->hasRole('user')) {
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
            return $user->hasPermission('subscriptions.view')
                && $this->isOrganisationAdmin($user, $subscription->organisation_id);
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
            return $user->hasPermission('subscriptions.manage')
                && $this->isOrganisationAdmin($user, $subscription->organisation_id);
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
            return $user->hasPermission('subscriptions.manage')
                && $this->isOrganisationAdmin($user, $subscription->organisation_id);
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

    /** Check organisation administrator role in the subscription's organisation. */
    private function isOrganisationAdmin(User $user, ?int $organisationId): bool
    {
        if ($organisationId === null || $user->organisation_id !== $organisationId) {
            return false;
        }

        return $user->roles()
            ->whereIn('roles.slug', ['administrator'])
            ->wherePivot('organisation_id', $organisationId)
            ->exists();
    }

    /** Check the billing-administrator role assigned within this organisation. */
    private function isBillingAdministrator(User $user, ?int $organisationId): bool
    {
        if ($organisationId === null || $user->organisation_id !== $organisationId) {
            return false;
        }

        return $user->roles()
            ->where('roles.slug', 'billing-administrator')
            ->wherePivot('organisation_id', $organisationId)
            ->exists();
    }
}
