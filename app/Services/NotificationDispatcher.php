<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use App\Models\NotificationDigestItem;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\ActivityNotice;
use Throwable;

/**
 * Delivers one project event to a user according to their preferences
 * (Profile > Preferences > Notifications): an in-app notification and/or an
 * email, sent now or held for the hourly, daily or weekly digest they chose.
 */
class NotificationDispatcher
{
    /**
     * @param  ?string  $dedupeKey  when given, the user receives this event at most once
     * @return bool  whether anything was delivered (false when skipped as a duplicate or unwanted)
     */
    public function notify(
        User $user,
        string $category,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?string $url = null,
        ?string $dedupeKey = null,
    ): bool {
        if ($dedupeKey !== null) {
            // createOrFirst leans on the unique index, so overlapping runs cannot both deliver.
            $delivery = NotificationDelivery::createOrFirst(
                ['user_id' => $user->id, 'dedupe_key' => $dedupeKey],
                ['sent_at' => now()],
            );

            if (! $delivery->wasRecentlyCreated) {
                return false;
            }
        }

        $inApp = $user->wantsNotification('in_app', $category);
        $email = $user->wantsNotification('email', $category) && $user->email_verified_at !== null;

        if (! $inApp && ! $email) {
            return false;
        }

        if ($inApp) {
            UserNotification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);
        }

        if ($email) {
            $frequency = (string) ($user->notificationPreferences()['frequency'] ?? 'immediate');

            if ($frequency === 'immediate') {
                $this->sendNow($user, $title, $message, $url);
            } else {
                NotificationDigestItem::create([
                    'user_id' => $user->id,
                    'frequency' => $frequency,
                    'category' => $category,
                    'title' => $title,
                    'message' => $message,
                    'url' => $url,
                ]);
            }
        }

        return true;
    }

    private function sendNow(User $user, string $title, string $message, ?string $url): void
    {
        try {
            $user->notify(new ActivityNotice($title, $message, $url));
        } catch (Throwable $exception) {
            // A mail failure must never undo the event that triggered it (e.g. saving an expense).
            report($exception);
        }
    }
}
