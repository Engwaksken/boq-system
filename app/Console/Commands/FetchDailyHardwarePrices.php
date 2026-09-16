<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Services\HardwarePriceFetchingService;
use Illuminate\Console\Command;
use Throwable;

class FetchDailyHardwarePrices extends Command
{
    protected $signature = 'hardware:fetch-daily
                            {--organisation= : Process only one organisation ID}
                            {--location=Kampala : Uganda market location}
                            {--limit=3 : Number of items to fetch per category}';

    protected $description = 'Fetch and store daily hardware prices using the configured AI provider';

    public function handle(HardwarePriceFetchingService $service): int
    {
        $location = trim((string) $this->option('location')) ?: 'Kampala';
        $limit = max(1, min(20, (int) $this->option('limit')));

        $organisationOption = $this->option('organisation');

        $organisationIds = filled($organisationOption)
            ? collect([(int) $organisationOption])
            : Organisation::query()->orderBy('id')->pluck('id');

        if ($organisationIds->isEmpty()) {
            $this->error('No organisation exists. Daily hardware fetch cannot run.');

            return self::FAILURE;
        }

        $totalFetched = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalErrors = 0;

        foreach ($organisationIds as $organisationId) {
            try {
                $results = $service->fetchDailyPrices(
                    organisationId: (int) $organisationId,
                    location: $location,
                    limitPerCategory: $limit
                );

                $totalFetched += $results['fetched'];
                $totalCreated += $results['created'];
                $totalUpdated += $results['updated'];
                $totalErrors += count($results['errors']);

                $this->info(
                    "Organisation {$organisationId}: ".
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
                    "Organisation {$organisationId}: {$exception->getMessage()}"
                );
            }
        }

        $this->newLine();
        $this->line(
            "Total: {$totalFetched} fetched, {$totalCreated} created, ".
            "{$totalUpdated} updated, {$totalErrors} errors."
        );

        return $totalFetched > 0 ? self::SUCCESS : self::FAILURE;
    }
}
