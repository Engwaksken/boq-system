<?php

namespace App\Console\Commands;

use App\Models\AiProvider;
use App\Services\AiUsageMonitor;
use Illuminate\Console\Command;

/**
 * Daily check of every enabled AI provider: refreshes balances where the
 * provider reports them and notifies admins before credit runs out or expires.
 */
class CheckAiProviderCredit extends Command
{
    protected $signature = 'ai:check-credit';

    protected $description = 'Refresh AI provider balances and warn admins about low or expiring credit';

    public function handle(AiUsageMonitor $monitor): int
    {
        $providers = AiProvider::query()->enabled()->get();

        foreach ($providers as $provider) {
            $monitor->refreshBalance($provider);
            $provider->refresh();
            $status = $monitor->status($provider);
            $monitor->warnIfLow($provider);

            $this->line(sprintf('%s: %s%s', $provider->name, $status['state'], $status['reasons'] ? ' ('.implode(' ', $status['reasons']).')' : ''));
        }

        return self::SUCCESS;
    }
}
