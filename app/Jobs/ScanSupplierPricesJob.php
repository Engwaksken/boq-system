<?php

namespace App\Jobs;

use App\Services\SupplierScanQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Scans one supplier's website for prices (queued from a location or bulk scan). */
class ScanSupplierPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Below the queue's retry_after (90 s) so a slow scan is never run twice. */
    public int $timeout = 85;

    public int $tries = 1;

    public function __construct(public int $supplierId) {}

    public function handle(SupplierScanQueue $scans): void
    {
        $scans->run($this->supplierId);
    }
}
