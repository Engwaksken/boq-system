<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $notifications = UserNotification::query()
            ->where('user_id', $request->user()->id)
            // Rows whose in-app channel is explicitly disabled are audit-only (for
            // example an email-only price alert) and must not surface here.
            ->where(function ($query) {
                $query->whereNull('data->in_app_enabled')
                    ->orWhere('data->in_app_enabled', true);
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'data' => $notification->fresh(),
        ]);
    }
}
