<?php

namespace App\Services;

use App\Exceptions\AiCreditExhaustedException;
use App\Jobs\ScanSupplierPricesJob;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Website price scans for many suppliers at once (by location or selection).
 *
 * Each supplier is marked "queued" in its metadata and scanned by a queue job.
 * When no queue worker runs, the Suppliers page picks up waiting scans itself
 * (runNextStalled), so the scans still finish while the admin keeps it open.
 */
class SupplierScanQueue
{
    /** Seconds a queued scan may wait for the worker before the page runs it. */
    public const STALL_SECONDS = 45;

    public function __construct(private HardwarePriceFetchingService $prices) {}

    /** Suppliers whose location, region or address mentions the location. */
    public static function inLocation(Builder $query, string $location): Builder
    {
        $location = trim($location);

        return $location === '' ? $query : $query->where(fn ($q) => $q
            ->where('location', 'like', "%{$location}%")
            ->orWhere('region', 'like', "%{$location}%")
            ->orWhere('address', 'like', "%{$location}%"));
    }

    /** Distinct supplier locations (city/district and region) for the pickers. */
    public static function locations(): array
    {
        return Supplier::query()->whereNotNull('location')->where('location', '!=', '')->distinct()->pluck('location')
            ->merge(Supplier::query()->whereNotNull('region')->where('region', '!=', '')->distinct()->pluck('region'))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** Active suppliers with a website that a location scan would cover. */
    public static function scannable(string $location = '', string $type = ''): Builder
    {
        return self::inLocation(Supplier::query(), $location)
            ->where('is_active', true)
            ->whereNotNull('website_url')->where('website_url', '!=', '')
            ->when(in_array($type, [Supplier::TYPE_SUPPLIER, Supplier::TYPE_FACTORY], true), fn ($q) => $q->where('type', $type));
    }

    /**
     * Queue a scan for each supplier (those without a website are skipped).
     *
     * @param  iterable<Supplier>  $suppliers
     * @return int suppliers queued
     */
    public function queue(iterable $suppliers, ?int $organisationId, int $limit = 15): int
    {
        $limit = max(1, min(40, $limit));
        $queued = 0;
        // A new request tries again, e.g. after the admin topped up.
        Cache::forget('supplier-price-scan-no-credit');

        foreach ($suppliers as $supplier) {
            if (blank($supplier->website_url) || $this->isQueued($supplier)) {
                continue;
            }

            $this->mark($supplier, [
                'status' => 'queued',
                'queued_at' => now()->toIso8601String(),
                'organisation_id' => $organisationId,
                'limit' => $limit,
            ]);
            ScanSupplierPricesJob::dispatch($supplier->id);
            $queued++;
        }

        return $queued;
    }

    public function isQueued(Supplier $supplier): bool
    {
        return in_array(data_get($supplier->metadata, 'price_scan.status'), ['queued', 'running'], true);
    }

    /**
     * Scan one queued supplier. Returns null when it is not queued any more or
     * another process is already scanning it.
     *
     * @return array{found: int, created: int, updated: int}|null
     */
    public function run(int $supplierId): ?array
    {
        $lock = Cache::lock('supplier-price-scan-'.$supplierId, 180);
        if (! $lock->get()) {
            return null;
        }

        try {
            $supplier = Supplier::find($supplierId);
            if (! $supplier || ! $this->isQueued($supplier)) {
                return null;
            }

            $state = (array) data_get($supplier->metadata, 'price_scan', []);

            // The providers ran out of credit during this run: skip the rest.
            if (Cache::has('supplier-price-scan-no-credit')) {
                $this->mark($supplier, ['status' => 'failed', 'error' => __('The AI providers have no tokens or credit left. Top up under AI API Settings.'), 'at' => now()->toIso8601String()]);

                return null;
            }

            $this->mark($supplier, ['status' => 'running'] + $state);
            @set_time_limit(180);

            try {
                $result = $this->prices->scanSupplier($supplier, $state['organisation_id'] ?? null, (int) ($state['limit'] ?? 15));
            } catch (AiCreditExhaustedException) {
                Cache::put('supplier-price-scan-no-credit', true, now()->addMinutes(30));
                $this->mark($supplier->fresh(), ['status' => 'failed', 'error' => __('The AI providers have no tokens or credit left. Top up under AI API Settings.'), 'at' => now()->toIso8601String()]);

                return null;
            } catch (Throwable $exception) {
                report($exception);
                $this->mark($supplier->fresh(), [
                    'status' => 'failed',
                    'error' => $exception instanceof RuntimeException ? $exception->getMessage() : __('The AI provider did not answer.'),
                    'at' => now()->toIso8601String(),
                ]);

                return null;
            }

            $this->mark($supplier->fresh(), ['status' => 'done', 'at' => now()->toIso8601String(), 'found' => $result['found']]);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /** Waiting scans (queued or running) across all suppliers. */
    public function pendingCount(): int
    {
        return Supplier::query()->whereIn('metadata->price_scan->status', ['queued', 'running'])->count();
    }

    /**
     * Run the oldest scan the queue worker has not picked up in time.
     * Returns the supplier scanned, or null when nothing was waiting.
     */
    public function runNextStalled(): ?Supplier
    {
        $stalled = Supplier::query()
            ->where('metadata->price_scan->status', 'queued')
            ->get()
            ->filter(fn (Supplier $s) => now()->diffInSeconds(\Illuminate\Support\Carbon::parse(data_get($s->metadata, 'price_scan.queued_at', now())), true) >= self::STALL_SECONDS)
            ->sortBy(fn (Supplier $s) => data_get($s->metadata, 'price_scan.queued_at'))
            ->first();

        // A scan stuck in "running" (the worker died) is queued again.
        Supplier::query()->where('metadata->price_scan->status', 'running')->get()
            ->filter(fn (Supplier $s) => now()->diffInMinutes(\Illuminate\Support\Carbon::parse(data_get($s->metadata, 'price_scan.queued_at', now())), true) >= 10)
            ->each(fn (Supplier $s) => $this->mark($s, ['status' => 'queued', 'queued_at' => now()->toIso8601String()] + (array) data_get($s->metadata, 'price_scan', [])));

        if (! $stalled) {
            return null;
        }

        $this->run($stalled->id);

        return $stalled->fresh();
    }

    private function mark(Supplier $supplier, array $state): void
    {
        $metadata = is_array($supplier->metadata) ? $supplier->metadata : [];
        $metadata['price_scan'] = $state;
        $supplier->forceFill(['metadata' => $metadata])->saveQuietly();
    }
}
