<?php

namespace App\Jobs;

use App\Models\AiProvider;
use App\Models\AiProviderUsage;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqPricingBatch;
use App\Models\HardwarePrice;
use App\Services\AiProviderService;
use App\Services\BoqExtractionService;
use App\Services\PriceMatchingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class ProcessBoqJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 300;

    private const AI_FAILURE_MESSAGE = 'AI pricing is unavailable. Retry the item or enter a manual rate.';

    public function __construct(public int $processingBatchId) {}

    public function handle(
        BoqExtractionService $extractor,
        PriceMatchingService $matcher,
        AiProviderService $providers
    ): void {
        $batch = BoqPricingBatch::findOrFail($this->processingBatchId);

        if ($this->isTerminalStatus($batch->status)) {
            return;
        }

        $initialTotalItems = (int) $batch->total_items;
        $batch->update([
            'status' => 'running',
            'started_at' => $batch->started_at ?? now(),
            'current_stage' => 'extracting',
            'provider' => null,
            'error_message' => null,
        ]);

        $boq = null;

        try {
            $boq = Boq::with('project')->findOrFail($batch->boq_id);
            $projectId = (int) ($boq->project_id ?: ($batch->project_id ?? 0));
            $projectLocation = trim((string) ($boq->project->location ?: $boq->project->district ?: $boq->project->country));
            $location = trim((string) ($batch->location ?: $projectLocation));

            if (! $boq->items()->exists()) {
                $batch->update(['current_stage' => 'extracting', 'message' => 'Extracting BOQ items...']);
                $extractor->extract($boq);
                $boq->refresh();
            }

            $batch->update(['current_stage' => 'matching', 'message' => 'Matching current prices...']);

            $items = $boq->items()->with('boq.project')->get();
            $organisationId = $batch->organisation_id === null ? null : (int) $batch->organisation_id;
            $providerKeys = null;
            $requestedProviderKey = null;

            foreach ($items as $item) {
                if ($this->batch() && $this->batch()->cancelled()) {
                    $batch->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'message' => 'Cancelled',
                    ]);

                    return;
                }

                $item->refresh();

                if ($item->pricing_status === 'priced') {
                    continue;
                }

                if ($item->match_type === 'manual') {
                    $this->markManualPriced($item, $location);

                    continue;
                }

                $match = $matcher->findMatches($item, 1, $location)->first();

                if ($match) {
                    $matcher->applyPriceToBoqItem(
                        $item,
                        $match['hardware_price'],
                        $match['similarity_score'],
                        $location
                    );
                    $this->markHardwarePriced(
                        $item,
                        $match['hardware_price'],
                        $match['similarity_score'] ?? null,
                        $location
                    );

                    continue;
                }

                if ($providerKeys === null) {
                    $providerKeys = $this->activeProviderKeys($organisationId);
                    $requestedProviderKey = $providerKeys[0] ?? null;
                }

                $wasFailed = $item->pricing_status === 'failed';

                try {
                    $this->clearAiState($item);
                    $attemptId = (string) Str::uuid();
                    $currency = (string) $item->currency;
                    $prompt = $this->pricingPrompt($item, $location, $currency);
                    $response = $providers->json(
                        prompt: $prompt,
                        operation: 'boq_pricing_suggestion',
                        organisationId: $organisationId,
                        requestedProvider: $requestedProviderKey,
                        context: [
                            'user_id' => (int) $batch->user_id,
                            'organisation_id' => $organisationId,
                            'project_id' => $projectId,
                            'boq_id' => (int) $boq->id,
                            'metadata' => [
                                'attempt_id' => $attemptId,
                            ],
                        ],
                    );

                    $usage = $this->successfulUsage($attemptId);

                    if (! $usage) {
                        throw new RuntimeException('No successful AI provider usage was recorded.');
                    }

                    $providerKey = trim((string) $usage->provider_key);
                    $allowedProviderKeys = $providerKeys ?: $this->activeProviderKeys($organisationId);

                    if ($providerKey === '' || ! in_array($providerKey, $allowedProviderKeys, true)) {
                        $allowedProviderKeys = $this->activeProviderKeys($organisationId);
                    }

                    if ($providerKey === '' || ! in_array($providerKey, $allowedProviderKeys, true)) {
                        throw new RuntimeException('The AI provider response could not be verified.');
                    }

                    $pricing = $this->pricingPayload($response, $currency);
                    $batch->update(['provider' => $providerKey]);
                    $this->markAiPriced($item, $pricing, $providerKey, $location);
                } catch (Throwable) {
                    $this->markAiFailed($item);

                    if (! $wasFailed) {
                        $batch->increment('failed_items');
                    }
                }
            }

            $boq->refresh();
            $allApproved = $boq->items()->exists()
                && ! $boq->items()->where('status', '!=', 'approved')->exists();
            $boq->update(['status' => $allApproved ? 'approved' : 'under_review']);

            $this->completeBatch($batch, $boq);
        } catch (Throwable $exception) {
            report($exception);

            if ($this->shouldMarkBatchFailed()) {
                $this->markBatchFailed($batch, $boq, $initialTotalItems);
            } else {
                $this->markRetryableFailure($batch);
            }

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $batch = BoqPricingBatch::find($this->processingBatchId);

        if (! $batch || $this->isTerminalStatus($batch->status)) {
            return;
        }

        report($exception);

        try {
            $this->markBatchFailed($batch, null, (int) $batch->total_items);
        } catch (Throwable $batchException) {
            report($batchException);
        }
    }

    private function isTerminalStatus(?string $status): bool
    {
        return in_array($status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true);
    }

    /**
     * Determine if the batch should be marked as permanently failed.
     */
    private function shouldMarkBatchFailed(): bool
    {
        return $this->job === null || $this->attempts() >= $this->tries;
    }

    /**
     * Get ordered list of active AI provider keys for the organisation.
     *
     * Organisation-specific providers are prioritised, followed by default
     * providers, then remaining providers by sort order.
     *
     * @return list<string>
     */
    private function activeProviderKeys(?int $organisationId): array
    {
        $providers = AiProvider::query()
            ->enabled()
            ->forOrganisation($organisationId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $providers
            ->sortBy(function (AiProvider $provider) use ($organisationId): int {
                if ($organisationId && (int) $provider->organisation_id === $organisationId) {
                    return 10;
                }

                if ($provider->is_default) {
                    return 20;
                }

                return 30 + (int) $provider->sort_order;
            })
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && trim($key) !== '')
            ->map(fn (string $key): string => trim($key))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Build the AI pricing prompt for a BOQ item.
     */
    private function pricingPrompt(BoqItem $item, string $location, string $currency): string
    {
        $input = [
            'item_id' => (int) $item->id,
            'item_code' => (string) ($item->item_code ?? ''),
            'description' => (string) $item->description,
            'unit' => (string) ($item->unit ?? ''),
            'location' => $location,
            'currency' => $currency,
        ];

        $schema = [
            'type' => 'object',
            'required' => ['suggested_rate', 'currency'],
            'properties' => [
                'suggested_rate' => [
                    'type' => 'number',
                    'exclusiveMinimum' => 0,
                ],
                'currency' => [
                    'type' => 'string',
                    'pattern' => '^[A-Z]{3}$',
                    'const' => $currency,
                ],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
            ],
            'additionalProperties' => false,
        ];

        $inputJson = json_encode(
            $input,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $schemaJson = json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return "Return one JSON object only. Do not return Markdown or any text outside the JSON object.\n"
            ."Use a current market unit price and follow this JSON schema exactly:\n"
            .$schemaJson
            ."\nThe response currency must exactly match the input currency.\n"
            ."Input data (JSON):\n"
            .$inputJson;
    }

    /**
     * Find the successful AI provider usage record for the given attempt ID.
     */
    private function successfulUsage(string $attemptId): ?AiProviderUsage
    {
        return AiProviderUsage::query()
            ->where('successful', true)
            ->where('operation', 'boq_pricing_suggestion')
            ->orderByDesc('id')
            ->get()
            ->first(function (AiProviderUsage $usage) use ($attemptId): bool {
                $metadata = $usage->metadata;

                return is_array($metadata)
                    && ($metadata['attempt_id'] ?? null) === $attemptId;
            });
    }

    /**
     * Extract and validate the pricing payload from the AI response.
     *
     * @return array{suggested_rate: string, currency: string, confidence: string|null}
     */
    private function pricingPayload(mixed $response, string $currency): array
    {
        if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1) {
            throw new RuntimeException('The BOQ currency is invalid.');
        }

        $payload = $this->responsePayload($response);

        if (! array_key_exists('suggested_rate', $payload)
            || ! array_key_exists('currency', $payload)) {
            throw new RuntimeException('The AI response did not contain a complete pricing suggestion.');
        }

        if (! is_string($payload['currency'])
            || $payload['currency'] !== $currency
            || preg_match('/\A[A-Z]{3}\z/', $payload['currency']) !== 1) {
            throw new RuntimeException('The AI response currency is invalid.');
        }

        $confidence = null;

        if (array_key_exists('confidence', $payload)) {
            $confidence = $this->normalizeConfidence($payload['confidence']);
        }

        return [
            'suggested_rate' => $this->normalizeRate($payload['suggested_rate']),
            'currency' => $currency,
            'confidence' => $confidence,
        ];
    }

    /**
     * Recursively extract the payload from various AI response formats.
     *
     * @return array{suggested_rate: mixed, currency: mixed, confidence?: mixed}
     */
    private function responsePayload(mixed $response): array
    {
        if (is_array($response)) {
            if (array_key_exists('suggested_rate', $response) || array_key_exists('currency', $response)) {
                return $response;
            }

            foreach (['content', 'text', 'response', 'raw', 'data'] as $key) {
                if (! array_key_exists($key, $response)) {
                    continue;
                }

                if (is_array($response[$key]) || is_string($response[$key])) {
                    return $this->responsePayload($response[$key]);
                }
            }

            throw new RuntimeException('The AI response was not a JSON object.');
        }

        if (is_string($response)) {
            return $this->decodeJsonText($response);
        }

        throw new RuntimeException('The AI response was not JSON.');
    }

    /**
     * Decode JSON text, handling BOM, markdown code fences, and whitespace.
     */
    private function decodeJsonText(string $text): array
    {
        $text = trim($text);

        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }

        $text = trim($text);

        if (str_starts_with($text, '```')) {
            if (substr_count($text, '```') !== 2
                || preg_match(
                    '~\A```[ \t]*(?:json)?[ \t]*(?:\r?\n)?(.*?)(?:\r?\n)?```\z~is',
                    $text,
                    $matches
                ) !== 1) {
                throw new RuntimeException('The AI response JSON fence is invalid.');
            }

            $text = trim($matches[1]);

            if (str_contains($text, '```')) {
                throw new RuntimeException('The AI response JSON fence is invalid.');
            }
        }

        try {
            $payload = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The AI response was not valid JSON.');
        }

        if (! is_array($payload)) {
            throw new RuntimeException('The AI response was not a JSON object.');
        }

        return $payload;
    }

    /**
     * Normalise a rate value to a string with exactly two decimal places.
     *
     * Rounds to the nearest cent and ensures the result is a valid positive
     * decimal string within supported range.
     */
    private function normalizeRate(mixed $value): string
    {
        if (is_bool($value) || (! is_int($value) && ! is_float($value))) {
            throw new RuntimeException('The suggested rate is invalid.');
        }

        if (is_float($value) && ! is_finite($value)) {
            throw new RuntimeException('The suggested rate is invalid.');
        }

        if ($value <= 0) {
            throw new RuntimeException('The suggested rate must be positive.');
        }

        $raw = is_int($value) ? (string) $value : sprintf('%.10F', $value);

        if (! str_contains($raw, '.')) {
            $raw .= '.';
        }

        [$whole, $fraction] = explode('.', $raw, 2);
        $whole = ltrim($whole, '0');

        if ($whole === '') {
            $whole = '0';
        }

        if (strlen($whole) > 15) {
            throw new RuntimeException('The suggested rate is outside the supported range.');
        }

        $fraction = substr(str_pad($fraction, 3, '0'), 0, 3);
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        if ($cents > 999999999999999 || $cents <= 0) {
            throw new RuntimeException('The suggested rate is outside the supported range.');
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Normalise a confidence value to a string with two decimal places.
     *
     * Clamps to [0, 1] range and rounds to 2 decimal places.
     */
    private function normalizeConfidence(mixed $value): string
    {
        if (is_bool($value) || (! is_int($value) && ! is_float($value))) {
            throw new RuntimeException('The AI confidence is invalid.');
        }

        if (is_float($value) && ! is_finite($value)) {
            throw new RuntimeException('The AI confidence is invalid.');
        }

        if ($value < 0 || $value > 1) {
            throw new RuntimeException('The AI confidence is outside the supported range.');
        }

        $confidence = max(0.0, round((float) $value, 2));

        return number_format($confidence, 2, '.', '');
    }

    /**
     * Reset all AI pricing fields on a BOQ item to start fresh.
     */
    private function clearAiState(BoqItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $locked = BoqItem::query()->lockForUpdate()->findOrFail($item->id);
            $locked->fill([
                'ai_suggested_rate' => null,
                'hardware_price_id' => null,
                'match_type' => null,
                'matched_by' => null,
                'matched_at' => null,
                'reviewed_rate' => null,
                'approved_rate' => null,
                'location' => null,
                'pricing_source' => null,
                'pricing_date' => null,
                'ai_confidence' => null,
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'pricing_status' => 'pricing',
                'priced_at' => null,
                'pricing_error' => null,
            ])->save();
        });

        $item->refresh();
    }

    /**
     * Mark a BOQ item as successfully priced by AI.
     */
    private function markAiPriced(
        BoqItem $item,
        array $pricing,
        string $providerKey,
        string $location
    ): void {
        DB::transaction(function () use ($item, $pricing, $providerKey, $location): void {
            $locked = BoqItem::query()->lockForUpdate()->findOrFail($item->id);
            $locked->fill([
                'ai_suggested_rate' => $pricing['suggested_rate'],
                'currency' => $pricing['currency'],
                'hardware_price_id' => null,
                'match_type' => null,
                'matched_by' => null,
                'matched_at' => null,
                'reviewed_rate' => null,
                'approved_rate' => null,
                'location' => $location,
                'pricing_source' => $providerKey,
                'pricing_date' => now()->toDateString(),
                'ai_confidence' => $pricing['confidence'],
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'pricing_status' => 'priced',
                'priced_at' => now(),
                'pricing_error' => null,
            ])->save();
        });

        $item->refresh();
    }

    /**
     * Mark a BOQ item as failed AI pricing attempt.
     */
    private function markAiFailed(BoqItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $locked = BoqItem::query()->lockForUpdate()->findOrFail($item->id);
            $locked->fill([
                'ai_suggested_rate' => null,
                'hardware_price_id' => null,
                'match_type' => null,
                'matched_by' => null,
                'matched_at' => null,
                'reviewed_rate' => null,
                'approved_rate' => null,
                'location' => null,
                'pricing_source' => null,
                'pricing_date' => null,
                'ai_confidence' => null,
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'pricing_status' => 'failed',
                'priced_at' => null,
                'pricing_error' => self::AI_FAILURE_MESSAGE,
            ])->save();
        });

        $item->refresh();
    }

    /**
     * Mark a BOQ item as manually priced (user-entered rate or spreadsheet).
     */
    private function markManualPriced(BoqItem $item, string $location): void
    {
        $source = $this->manualSource($item);

        DB::transaction(function () use ($item, $location, $source): void {
            $locked = BoqItem::query()->lockForUpdate()->findOrFail($item->id);
            $locked->fill([
                'hardware_price_id' => $source === 'manual' ? null : $locked->hardware_price_id,
                'match_type' => 'manual',
                'matched_at' => $locked->matched_at ?? now(),
                'reviewed_rate' => null,
                'approved_rate' => null,
                'location' => $locked->location ?: $location,
                'pricing_source' => $source,
                'pricing_date' => $locked->pricing_date ?? now()->toDateString(),
                'ai_confidence' => null,
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'pricing_status' => 'priced',
                'priced_at' => now(),
                'pricing_error' => null,
            ])->save();
        });

        $item->refresh();
    }

    /**
     * Determine the pricing source label for a manually priced item.
     */
    private function manualSource(BoqItem $item): string
    {
        $source = trim((string) $item->pricing_source);

        if ($item->hardware_price_id !== null
            && preg_match('/\Ahardware_price:(\d+)\z/', $source, $matches) === 1
            && (int) $matches[1] === (int) $item->hardware_price_id) {
            return $source;
        }

        return 'manual';
    }

    /**
     * Mark a BOQ item as priced from a hardware price database match.
     */
    private function markHardwarePriced(
        BoqItem $item,
        HardwarePrice $price,
        mixed $confidence,
        string $location
    ): void {
        DB::transaction(function () use ($item, $price, $confidence, $location): void {
            $locked = BoqItem::query()->lockForUpdate()->findOrFail($item->id);
            $locked->fill([
                'ai_suggested_rate' => $locked->ai_suggested_rate ?? $price->price,
                'hardware_price_id' => $price->id,
                'match_type' => 'automatic',
                'matched_by' => null,
                'matched_at' => $locked->matched_at ?? now(),
                'reviewed_rate' => null,
                'approved_rate' => null,
                'location' => $locked->location ?: ($price->location ?: $location),
                'pricing_source' => 'hardware_price:'.$price->id,
                'pricing_date' => $price->fetched_at?->toDateString() ?? now()->toDateString(),
                'ai_confidence' => $confidence === null ? null : round((float) $confidence * 100, 2),
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'pricing_status' => 'priced',
                'priced_at' => now(),
                'pricing_error' => null,
            ])->save();
        });

        $item->refresh();
    }

    /**
     * Finalise the batch after all items have been processed.
     *
     * Computes pricing distribution (manual, hardware, AI, failed) and
     * persists the batch completion status.
     */
    private function completeBatch(BoqPricingBatch $batch, Boq $boq): void
    {
        $items = $boq->items()->get();
        $distribution = [
            'manual' => 0,
            'hardware' => 0,
            'ai' => 0,
            'failed' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) $item->pricing_status;
            $source = trim((string) $item->pricing_source);

            if ($status !== 'priced') {
                $distribution['failed']++;

                continue;
            }

            if ($source === 'manual') {
                $distribution['manual']++;
            } elseif (preg_match('/\Ahardware_price:\d+\z/', $source) === 1) {
                $distribution['hardware']++;
            } else {
                $distribution['ai']++;
            }
        }

        $failed = (int) $distribution['failed'];
        $processed = array_sum($distribution);
        $status = $failed > 0 ? 'completed_with_errors' : 'completed';
        $message = sprintf(
            'Pricing completed: %d manual, %d hardware, %d AI, %d failed.',
            $distribution['manual'],
            $distribution['hardware'],
            $distribution['ai'],
            $failed
        );
        $encodedDistribution = json_encode($distribution, JSON_THROW_ON_ERROR);

        $this->persistBatchAttributes($batch, [
            'status' => $status,
            'current_stage' => 'completed',
            'completed_at' => now(),
            'processed_items' => $processed,
            'failed_items' => $failed,
            'message' => $message,
            'error_message' => $failed > 0
                ? 'Some BOQ items could not be priced. Retry failed items or enter manual rates.'
                : null,
            'pricing_distribution' => $encodedDistribution,
        ]);
    }

    /**
     * Persist batch attributes, handling pricing_distribution separately
     * since it may not exist as a column in the database yet.
     */
    private function persistBatchAttributes(BoqPricingBatch $batch, array $attributes): void
    {
        $distribution = null;
        $hasDistribution = array_key_exists('pricing_distribution', $attributes);

        if ($hasDistribution) {
            $distribution = $attributes['pricing_distribution'];
            unset($attributes['pricing_distribution']);
        }

        if ($attributes !== []) {
            $batch->update($attributes);
        }

        if ($hasDistribution) {
            $batch->setAttribute('pricing_distribution', $distribution);

            if (Schema::hasColumn($batch->getTable(), 'pricing_distribution')) {
                DB::table($batch->getTable())
                    ->where($batch->getKeyName(), $batch->getKey())
                    ->update([
                        'pricing_distribution' => $distribution,
                        'updated_at' => $batch->freshTimestampString(),
                    ]);
            }
        }
    }

    /**
     * Mark batch as failed in a way that allows retry (not terminal).
     */
    private function markRetryableFailure(BoqPricingBatch $batch): void
    {
        $fresh = BoqPricingBatch::find($batch->id);

        if (! $fresh || $this->isTerminalStatus($fresh->status)) {
            return;
        }

        $fresh->update([
            'current_stage' => 'failed',
            'message' => 'BOQ pricing stopped unexpectedly and will retry.',
            'error_message' => 'BOQ pricing could not be completed. Retry the batch.',
        ]);
    }

    /**
     * Mark batch as permanently failed after all retries exhausted.
     */
    private function markBatchFailed(
        BoqPricingBatch $batch,
        ?Boq $boq,
        int $initialTotalItems
    ): void {
        $fresh = BoqPricingBatch::find($batch->id);

        if (! $fresh || $this->isTerminalStatus($fresh->status)) {
            return;
        }

        $totalItems = (int) $fresh->total_items ?: $initialTotalItems;
        $currentFailed = (int) $fresh->failed_items;
        $unprocessed = max(0, $totalItems - (int) $fresh->processed_items - $currentFailed);
        $failed = $currentFailed + $unprocessed;
        $boq = $boq?->fresh() ?? $fresh->boq()->first();

        if ($boq) {
            $failed = max($failed, (int) $boq->items()->where('pricing_status', 'failed')->count());
        }

        if ($totalItems > 0) {
            $failed = min($failed, $totalItems);
        }

        $fresh->update([
            'status' => 'failed',
            'current_stage' => 'failed',
            'message' => 'BOQ pricing stopped before all items were processed.',
            'error_message' => 'BOQ pricing could not be completed. Retry the batch or process the remaining items manually.',
            'completed_at' => now(),
            'failed_items' => $failed,
        ]);

        if ($boq && $boq->status !== 'approved') {
            $boq->update(['status' => 'under_review']);
        }
    }
}
