<?php

namespace App\Jobs;

use App\Services\HardwarePriceFetchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchDailyHardwarePrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];
    public $timeout = 1800;

    public function __construct(
        private ?int $organisationId = null
    ) {}

    public function handle(HardwarePriceFetchingService $fetchingService): void
    {
        Log::info('Starting daily hardware price fetch job');

        try {
            $results = $fetchingService->fetchDailyPrices();
            
            Log::info('Daily hardware price fetch completed', $results);
            
            if (!empty($results['errors'])) {
                Log::warning('Price fetch completed with errors', ['errors' => $results['errors']]);
            }
        } catch (\Exception $e) {
            Log::error('Daily hardware price fetch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Daily hardware price fetch job failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}