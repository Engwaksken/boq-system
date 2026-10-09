<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boq;
use App\Models\Project;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show the dashboard summary for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptionService = app(SubscriptionService::class);

        // Members only see projects (and their BOQs) they are assigned to;
        // administrators see the whole organisation (Project::accessibleTo).
        $projectQuery = Project::query()->accessibleTo($user);

        $boqQuery = Boq::query()
            ->whereHas('project', fn ($project) => $project->accessibleTo($user));

        $currentSubscription = cache()->remember('dashboard_subscription_' . $user->id, 30, function () use ($subscriptionService, $user) {
            return $subscriptionService->currentSubscription($user, $user->organisation_id);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'total_projects' => (clone $projectQuery)->count(),
                'active_projects' => (clone $projectQuery)->where('status', 'active')->count(),
                'completed_projects' => (clone $projectQuery)->where('status', 'completed')->count(),
                'total_boqs' => (clone $boqQuery)->count(),
                'boqs_awaiting_review' => (clone $boqQuery)->where('status', 'under_review')->count(),
                'boqs_analysed' => (clone $boqQuery)->where('status', 'analysed')->count(),
                'total_estimated_value' => (clone $projectQuery)->sum('contract_value'),
                'recent_projects' => (clone $projectQuery)->withCount('boqs')->latest()->limit(5)->get(['id', 'name', 'code', 'status']),
                'current_subscription' => $currentSubscription ? [
                    'plan' => $currentSubscription->plan?->name,
                    'status' => $currentSubscription->status,
                    'expires_at' => $currentSubscription->end_date,
                ] : null,
            ],
        ]);
    }
}
