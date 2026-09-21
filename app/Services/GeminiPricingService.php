<?php

namespace App\Services;

use App\Models\BoqItem;
use RuntimeException;
use Throwable;

class GeminiPricingService
{
    public function __construct(
        private readonly AiProviderService $providers
    ) {
    }

    public function suggest(array $item, string $location, string $currency): array
    {
        $description = (string) ($item['description'] ?? '');
        $unit = (string) ($item['unit'] ?? '');

        $prompt = <<<PROMPT
Estimate a current civil-works unit rate using location-specific market context.

Return JSON only with:
{
  "suggested_rate": number,
  "confidence": number,
  "explanation": string
}

Do not invent certainty.
Item: {$description}
Unit: {$unit}
Location: {$location}
Currency: {$currency}
PROMPT;

        $result = $this->providers->json(
            prompt: $prompt,
            operation: 'boq_pricing_suggestion',
            organisationId: auth()->user()?->organisation_id,
            context: ['user_id' => auth()->id()]
        );

        if (! isset($result['suggested_rate'])) {
            throw new RuntimeException('AI returned an invalid pricing response.');
        }

        return $result;
    }

    /**
     * Suggest an AI rate for the item and persist it as a pending review.
     * Returns false (without throwing) when no AI provider is available so
     * callers can fall back to hardware matches or spreadsheet rates.
     */
    public function apply(BoqItem $item, string $location): bool
    {
        try {
            $result = $this->suggest(
                ['description' => $item->description, 'unit' => $item->unit],
                $location,
                (string) $item->currency
            );

            $item->fill([
                'ai_suggested_rate' => $result['suggested_rate'],
                'reviewed_rate' => null,
                'approved_rate' => null,
                'location' => $location,
                'ai_confidence' => $result['confidence'] ?? null,
                'pricing_source' => config('services.ai_provider'),
                'pricing_date' => now(),
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
