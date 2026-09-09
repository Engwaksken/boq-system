<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * List all active plans.
     */
    public function index(Request $request): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with('features')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Show a single plan.
     */
    public function show(Request $request, Plan $plan): JsonResponse
    {
        if (! $plan->is_active || $plan->is_archived) {
            return response()->json([
                'success' => false,
                'error_code' => 'PLAN_NOT_FOUND',
                'message' => __('subscriptions.plan_not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $plan->load('features'),
        ]);
    }
}
