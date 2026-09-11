<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiPricingService
{
    public function suggest(array $item, string $location, string $currency): array
    {
        if (config('services.ai_provider') === 'openai') {
            return $this->openAiSuggest($item, $location, $currency);
        }
        $key = config('services.gemini.key');
        if (empty($key)) throw new RuntimeException('AI pricing is not configured.');

        $prompt = "Estimate a current civil-works unit rate using location-specific market context. Return JSON only with suggested_rate (number), confidence (0-100), and explanation (string). Do not invent certainty. Item: {$item['description']}; unit: {$item['unit']}; location: {$location}; currency: {$currency}.";
        $response = Http::timeout(45)->post(config('services.gemini.base_url').'/v1/models/'.config('services.gemini.model').':generateContent?key='.$key, [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ])->throw()->json();
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $result = json_decode($text, true);
        if (!is_array($result) || !isset($result['suggested_rate'])) throw new RuntimeException('AI returned an invalid pricing response.');

        return $result;
    }

    private function openAiSuggest(array $item, string $location, string $currency): array
    {
        $key = config('services.openai.key');
        if (empty($key)) throw new RuntimeException('OpenAI pricing is not configured.');
        $prompt = "Estimate a current civil-works unit rate using location-specific market context. Return JSON with suggested_rate (number), confidence (0-100), and explanation (string). Item: {$item['description']}; unit: {$item['unit']}; location: {$location}; currency: {$currency}.";
        $response = Http::timeout(45)->withToken($key)->post(config('services.openai.base_url').'/v1/chat/completions', [
            'model' => config('services.openai.model'),
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'response_format' => ['type' => 'json_object'],
        ])->throw()->json();
        $result = json_decode($response['choices'][0]['message']['content'] ?? '', true);
        if (!is_array($result) || !isset($result['suggested_rate'])) throw new RuntimeException('OpenAI returned an invalid pricing response.');
        return $result;
    }
}
