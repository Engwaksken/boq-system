<?php

namespace App\Console\Commands;

use App\Models\NotificationDigestItem;
use App\Models\User;
use App\Notifications\ActivityDigest;
use Illuminate\Console\Command;

class SendNotificationDigests extends Command
{
    protected $signature = 'notifications:digest {frequency : hourly, daily or weekly}';

    protected $description = 'Email each user the project updates held back for their chosen digest';

    public function handle(): int
    {
        $frequency = (string) $this->argument('frequency');

        if (! in_array($frequency, ['hourly', 'daily', 'weekly'], true)) {
            $this->error('The frequency must be hourly, daily or weekly.');

            return self::FAILURE;
        }

        $sent = 0;

        NotificationDigestItem::query()
            ->where('frequency', $frequency)
            ->whereNull('sent_at')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->each(function ($items, $userId) use ($frequency, &$sent) {
                $user = User::find($userId);
                $ids = $items->pluck('id')->all();

                if ($user && $user->email_verified_at !== null) {
                    $user->notify(new ActivityDigest($frequency, $items->map(fn ($item) => [
                        'title' => $item->title,
                        'message' => $item->message,
                        'url' => $item->url,
                    ])->all()));
                    $sent++;
                }

                // Mark as done even when the account can no longer receive mail, so it is not retried forever.
                NotificationDigestItem::whereIn('id', $ids)->update(['sent_at' => now()]);
            });

        $this->info("Sent {$sent} {$frequency} digest email(s).");

        return self::SUCCESS;
    }
}
