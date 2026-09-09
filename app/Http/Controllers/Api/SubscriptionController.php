<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * List subscriptions for the authenticated user/organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscriptions = Subscription::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('organisation_id', $user->organisation_id);
            })
            ->with('plan')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    /**
     * Get the current active subscription.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $service = app(SubscriptionService::class);

        $subscription = $service->currentSubscription($user, $user->organisation_id);

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'error_code' => 'NO_ACTIVE_SUBSCRIPTION',
                'message' => __('subscriptions.no_active_subscription'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $subscription->load('plan', 'entitlements.feature'),
        ]);
    }

    /**
     * Create a new subscription (pending payment).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::find($validated['plan_id']);

        if (! $plan || ! $plan->is_active || $plan->is_archived) {
            return response()->json([
                'success' => false,
                'error_code' => 'PLAN_NOT_AVAILABLE',
                'message' => __('subscriptions.plan_not_available'),
            ], 422);
        }

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
            'data' => $subscription->load('plan'),
        ], 201);
    }

    /**
     * Show a single subscription.
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        $user = $request->user();

        if ($subscription->user_id !== $user->id && $subscription->organisation_id !== $user->organisation_id) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => __('auth.forbidden'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $subscription->load('plan', 'entitlements.feature', 'transactions'),
        ]);
    }
}
