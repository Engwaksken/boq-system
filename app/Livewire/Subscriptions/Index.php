<?php

namespace App\Livewire\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    */

    public string $activeTab = 'subscriptions';

    /*
    |--------------------------------------------------------------------------
    | Subscription Filters
    |--------------------------------------------------------------------------
    */

    public string $search = '';

    public string $statusFilter = 'all';

    public string $periodFilter = 'all';

    public int $perPage = 10;

    /*
    |--------------------------------------------------------------------------
    | Plan Filters
    |--------------------------------------------------------------------------
    */

    public string $planSearch = '';

    public string $planPeriodFilter = 'all';

    public int $planPerPage = 10;

    /*
    |--------------------------------------------------------------------------
    | Selection
    |--------------------------------------------------------------------------
    */

    public array $selectedSubscriptions = [];

    public bool $selectPage = false;

    /*
    |--------------------------------------------------------------------------
    | Action Modal
    |--------------------------------------------------------------------------
    */

    public bool $showActionModal = false;

    public string $actionType = '';

    public ?int $actionSubscriptionId = null;

    public ?int $actionPlanId = null;

    public string $actionTitle = '';

    public string $actionMessage = '';

    /*
    |--------------------------------------------------------------------------
    | Current Subscription
    |--------------------------------------------------------------------------
    */

    public $currentSubscription = null;

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->refreshCurrentSubscription();
    }

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    */

    public function showSubscriptions(): void
    {
        $this->activeTab = 'subscriptions';

        $this->resetValidation();
    }

    public function showPlans(): void
    {
        $this->activeTab = 'plans';

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | Filter Updates
    |--------------------------------------------------------------------------
    */

    public function updatedSearch(): void
    {
        $this->resetSubscriptionPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetSubscriptionPage();
    }

    public function updatedPeriodFilter(): void
    {
        $this->resetSubscriptionPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetSubscriptionPage();
    }

    public function updatedPlanSearch(): void
    {
        $this->resetPage('plansPage');
    }

    public function updatedPlanPeriodFilter(): void
    {
        $this->resetPage('plansPage');
    }

    public function updatedPlanPerPage(): void
    {
        $this->resetPage('plansPage');
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination / Selection
    |--------------------------------------------------------------------------
    */

    private function resetSubscriptionPage(): void
    {
        $this->resetPage('subscriptionsPage');

        $this->clearSelection();
    }

    public function clearSelection(): void
    {
        $this->selectedSubscriptions = [];

        $this->selectPage = false;
    }

    public function updatedSelectPage(bool $value): void
    {
        if (! $value) {
            $this->selectedSubscriptions = [];

            return;
        }

        $currentPage = $this->getPage('subscriptionsPage');

        $this->selectedSubscriptions = $this
            ->subscriptionsQuery()
            ->forPage(
                $currentPage,
                $this->perPage
            )
            ->pluck('id')
            ->map(
                fn ($id) => (string) $id
            )
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription Confirmation Actions
    |--------------------------------------------------------------------------
    */

    public function confirmSubscribe(int $planId): void
    {
        $plan = Plan::query()
            ->whereKey($planId)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->firstOrFail();

        $this->actionType = 'subscribe';

        $this->actionPlanId = $plan->id;

        $this->actionSubscriptionId = null;

        $this->actionTitle =
            'Choose '.$plan->name.'?';

        $this->actionMessage =
            'A pending subscription will be created. '
            .'Complete payment to activate access.';

        $this->showActionModal = true;
    }

    public function confirmCancel(
        int $subscriptionId
    ): void {
        $subscription =
            $this->ownedSubscription(
                $subscriptionId
            );

        $this->actionType = 'cancel';

        $this->actionSubscriptionId =
            $subscription->id;

        $this->actionPlanId = null;

        $this->actionTitle =
            'Cancel subscription?';

        $this->actionMessage =
            'Cancel '
            .($subscription->plan?->name
                ?? 'this subscription')
            .'? Your projects and BOQ data will be preserved.';

        $this->showActionModal = true;
    }

    public function confirmBulkCancel(): void
    {
        if (
            empty(
                $this->selectedSubscriptions
            )
        ) {
            return;
        }

        $this->actionType = 'bulk_cancel';

        $this->actionPlanId = null;

        $this->actionSubscriptionId = null;

        $this->actionTitle =
            'Cancel selected subscriptions?';

        $this->actionMessage =
            count(
                $this->selectedSubscriptions
            )
            .' selected subscription(s) will be cancelled. '
            .'Project and BOQ data will remain available.';

        $this->showActionModal = true;
    }

    public function closeActionModal(): void
    {
        $this->showActionModal = false;

        $this->actionType = '';

        $this->actionSubscriptionId = null;

        $this->actionPlanId = null;

        $this->actionTitle = '';

        $this->actionMessage = '';
    }

    /*
    |--------------------------------------------------------------------------
    | Execute Actions
    |--------------------------------------------------------------------------
    */

    public function performAction(): void
    {
        match ($this->actionType) {
            'subscribe' =>
                $this->subscribeToPlan(),

            'cancel' =>
                $this->cancelSubscription(),

            'bulk_cancel' =>
                $this->bulkCancel(),

            default => null,
        };

        $this->closeActionModal();

        $this->refreshCurrentSubscription();
    }

    /*
    |--------------------------------------------------------------------------
    | Subscribe
    |--------------------------------------------------------------------------
    */

    private function subscribeToPlan(): void
    {
        if (! $this->actionPlanId) {
            return;
        }

        $plan = Plan::query()
            ->whereKey(
                $this->actionPlanId
            )
            ->where('is_active', true)
            ->where('is_archived', false)
            ->firstOrFail();

        /** @var User $user */
        $user = auth()->user();

        $existingPending =
            Subscription::query()
                ->where(
                    'plan_id',
                    $plan->id
                )
                ->where(
                    'status',
                    'pending'
                )
                ->where(
                    fn (Builder $query) =>
                        $this->applyOwnershipScope(
                            $query,
                            $user
                        )
                )
                ->latest()
                ->first();

        if ($existingPending) {
            session()->flash(
                'message',
                'You already have a pending '
                .$plan->name
                .' subscription.'
            );

            $this->showSubscriptions();

            return;
        }

        $subscription =
            new Subscription();

        $subscription->user_id =
            $user->id;

        $subscription->organisation_id =
            $user->organisation_id;

        $subscription->plan_id =
            $plan->id;

        $subscription->status =
            'pending';

        $subscription->payment_status =
            'pending';

        $subscription->access_type =
            $plan->type;

        $subscription->auto_renewal =
            (bool) $plan->auto_renewal;

        $subscription->save();

        $this->showSubscriptions();

        $this->resetPage(
            'subscriptionsPage'
        );

        session()->flash(
            'message',
            $plan->name
            .' selected. Complete payment to activate your subscription.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel One Subscription
    |--------------------------------------------------------------------------
    */

    private function cancelSubscription(): void
    {
        if (
            ! $this->actionSubscriptionId
        ) {
            return;
        }

        $subscription =
            $this->ownedSubscription(
                $this->actionSubscriptionId
            );

        if (
            in_array(
                $subscription->status,
                [
                    'cancelled',
                    'expired',
                ],
                true
            )
        ) {
            return;
        }

        $subscription->update([
            'status' =>
                'cancelled',

            'cancellation_date' =>
                now(),

            'auto_renewal' =>
                false,
        ]);

        $subscription
            ->entitlements()
            ->update([
                'status' => 'revoked',
            ]);

        session()->flash(
            'message',
            'Subscription cancelled. '
            .'Your projects and BOQ data were preserved.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Cancel
    |--------------------------------------------------------------------------
    */

    private function bulkCancel(): void
    {
        if (
            empty(
                $this->selectedSubscriptions
            )
        ) {
            return;
        }

        /** @var User $user */
        $user = auth()->user();

        $ids = collect(
            $this->selectedSubscriptions
        )
            ->map(
                fn ($id) => (int) $id
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $subscriptions =
            Subscription::query()
                ->whereIn(
                    'id',
                    $ids
                )
                ->where(
                    fn (Builder $query) =>
                        $this->applyOwnershipScope(
                            $query,
                            $user
                        )
                )
                ->whereNotIn(
                    'status',
                    [
                        'cancelled',
                        'expired',
                    ]
                )
                ->get();

        foreach (
            $subscriptions
            as $subscription
        ) {
            $subscription->update([
                'status' =>
                    'cancelled',

                'cancellation_date' =>
                    now(),

                'auto_renewal' =>
                    false,
            ]);

            $subscription
                ->entitlements()
                ->update([
                    'status' =>
                        'revoked',
                ]);
        }

        $count =
            $subscriptions->count();

        $this->clearSelection();

        session()->flash(
            'message',
            $count
            .' subscription(s) cancelled successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Subscriptions Query
    |--------------------------------------------------------------------------
    */

    private function subscriptionsQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return Subscription::query()
            ->where(
                fn (Builder $query) =>
                    $this->applyOwnershipScope(
                        $query,
                        $user
                    )
            )

            /*
             * Search
             */
            ->when(
                trim($this->search) !== '',
                function (Builder $query) {
                    $term =
                        '%'
                        .trim(
                            $this->search
                        )
                        .'%';

                    $query->where(
                        function (
                            Builder $inner
                        ) use ($term) {
                            $inner
                                ->where(
                                    'status',
                                    'like',
                                    $term
                                )
                                ->orWhere(
                                    'payment_status',
                                    'like',
                                    $term
                                )
                                ->orWhereHas(
                                    'plan',
                                    fn (
                                        Builder $plan
                                    ) =>
                                        $plan->where(
                                            'name',
                                            'like',
                                            $term
                                        )
                                );
                        }
                    );
                }
            )

            /*
             * Status
             */
            ->when(
                $this->statusFilter
                    !== 'all',
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        $this->statusFilter
                    )
            )

            /*
             * Period
             */
            ->when(
                $this->periodFilter
                    !== 'all',
                function (
                    Builder $query
                ) {
                    match (
                        $this->periodFilter
                    ) {
                        'current' =>
                            $query
                                ->whereIn(
                                    'status',
                                    [
                                        'trial',
                                        'active',
                                        'grace_period',
                                    ]
                                )
                                ->where(
                                    function (
                                        Builder $date
                                    ) {
                                        $date
                                            ->whereNull(
                                                'end_date'
                                            )
                                            ->orWhere(
                                                'end_date',
                                                '>=',
                                                now()
                                            );
                                    }
                                ),

                        'ending_30' =>
                            $query
                                ->whereNotNull(
                                    'end_date'
                                )
                                ->whereBetween(
                                    'end_date',
                                    [
                                        now(),
                                        now()
                                            ->copy()
                                            ->addDays(30),
                                    ]
                                ),

                        'expired' =>
                            $query->where(
                                function (
                                    Builder $expired
                                ) {
                                    $expired
                                        ->where(
                                            'status',
                                            'expired'
                                        )
                                        ->orWhere(
                                            function (
                                                Builder $date
                                            ) {
                                                $date
                                                    ->whereNotNull(
                                                        'end_date'
                                                    )
                                                    ->where(
                                                        'end_date',
                                                        '<',
                                                        now()
                                                    );
                                            }
                                        );
                                }
                            ),

                        'this_year' =>
                            $query->whereYear(
                                'created_at',
                                now()->year
                            ),

                        default =>
                            null,
                    };
                }
            )

            ->with('plan')

            ->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | Plans Query
    |--------------------------------------------------------------------------
    */

    private function plansQuery(): Builder
    {
        return Plan::query()
            ->where(
                'is_active',
                true
            )
            ->where(
                'is_archived',
                false
            )

            ->when(
                trim(
                    $this->planSearch
                ) !== '',
                function (
                    Builder $query
                ) {
                    $term =
                        '%'
                        .trim(
                            $this->planSearch
                        )
                        .'%';

                    $query->where(
                        function (
                            Builder $inner
                        ) use ($term) {
                            $inner
                                ->where(
                                    'name',
                                    'like',
                                    $term
                                )
                                ->orWhere(
                                    'code',
                                    'like',
                                    $term
                                )
                                ->orWhere(
                                    'description',
                                    'like',
                                    $term
                                );
                        }
                    );
                }
            )

            ->when(
                $this->planPeriodFilter
                    !== 'all',
                function (
                    Builder $query
                ) {
                    match (
                        $this->planPeriodFilter
                    ) {
                        'monthly' =>
                            $query->where(
                                'type',
                                'monthly'
                            ),

                        'quarterly' =>
                            $query->where(
                                'type',
                                'three_month'
                            ),

                        'six_month' =>
                            $query->where(
                                'type',
                                'six_month'
                            ),

                        'annual' =>
                            $query->where(
                                'type',
                                'annual'
                            ),

                        'lifetime' =>
                            $query->where(
                                'type',
                                'lifetime'
                            ),

                        default =>
                            null,
                    };
                }
            )

            ->orderBy(
                'display_order'
            )

            ->orderBy(
                'price'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription Ownership
    |--------------------------------------------------------------------------
    */

    private function ownedSubscription(
        int $subscriptionId
    ): Subscription {
        /** @var User $user */
        $user = auth()->user();

        return Subscription::query()
            ->whereKey(
                $subscriptionId
            )
            ->where(
                fn (Builder $query) =>
                    $this->applyOwnershipScope(
                        $query,
                        $user
                    )
            )
            ->with('plan')
            ->firstOrFail();
    }

    private function applyOwnershipScope(
        Builder $query,
        User $user
    ): Builder {
        /*
         * Individual subscription ownership.
         */
        $query->where(
            'user_id',
            $user->id
        );

        /*
         * If subscriptions are shared at organisation level,
         * allow organisation-owned subscriptions too.
         */
        if (
            $user->organisation_id
        ) {
            $query->orWhere(
                'organisation_id',
                $user->organisation_id
            );
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Current Subscription
    |--------------------------------------------------------------------------
    */

    private function refreshCurrentSubscription(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->currentSubscription =
            app(
                SubscriptionService::class
            )->currentSubscription(
                $user,
                $user->organisation_id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Stats
    |--------------------------------------------------------------------------
    */

    private function subscriptionStats(
        User $user
    ): array {
        $query =
            fn () =>
                Subscription::query()
                    ->where(
                        fn (
                            Builder $owner
                        ) =>
                            $this
                                ->applyOwnershipScope(
                                    $owner,
                                    $user
                                )
                    );

        return [
            'total' =>
                $query()->count(),

            'active' =>
                $query()
                    ->whereIn(
                        'status',
                        [
                            'trial',
                            'active',
                            'grace_period',
                        ]
                    )
                    ->count(),

            'pending' =>
                $query()
                    ->where(
                        'status',
                        'pending'
                    )
                    ->count(),

            'plans' =>
                Plan::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->where(
                        'is_archived',
                        false
                    )
                    ->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        return view(
            'livewire.subscriptions.index',
            [
                'subscriptions' =>
                    $this
                        ->subscriptionsQuery()
                        ->paginate(
                            $this->perPage,
                            ['*'],
                            'subscriptionsPage'
                        ),

                'plans' =>
                    $this
                        ->plansQuery()
                        ->paginate(
                            $this->planPerPage,
                            ['*'],
                            'plansPage'
                        ),

                'subscription' =>
                    $this->currentSubscription,

                'stats' =>
                    $this
                        ->subscriptionStats(
                            $user
                        ),
            ]
        );
    }
}
