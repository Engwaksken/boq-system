<?php

namespace App\Livewire\Shell;

use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Livewire\Component;
use Throwable;

/**
 * Top-bar notification bell: the signed-in user's latest in-app notifications
 * (subscription reminders, payment results, etc.) with an unread badge.
 */
class NotificationsMenu extends Component
{
    public int $limit = 8;

    public function markAsRead(int $id): void
    {
        UserNotification::query()
            ->where('user_id', auth()->id())
            ->whereKey($id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        UserNotification::query()
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function render()
    {
        [$notifications, $unread] = $this->load();

        return view('livewire.shell.notifications-menu', [
            'notifications' => $notifications,
            'unread' => $unread,
        ]);
    }

    /** @return array{0: Collection<int, UserNotification>, 1: int} */
    private function load(): array
    {
        $userId = auth()->id();

        if (! $userId) {
            return [collect(), 0];
        }

        try {
            $query = UserNotification::query()->where('user_id', $userId);

            return [
                (clone $query)->latest()->limit(max(1, min($this->limit, 20)))->get(['id', 'type', 'title', 'message', 'read_at', 'created_at']),
                (clone $query)->whereNull('read_at')->count(),
            ];
        } catch (Throwable $e) {
            // The bell must never break the page (e.g. a missing table during an upgrade).
            report($e);

            return [collect(), 0];
        }
    }
}
