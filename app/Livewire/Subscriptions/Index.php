<?php

declare(strict_types=1);

namespace App\Livewire\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
    | Confirmation Modal
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
    | Allowed Values
    |--------------------------------------------------------------------------
    */

    private const PER_PAGE_OPTIONS = [
        10,
        25,
        50,
        100,
    ];

    private const SUBSCRIPTION_STATUSES = [
        'pending',
        'trial',
        'active',
        'past_due',
        'grace_period',
        'suspended',
        'expired',
        'cancelled',
    ];

    private const PLAN_PERIODS = [
        'monthly',
        'quarterly',
        'six_month',
        'annual',
        'lifetime',
    ];

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

        $this->resetPage(
            'subscriptionsPage'
        );
    }

    public function showPlans(): void
    {
        $this->activeTab = 'plans';

        $this->resetValidation();

        $this->resetPage(
            'plansPage'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription Filter Updates
    |--------------------------------------------------------------------------
    */

    public function updatedSearch(): void
    {
        $this->resetSubscriptionPage();
    }

    public function updatedStatusFilter(
        string $value
    ): void {
        if (
            $value !== 'all'
            && ! in_array(
                $value,
                self::SUBSCRIPTION_STATUSES,
                true
            )
        ) {
            $this->statusFilter = 'all';
        }

        $this->resetSubscriptionPage();
    }

    public function updatedPeriodFilter(
        string $value
    ): void {
        $allowed = [
            'all',
            'current',
            'ending_30',
            'expired',
            'this_year',
        ];

        if (
            ! in_array(
                $value,
                $allowed,
                true
            )
        ) {
            $this->periodFilter = 'all';
        }

        $this->resetSubscriptionPage();
    }

    public function updatedPerPage(
        mixed $value
    ): void {
        $value = (int) $value;

        $this->perPage =
            in_array(
                $value,
                self::PER_PAGE_OPTIONS,
                true
            )
                ? $value
                : 10;

        $this->resetSubscriptionPage();
    }

    /*
    |--------------------------------------------------------------------------
    | Plan Filter Updates
    |--------------------------------------------------------------------------
    */

    public function updatedPlanSearch(): void
    {
        $this->resetPage(
            'plansPage'
        );
    }

    public function updatedPlanPeriodFilter(
        string $value
    ): void {
        $allowed = array_merge(
            ['all'],
            self::PLAN_PERIODS
        );

        if (
            ! in_array(
                $value,
                $allowed,
                true
            )
        ) {
            $this->planPeriodFilter = 'all';
        }

        $this->resetPage(
            'plansPage'
        );
    }

    public function updatedPlanPerPage(
        mixed $value
    ): void {
        $value = (int) $value;

        $this->planPerPage =
            in_array(
                $value,
                self::PER_PAGE_OPTIONS,
                true
            )
                ? $value
                : 10;

        $this->resetPage(
            'plansPage'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination / Selection
    |--------------------------------------------------------------------------
    */

    private function resetSubscriptionPage(): void
    {
        $this->resetPage(
            'subscriptionsPage'
        );

        $this->clearSelection();
    }

    public function clearSelection(): void
    {
        $this->selectedSubscriptions = [];

        $this->selectPage = false;
    }

    public function updatedSelectPage(
        bool $value
    ): void {
        if (! $value) {
            $this->selectedSubscriptions = [];

            return;
        }

        /*
         * Select only records visible on the current page.
         */
        $currentPage =
            $this->getPage(
                'subscriptionsPage'
            );

        $this->selectedSubscriptions =
            $this
                ->subscriptionsQuery()
                ->forPage(
                    $currentPage,
                    $this->perPage
                )
                ->pluck('subscriptions.id')
                ->map(
                    fn ($id) =>
                        (string) $id
                )
                ->values()
                ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm Subscription
    |--------------------------------------------------------------------------
    */

    public function confirmSubscribe(
        int $planId
    ): void {
        $plan =
            Plan::query()
                ->whereKey(
                    $planId
                )
                ->where(
                    'is_active',
                    true
                )
                ->where(
                    'is_archived',
                    false
                )
                ->firstOrFail();

        $this->actionType =
            'subscribe';

        $this->actionPlanId =
            $plan->id;

        $this->actionSubscriptionId =
            null;

        $this->actionTitle =
            'Choose '
            .$plan->name
            .'?';

        $this->actionMessage =
            'A pending subscription will be created. '
            .'Complete payment to activate access.';

        $this->showActionModal =
            true;
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm Cancellation
    |--------------------------------------------------------------------------
    */

    public function confirmCancel(
        int $subscriptionId
    ): void {
        $subscription =
            $this->ownedSubscription(
                $subscriptionId
            );

        $this->actionType =
            'cancel';

        $this->actionSubscriptionId =
            $subscription->id;

        $this->actionPlanId =
            null;

        $this->actionTitle =
            'Cancel subscription?';

        $this->actionMessage =
            'Cancel '
            .(
                $subscription
                    ->plan
                    ?->name
                ?? 'this subscription'
            )
            .'? Your projects and BOQ data will be preserved.';

        $this->showActionModal =
            true;
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

        $this->actionType =
            'bulk_cancel';

        $this->actionPlanId =
            null;

        $this->actionSubscriptionId =
            null;

        $this->actionTitle =
            'Cancel selected subscriptions?';

        $this->actionMessage =
            count(
                $this->selectedSubscriptions
            )
            .' selected subscription(s) will be cancelled. '
            .'Project and BOQ data will remain available.';

        $this->showActionModal =
            true;
    }

    /*
    |--------------------------------------------------------------------------
    | Close Modal
    |--------------------------------------------------------------------------
    */

    public function closeActionModal(): void
    {
        $this->showActionModal =
            false;

        $this->actionType =
            '';

        $this->actionSubscriptionId =
            null;

        $this->actionPlanId =
            null;

        $this->actionTitle =
            '';

        $this->actionMessage =
            '';
    }

    /*
    |--------------------------------------------------------------------------
    | Perform Confirmed Action
    |--------------------------------------------------------------------------
    */

    public function performAction(): void
    {
        $action =
            $this->actionType;

        try {
            match ($action) {
                'subscribe' =>
                    $this->subscribeToPlan(),

                'cancel' =>
                    $this->cancelSubscription(),

                'bulk_cancel' =>
                    $this->bulkCancel(),

                default =>
                    null,
            };
        } finally {
            $this->closeActionModal();

            $this->refreshCurrentSubscription();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Subscribe
    |--------------------------------------------------------------------------
    */

    private function subscribeToPlan(): void
    {
        if (
            ! $this->actionPlanId
        ) {
            return;
        }

        $plan =
            Plan::query()
                ->whereKey(
                    $this->actionPlanId
                )
                ->where(
                    'is_active',
                    true
                )
                ->where(
                    'is_archived',
                    false
                )
                ->firstOrFail();

        /** @var User $user */
        $user =
            auth()->user();

        /*
         * Avoid creating multiple pending subscriptions
         * for the same user/organisation and same plan.
         */
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
                    fn (
                        Builder $query
                    ) =>
                        $this
                            ->applyOwnershipScope(
                                $query,
                                $user
                            )
                )
                ->latest()
                ->first();

        if (
            $existingPending
        ) {
            session()->flash(
                'message',
                'You already have a pending '
                .$plan->name
                .' subscription.'
            );

            $this->showSubscriptions();

            return;
        }

        DB::transaction(
            function () use (
                $user,
                $plan
            ): void {
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
            }
        );

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
    | Cancel Single Subscription
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
                $this
                    ->actionSubscriptionId
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

        DB::transaction(
            function () use (
                $subscription
            ): void {
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
        );

        session()->flash(
            'message',
            'Subscription cancelled successfully. '
            .'Your projects and BOQ data were preserved.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Cancellation
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
        $user =
            auth()->user();

        $ids =
            collect(
                $this
                    ->selectedSubscriptions
            )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->filter(
                    fn ($id) =>
                        $id > 0
                )
                ->unique()
                ->values()
                ->all();

        if (
            empty($ids)
        ) {
            $this->clearSelection();

            return;
        }

        $subscriptions =
            Subscription::query()
                ->whereIn(
                    'id',
                    $ids
                )
                ->where(
                    fn (
                        Builder $query
                    ) =>
                        $this
                            ->applyOwnershipScope(
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

        DB::transaction(
            function () use (
                $subscriptions
            ): void {
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
            }
        );

        $count =
            $subscriptions
                ->count();

        $this->clearSelection();

        $this->refreshCurrentSubscription();

        session()->flash(
            'message',
            $count
            .' subscription(s) cancelled successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription Query
    |--------------------------------------------------------------------------
    */

    private function subscriptionsQuery(): Builder
    {
        /** @var User $user */
        $user =
            auth()->user();

        return Subscription::query()

            /*
             * Ownership / organisation isolation.
             */
            ->where(
                fn (
                    Builder $query
                ) =>
                    $this
                        ->applyOwnershipScope(
                            $query,
                            $user
                        )
            )

            /*
             * Search.
             */
            ->when(
                trim(
                    $this->search
                ) !== '',
                function (
                    Builder $query
                ): void {
                    $term =
                        '%'
                        .trim(
                            $this->search
                        )
                        .'%';

                    $query->where(
                        function (
                            Builder $inner
                        ) use (
                            $term
                        ): void {
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
                                        $plan
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
                                );
                        }
                    );
                }
            )

            /*
             * Status.
             */
            ->when(
                $this->statusFilter
                    !== 'all',
                fn (
                    Builder $query
                ) =>
                    $query->where(
                        'status',
                        $this
                            ->statusFilter
                    )
            )

            /*
             * Period.
             */
            ->when(
                $this->periodFilter
                    !== 'all',
                function (
                    Builder $query
                ): void {
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
                                        Builder $dates
                                    ): void {
                                        $dates
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
                                ): void {
                                    $expired
                                        ->where(
                                            'status',
                                            'expired'
                                        )
                                        ->orWhere(
                                            function (
                                                Builder $dates
                                            ): void {
                                                $dates
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

            ->with([
                'plan',
            ])

            ->latest(
                'subscriptions.created_at'
            );
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

            /*
             * Blade displays plan features, so eager-load them.
             */
            ->with([
                'features',
            ])

            /*
             * Search.
             */
            ->when(
                trim(
                    $this->planSearch
                ) !== '',
                function (
                    Builder $query
                ): void {
                    $term =
                        '%'
                        .trim(
                            $this
                                ->planSearch
                        )
                        .'%';

                    $query->where(
                        function (
                            Builder $inner
                        ) use (
                            $term
                        ): void {
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

            /*
             * Billing period.
             */
            ->when(
                $this
                    ->planPeriodFilter
                    !== 'all',
                function (
                    Builder $query
                ): void {
                    $type =
                        match (
                            $this
                                ->planPeriodFilter
                        ) {
                            'monthly' =>
                                'monthly',

                            'quarterly' =>
                                'three_month',

                            'six_month' =>
                                'six_month',

                            'annual' =>
                                'annual',

                            'lifetime' =>
                                'lifetime',

                            default =>
                                null,
                        };

                    if (
                        $type !== null
                    ) {
                        $query->where(
                            'type',
                            $type
                        );
                    }
                }
            )

            ->orderBy(
                'display_order'
            )
            ->orderBy(
                'price'
            )
            ->orderBy(
                'id'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Ownership
    |--------------------------------------------------------------------------
    */

    private function ownedSubscription(
        int $subscriptionId
    ): Subscription {
        /** @var User $user */
        $user =
            auth()->user();

        return Subscription::query()
            ->whereKey(
                $subscriptionId
            )
            ->where(
                fn (
                    Builder $query
                ) =>
                    $this
                        ->applyOwnershipScope(
                            $query,
                            $user
                        )
            )
            ->with([
                'plan',
            ])
            ->firstOrFail();
    }

    /**
     * Apply subscription ownership safely.
     *
     * Important:
     * Never use:
     *
     *     orWhere('organisation_id', null)
     *
     * because that could expose subscriptions belonging to other
     * personal users who also have a null organisation_id.
     */
    private function applyOwnershipScope(
        Builder $query,
        User $user
    ): Builder {
        $query->where(
            'user_id',
            $user->id
        );

        if (
            $user
                ->organisation_id
            !== null
        ) {
            $query->orWhere(
                'organisation_id',
                $user
                    ->organisation_id
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
        $user =
            auth()->user();

        $this->currentSubscription =
            app(
                SubscriptionService::class
            )->currentSubscription(
                $user,
                $user
                    ->organisation_id
            );

        if (
            $this->currentSubscription
        ) {
            $this
                ->currentSubscription
                ->loadMissing(
                    'plan'
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    private function subscriptionStats(
        User $user
    ): array {
        $ownedQuery =
            fn (): Builder =>
                Subscription::query()
                    ->where(
                        fn (
                            Builder $query
                        ) =>
                            $this
                                ->applyOwnershipScope(
                                    $query,
                                    $user
                                )
                    );

        return [
            'total' =>
                $ownedQuery()
                    ->count(),

            'active' =>
                $ownedQuery()
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
                            Builder $query
                        ): void {
                            $query
                                ->whereNull(
                                    'end_date'
                                )
                                ->orWhere(
                                    'end_date',
                                    '>=',
                                    now()
                                );
                        }
                    )
                    ->count(),

            'pending' =>
                $ownedQuery()
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
        $user =
            auth()->user();

        /*
         * Refreshing here ensures the header/current-plan indicator
         * is correct after external payment/subscription changes.
         */
        $this->refreshCurrentSubscription();

        $subscriptions =
            $this
                ->subscriptionsQuery()
                ->paginate(
                    $this->perPage,
                    ['*'],
                    'subscriptionsPage'
                );

        $plans =
            $this
                ->plansQuery()
                ->paginate(
                    $this->planPerPage,
                    ['*'],
                    'plansPage'
                );

        return view(
            'livewire.subscriptions.index',
            [
                /*
                 * Paginated subscription history.
                 */
                'subscriptions' =>
                    $subscriptions,

                /*
                 * Available plans.
                 */
                'plans' =>
                    $plans,

                /*
                 * Keep this alias because the existing Blade uses
                 * $subscription in its header and Current Plan logic.
                 */
                'subscription' =>
                    $this
                        ->currentSubscription,

                /*
                 * Statistics cards.
                 */
                'stats' =>
                    $this
                        ->subscriptionStats(
                            $user
                        ),
            ]
        );
    }
}