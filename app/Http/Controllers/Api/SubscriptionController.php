<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Http\Resources\SubscriptionResource;
use App\Http\Requests\StoreSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * List subscriptions for the authenticated user/organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);
        $user = $request->user();

        $subscriptions = Subscription::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                $q->orWhere('payer_id', $user->id)
                    ->orWhere('beneficiary_id', $user->id);
            })
            ->with('plan')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => SubscriptionResource::collection($subscriptions),
        ]);
    }

    /**
     * Get the current active subscription.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', Subscription::class);
        $subscription = Subscription::query()
            ->whereIn('status', ['active', 'trial', 'grace_period'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('beneficiary_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('payer_id', $user->id)
                            ->whereColumn('payer_id', '!=', 'user_id');
                    });
            })
            ->latest()->first();

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'error_code' => 'NO_ACTIVE_SUBSCRIPTION',
                'message' => __('subscriptions.no_active_subscription'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription->load('plan', 'entitlements.feature')),
        ]);
    }

    /**
     * Create a new subscription (pending payment).
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $plan = Plan::find($validated['plan_id']);

        if (! $plan || ! $plan->is_active || $plan->is_archived) {
            return response()->json([
                'success' => false,
                'error_code' => 'PLAN_NOT_AVAILABLE',
                'message' => __('subscriptions.plan_not_available'),
            ], 422);
        }

        $this->authorize('create', $plan);

        $user = $request->user();

        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'auto_renewal' => $plan->auto_renewal,
        ]);

        // These fields are intentionally not mass-assignable; set them explicitly.
        $subscription->user_id = $user->id;
        $subscription->organisation_id = $user->organisation_id;
        $subscription->status = 'pending';
        $subscription->access_type = $plan->type;
        $subscription->payment_status = 'pending';

        $subscription->save();

        return response()->json([
            'success' => true,
            'message' => __('subscriptions.created'),
            'data' => new SubscriptionResource($subscription->load('plan')),
        ], 201);
    }

    /**
     * Show a single subscription.
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription->load('plan', 'entitlements.feature', 'transactions')),
        ]);
    }
}
