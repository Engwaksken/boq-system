<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire ended subscriptions without deleting user BOQ data';

    public function handle(): int
    {
        $count = 0;
        Subscription::query()
            ->whereIn('status', ['active', 'trial', 'grace_period', 'past_due'])
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'expired']);
                    $subscription->entitlements()->where('is_permanent', false)->update(['status' => 'expired']);
                    $count++;
                }
            });
        $this->info("Expired {$count} subscription(s).");
        return self::SUCCESS;
    }
}
