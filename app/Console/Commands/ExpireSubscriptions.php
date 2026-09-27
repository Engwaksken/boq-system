<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\UserNotification;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Move ended subscriptions into their grace period, then expire them (BOQ data is never deleted)';

    public function handle(): int
    {
        $graced = 0;
        $expired = 0;

        // Ended, but the plan's grace period is still running: keep access, warn the user.
        Subscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->whereNotNull('grace_period_end_date')
            ->where('grace_period_end_date', '>', now())
            ->chunkById(100, function ($subscriptions) use (&$graced) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'grace_period']);
                    $this->notify($subscription, 'Subscription in grace period',
                        'Your subscription has ended. Renew before '.$subscription->grace_period_end_date->toDateString().' to keep access.');
                    $graced++;
                }
            });

        // Past the end date (and past the grace period, if any): expire.
        Subscription::query()
            ->whereIn('status', ['active', 'trial', 'grace_period', 'past_due'])
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->where(fn ($query) => $query
                ->whereNull('grace_period_end_date')
                ->orWhere('grace_period_end_date', '<=', now()))
            ->chunkById(100, function ($subscriptions) use (&$expired) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'expired']);
                    $subscription->entitlements()->where('is_permanent', false)->update(['status' => 'expired']);
                    $this->notify($subscription, 'Subscription expired',
                        'Your subscription has expired. Your projects and BOQs are safe; choose a plan to continue using paid features.');
                    $expired++;
                }
            });

        $this->info("Moved {$graced} subscription(s) into grace period; expired {$expired}.");

        return self::SUCCESS;
    }

    private function notify(Subscription $subscription, string $title, string $message): void
    {
        if (! $subscription->user_id) {
            return;
        }

        UserNotification::create([
            'user_id' => $subscription->user_id,
            'type' => 'subscription',
            'title' => $title,
            'message' => $message,
            'data' => ['subscription_id' => $subscription->id, 'status' => $subscription->status],
        ]);
    }
}
