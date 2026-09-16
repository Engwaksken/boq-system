<?php

namespace App\Services;

use RuntimeException;

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
}
