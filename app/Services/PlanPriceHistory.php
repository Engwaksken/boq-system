<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanPriceChange;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Plan price changes a user may see: always the plans they (or their
 * organisation) subscribed to, plus every available plan for scope "all".
 * Shared by the Subscriptions Price History tab and the API.
 */
class PlanPriceHistory
{
    public const SCOPES = ['mine', 'all'];

    /**
     * Ids of every plan the user or their organisation has subscribed to.
     * Organisation subscriptions only count for members of that organisation.
     *
     * @return list<int>
     */
    public function subscribedPlanIds(User $user): array
    {
        return Subscription::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);

                if ($user->organisation_id !== null) {
                    $query->orWhere('organisation_id', $user->organisation_id);
                }
            })
            ->distinct()
            ->pluck('plan_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** Newest change first. */
    public function query(array $subscribedPlanIds, string $scope): Builder
    {
        return PlanPriceChange::query()
            ->with('plan')
            ->where(function (Builder $query) use ($subscribedPlanIds, $scope) {
                $query->whereIn('plan_id', $subscribedPlanIds ?: [0]);

                if ($scope === 'all') {
                    $query->orWhereIn(
                        'plan_id',
                        Plan::query()->where('is_active', true)->where('is_archived', false)->select('id')
                    );
                }
            })
            ->orderByDesc('changed_at')
            ->orderByDesc('id');
    }
}
