<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\UserNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemindExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:remind {--days=14,7,3,1 : Comma-separated days before the end date to remind}';

    protected $description = 'Send in-app reminders before subscriptions end (once per subscription per reminder day)';

    public function handle(): int
    {
        $sent = 0;
        $days = collect(explode(',', (string) $this->option('days')))
            ->map(fn ($day) => (int) trim($day))
            ->filter(fn ($day) => $day > 0)
            ->unique();

        foreach ($days as $day) {
            Subscription::query()
                ->with('plan')
                ->whereIn('status', ['active', 'trial'])
                ->whereNotNull('user_id')
                ->whereBetween('end_date', [now()->addDays($day)->startOfDay(), now()->addDays($day)->endOfDay()])
                ->chunkById(100, function ($subscriptions) use ($day, &$sent) {
                    foreach ($subscriptions as $subscription) {
                        $key = "renewal-reminder-{$subscription->id}-{$day}";

                        $created = DB::transaction(function () use ($subscription, $day, $key): bool {
                            // A per-user row lock serializes duplicate scheduler runs.
                            \App\Models\User::query()->whereKey($subscription->user_id)->lockForUpdate()->first();
                            if (UserNotification::where('user_id', $subscription->user_id)->where('data->reminder_key', $key)->exists()) {
                                return false;
                            }

                            UserNotification::create([
                                'user_id' => $subscription->user_id,
                                'type' => 'subscription',
                                'title' => $day === 1 ? 'Subscription ends tomorrow' : "Subscription ends in {$day} days",
                                'message' => sprintf(
                                    'Your %s plan ends on %s. Renew from Subscriptions to avoid interruption.',
                                    $subscription->plan?->name ?? 'current',
                                    $subscription->end_date->toDateString()
                                ),
                                'data' => ['subscription_id' => $subscription->id, 'reminder_key' => $key],
                            ]);

                            return true;
                        });

                        if ($created) {
                            $sent++;
                        }
                    }
                });
        }

        $this->info("Sent {$sent} renewal reminder(s).");

        return self::SUCCESS;
    }
}
