<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\SiteSetting;
use App\Services\HardwarePriceFetchingService;
use Illuminate\Console\Command;
use Throwable;

class FetchDailyHardwarePrices extends Command
{
    protected $signature = 'hardware:fetch-daily
                            {--organisation= : Process only one organisation ID}
                            {--location= : Market location (defaults to the Settings market location)}
                            {--limit=3 : Number of items to fetch per category}';

    protected $description = 'Fetch and store daily hardware prices using the configured AI provider';

    public function handle(HardwarePriceFetchingService $service): int
    {
        SiteSetting::set('hardware_auto_scan_last_started_at', now()->toIso8601String(), 'hardware_scanner');
        SiteSetting::set('hardware_auto_scan_last_status', 'running', 'hardware_scanner');

        $location = trim((string) $this->option('location')) ?: \App\Support\Regional::marketLocation();
        $limit = max(1, min(20, (int) $this->option('limit')));

        $organisationOption = $this->option('organisation');

        // By default the general market prices (shared by everyone) are refreshed
        // once, instead of spending AI tokens on the same prices per organisation.
        $organisationIds = filled($organisationOption)
            ? collect([(int) $organisationOption])
            : collect([null]);

        $totalFetched = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalErrors = 0;

        foreach ($organisationIds as $organisationId) {
            try {
                $results = $service->fetchDailyPrices(
                    organisationId: $organisationId === null ? null : (int) $organisationId,
                    location: $location,
                    limit: $limit,
                    // Refresh only items that already have a price; never spend AI
                    // credits discovering prices for unpriced items on a schedule.
                    onlyPreviouslyPriced: true
                );

                $totalFetched += $results['fetched'];
                $totalCreated += $results['created'];
                $totalUpdated += $results['updated'];
                $totalErrors += count($results['errors']);

                $this->info(
                    ($organisationId === null ? 'General prices: ' : "Organisation {$organisationId}: ").
                    "{$results['fetched']} fetched, ".
                    "{$results['created']} created, ".
                    "{$results['updated']} updated, ".
                    count($results['errors']).' category errors.'
                );

                foreach ($results['errors'] as $error) {
                    $this->warn($error);
                }
            } catch (Throwable $exception) {
                $totalErrors++;

                report($exception);

                $this->error(
                    ($organisationId === null ? 'General prices' : "Organisation {$organisationId}").": {$exception->getMessage()}"
                );
            }
        }

        $this->newLine();
        $this->line(
            "Total: {$totalFetched} fetched, {$totalCreated} created, ".
            "{$totalUpdated} updated, {$totalErrors} errors."
        );

        $status = match (true) {
            $totalErrors > 0 && $totalFetched === 0 => 'failed',
            $totalErrors > 0 => 'partial',
            default => 'successful',
        };

        SiteSetting::set('hardware_auto_scan_last_run_at', now()->toIso8601String(), 'hardware_scanner');
        SiteSetting::set('hardware_auto_scan_last_status', $status, 'hardware_scanner');
        SiteSetting::set(
            'hardware_auto_scan_last_summary',
            "{$totalFetched} fetched, {$totalCreated} created, {$totalUpdated} updated, {$totalErrors} errors.",
            'hardware_scanner'
        );

        return $totalFetched > 0 ? self::SUCCESS : self::FAILURE;
    }
}
