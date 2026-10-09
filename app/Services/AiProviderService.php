<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\AiProviderUsage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiProviderService
{
    public function __construct(private ?AiUsageMonitor $monitor = null)
    {
        $this->monitor ??= new AiUsageMonitor();
    }

    public function json(
        string $prompt,
        string $operation,
        ?int $organisationId = null,
        ?string $requestedProvider = null,
        array $context = []
    ): array {
        $enabled = $this->candidates($organisationId, $requestedProvider);

        if ($enabled->isEmpty()) {
            throw new RuntimeException('No enabled AI provider is configured.');
        }

        // Skip providers known to have no tokens/credit left.
        $providers = $enabled->reject(fn (AiProvider $provider) => $this->monitor->isExhausted($provider))->values();

        if ($providers->isEmpty()) {
            foreach ($enabled as $provider) {
                $this->monitor->warnIfLow($provider);
            }

            throw new \App\Exceptions\AiCreditExhaustedException();
        }

        $lastException = null;
        $creditFailures = 0;

        foreach ($providers as $provider) {
            $started = hrtime(true);

            try {
                $result = $this->sendJsonRequest($provider, $prompt);
                $this->recordUsage(
                    $provider,
                    $operation,
                    true,
                    $started,
                    $organisationId,
                    $context,
                    null,
                    null,
                    $result['_usage'] ?? []
                );

                unset($result['_usage']);
                $this->afterSuccess($provider);

                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;
                $category = $this->categorise($exception);

                if ($category === 'insufficient_credit') {
                    $creditFailures++;
                    $this->monitor->markExhausted($provider, $this->httpStatusLabel($exception));

                    continue;
                }

                $this->recordUsage(
                    $provider,
                    $operation,
                    false,
                    $started,
                    $organisationId,
                    $context,
                    $category,
                    $this->safeMessage($exception),
                    []
                );

                // Gemini live-web-search grounding can be rejected by the
                // API (e.g. unsupported tool/model combination). Retry the
                // same provider once without grounding so scans degrade
                // gracefully to estimates instead of failing entirely.
                if ($this->supportsGroundingRetry($provider)) {
                    try {
                        $result = $this->sendJsonRequest($provider, $prompt, false);

                        $this->recordUsage(
                            $provider,
                            $operation,
                            true,
                            $started,
                            $organisationId,
                            $context,
                            null,
                            null,
                            $result['_usage'] ?? []
                        );

                        unset($result['_usage']);
                        $this->afterSuccess($provider);

                        return $result;
                    } catch (Throwable) {
                        // Keep the original failure for the next provider.
                    }
                }

                // Invalid credentials/configuration should be visible to admins.
                // We still allow fallback so customer-facing workflows are not broken.
                continue;
            }
        }

        if ($creditFailures === $providers->count()) {
            throw new \App\Exceptions\AiCreditExhaustedException(previous: $lastException);
        }

        throw new RuntimeException(
            'AI service is temporarily unavailable. Please try again later.',
            previous: $lastException
        );
    }

    /** Only connection problems and server errors are worth retrying (not 4xx such as "no balance"). */
    private function isTransient(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && $exception->response->serverError());
    }

    private function afterSuccess(AiProvider $provider): void
    {
        try {
            $this->monitor->markWorking($provider);

            // Only providers with a limit, balance or expiry set can be running low.
            if ($provider->monthly_token_limit || $provider->credit_expires_at || $provider->credit_balance !== null) {
                $this->monitor->warnIfLow($provider);
            }
        } catch (Throwable) {
            // Monitoring must never break the request that just succeeded.
        }
    }

    private function httpStatusLabel(Throwable $exception): string
    {
        return $exception instanceof RequestException ? 'HTTP '.$exception->response->status() : '';
    }

    public function test(AiProvider $provider): array
    {
        $prompt = 'Return JSON only: {"ok":true}';

        try {
            $result = $this->sendJsonRequest($provider, $prompt);

            if (($result['ok'] ?? null) !== true) {
                throw new RuntimeException('AI provider returned an invalid test response.');
            }

            $provider->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'success',
                'last_test_message' => 'Connected successfully.',
            ])->save();

            return [
                'ok' => true,
                'message' => 'Connected successfully.',
                'result' => $result,
            ];
        } catch (Throwable $exception) {
            $category = $this->categorise($exception);
            $message = match ($category) {
                'authentication' => 'Authentication failed.',
                'insufficient_credit' => 'No tokens or credit left on this account. Top it up.',
                'model_not_found' => 'Model not found.',
                'rate_limit' => 'Provider rate limit reached.',
                'timeout' => 'Provider connection timed out.',
                'configuration' => 'Provider configuration is invalid.',
                default => 'Provider unavailable.',
            };

            $provider->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => $message,
            ])->save();

            return ['ok' => false, 'message' => $message];
        }
    }

    private function candidates(?int $organisationId, ?string $requestedProvider): Collection
    {
        $query = AiProvider::query()
            ->enabled()
            ->forOrganisation($organisationId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');

        $providers = $query->get();

        return $providers->sortBy(function (AiProvider $provider) use ($organisationId, $requestedProvider) {
            if ($requestedProvider && $provider->key === $requestedProvider) {
                return 0;
            }

            if ($organisationId && $provider->organisation_id === $organisationId) {
                return 10;
            }

            if ($provider->is_default) {
                return 20;
            }

            return 30 + $provider->sort_order;
        })->values();
    }

    private function sendJsonRequest(AiProvider $provider, string $prompt, ?bool $webSearchOverride = null): array
    {
        $type = strtolower($provider->provider_type);
        $settings = $provider->settings ?? [];
        $timeout = max(5, (int) ($settings['timeout'] ?? 45));

        return match ($type) {
            'gemini', 'google_gemini' => $this->gemini($provider, $prompt, $timeout, $webSearchOverride),
            'anthropic' => $this->anthropic($provider, $prompt, $timeout),
            'azure_openai' => $this->azureOpenAi($provider, $prompt, $timeout),
            default => $this->openAiCompatible($provider, $prompt, $timeout),
        };
    }

    private function supportsGroundingRetry(AiProvider $provider): bool
    {
        return in_array(
            strtolower($provider->provider_type),
            ['gemini', 'google_gemini'],
            true
        ) && (bool) data_get($provider->settings, 'web_search', false);
    }

    private function gemini(AiProvider $provider, string $prompt, int $timeout, ?bool $webSearchOverride = null): array
    {
        if (blank($provider->api_key)) {
            throw new RuntimeException('Provider API key is missing.');
        }

        $base = preg_replace('#/v1(beta)?$#', '', rtrim($provider->api_base_url ?: 'https://generativelanguage.googleapis.com', '/'));
        $model = $provider->default_model ?: 'gemini-2.5-flash';

        $body = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => (float) data_get($provider->settings, 'temperature', 0.2)],
        ];
        if (filled(data_get($provider->settings, 'max_tokens'))) {
            $body['generationConfig']['maxOutputTokens'] = (int) data_get($provider->settings, 'max_tokens');
        }

        // Opt-in live web search via Google Search grounding so price scans
        // can return real, cited market results instead of model estimates.
        $webSearch = $webSearchOverride
            ?? (bool) data_get($provider->settings, 'web_search', false);

        if ($webSearch) {
            $body['tools'] = [['google_search' => new \stdClass()]];
        }

        $response = Http::timeout($timeout)
            ->retry(2, 400, fn (Throwable $e): bool => $this->isTransient($e), throw: false)
            ->post(
                "{$base}/v1beta/models/{$model}:generateContent?key=".urlencode($provider->api_key),
                $body
            );

        if (! $response->successful()) {
            $response->throw();
        }

        $payload = $response->json();
        $text = data_get($payload, 'candidates.0.content.parts.0.text');

        $result = json_decode((string) $text, true);

        if (! is_array($result)) {
            throw new RuntimeException('AI provider returned invalid JSON.');
        }

        $result['_grounding'] = collect(
            data_get($payload, 'candidates.0.groundingMetadata.groundingChunks', [])
        )
            ->map(fn ($chunk): array => [
                'title' => data_get($chunk, 'web.title'),
                'uri' => data_get($chunk, 'web.uri'),
            ])
            ->filter(fn ($source): bool => filled($source['uri'] ?? null))
            ->values()
            ->all();

        $result['_usage'] = [
            'input_units' => (int) data_get($payload, 'usageMetadata.promptTokenCount', 0),
            'output_units' => (int) data_get($payload, 'usageMetadata.candidatesTokenCount', 0),
        ];

        return $result;
    }

    private function openAiCompatible(AiProvider $provider, string $prompt, int $timeout): array
    {
        if (blank($provider->api_key) && ! in_array($provider->provider_type, ['ollama'], true)) {
            throw new RuntimeException('Provider API key is missing.');
        }

        $preset = config('ai-providers.'.$provider->provider_type, []);
        $base = rtrim($provider->api_base_url ?: ($preset['url'] ?? 'https://api.openai.com/v1'), '/');
        $endpoint = isset($preset['endpoint']) ? $base.$preset['endpoint'] : (str_ends_with($base, '/v1')
            ? "{$base}/chat/completions"
            : "{$base}/v1/chat/completions");

        $request = Http::timeout($timeout)->retry(2, 400, fn (Throwable $e): bool => $this->isTransient($e), throw: false);

        if (filled($provider->api_key)) {
            $request = $request->withToken($provider->api_key);
        }

        $headers = (array) data_get($provider->settings, 'headers', []);
        if ($headers) {
            $request = $request->withHeaders($headers);
        }

        $body = [
            'model' => $provider->default_model,
            'messages' => [
                ['role' => 'system', 'content' => 'Return valid JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => (float) data_get($provider->settings, 'temperature', 0.2),
        ];
        if ($preset['json_mode'] ?? true) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        if (filled(data_get($provider->settings, 'max_tokens'))) {
            $body['max_tokens'] = (int) data_get($provider->settings, 'max_tokens');
        }
        $response = $request->post($endpoint, $body);

        if (! $response->successful()) {
            $response->throw();
        }

        $payload = $response->json();
        $content = data_get($payload, 'choices.0.message.content');
        $result = json_decode((string) $content, true);

        if (! is_array($result)) {
            throw new RuntimeException('AI provider returned invalid JSON.');
        }

        $result['_usage'] = [
            'input_units' => (int) data_get($payload, 'usage.prompt_tokens', 0),
            'output_units' => (int) data_get($payload, 'usage.completion_tokens', 0),
        ];

        return $result;
    }

    private function anthropic(AiProvider $provider, string $prompt, int $timeout): array
    {
        if (blank($provider->api_key)) {
            throw new RuntimeException('Provider API key is missing.');
        }
        $base = preg_replace('#/v1$#', '', rtrim($provider->api_base_url ?: 'https://api.anthropic.com', '/'));
        $payload = Http::timeout($timeout)->withHeaders([
            'x-api-key' => $provider->api_key, 'anthropic-version' => '2023-06-01',
        ])->post($base.'/v1/messages', [
            'model' => $provider->default_model,
            'max_tokens' => (int) (data_get($provider->settings, 'max_tokens') ?? 4096),
            'temperature' => (float) data_get($provider->settings, 'temperature', 0.2),
            'system' => 'Return valid JSON only, without markdown fences.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ])->throw()->json();
        $text = collect($payload['content'] ?? [])->where('type', 'text')->pluck('text')->implode('');
        $result = json_decode($text, true);
        if (! is_array($result)) {
            throw new RuntimeException('AI provider returned invalid JSON.');
        }
        $result['_usage'] = ['input_units' => (int) data_get($payload, 'usage.input_tokens', 0),
            'output_units' => (int) data_get($payload, 'usage.output_tokens', 0)];

        return $result;
    }

    private function azureOpenAi(AiProvider $provider, string $prompt, int $timeout): array
    {
        if (blank($provider->api_key) || blank($provider->api_base_url) || blank($provider->default_model)) {
            throw new RuntimeException('Provider configuration is invalid.');
        }
        $base = rtrim($provider->api_base_url, '/');
        $payload = Http::timeout($timeout)->withHeaders(['api-key' => $provider->api_key])
            ->post($base.'/openai/deployments/'.rawurlencode($provider->default_model).'/chat/completions?api-version='.rawurlencode(data_get($provider->settings, 'api_version', '2024-10-21')), [
                'model' => $provider->default_model,
                'messages' => [['role' => 'system', 'content' => 'Return valid JSON only.'], ['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
            ])->throw()->json();
        $result = json_decode((string) data_get($payload, 'choices.0.message.content'), true);
        if (! is_array($result)) {
            throw new RuntimeException('AI provider returned invalid JSON.');
        }
        $result['_usage'] = ['input_units' => (int) data_get($payload, 'usage.prompt_tokens', 0),
            'output_units' => (int) data_get($payload, 'usage.completion_tokens', 0)];

        return $result;
    }

    private function recordUsage(
        AiProvider $provider,
        string $operation,
        bool $successful,
        int $started,
        ?int $organisationId,
        array $context,
        ?string $errorCategory,
        ?string $errorMessage,
        array $usage
    ): void {
        try {
            AiProviderUsage::create([
                'ai_provider_id' => $provider->id,
                'organisation_id' => $organisationId,
                'user_id' => $context['user_id'] ?? auth()->id(),
                'project_id' => $context['project_id'] ?? null,
                'boq_id' => $context['boq_id'] ?? null,
                'provider_key' => $provider->key,
                'model' => $provider->default_model,
                'operation' => $operation,
                'input_units' => (int) ($usage['input_units'] ?? 0),
                'output_units' => (int) ($usage['output_units'] ?? 0),
                'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'successful' => $successful,
                'error_category' => $errorCategory,
                'error_message' => $errorMessage,
                'metadata' => $context['metadata'] ?? null,
            ]);
        } catch (Throwable) {
            // Usage logging must never break the customer workflow.
        }
    }

    private function categorise(Throwable $exception): string
    {
        if ($exception instanceof ConnectionException) {
            return 'timeout';
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();
            $body = strtolower((string) $exception->response->body());

            // 402 Payment Required (DeepSeek "Insufficient Balance"), OpenAI
            // "insufficient_quota", billing and credit errors.
            if ($status === 402 || (in_array($status, [400, 403, 429], true) && preg_match('/insufficient[_ ](quota|balance|credit|funds)|billing|credit balance|out of credit|quota exceeded|exceeded your current quota/', $body))) {
                return 'insufficient_credit';
            }

            return match (true) {
                in_array($status, [401, 403], true) => 'authentication',
                $status === 404 => 'model_not_found',
                $status === 429 => 'rate_limit',
                $status >= 500 => 'provider_unavailable',
                default => 'configuration',
            };
        }

        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'api key') || str_contains($message, 'configuration')) {
            return 'configuration';
        }

        return 'provider_error';
    }

    private function safeMessage(Throwable $exception): string
    {
        return mb_substr(preg_replace('/(?:key|token|secret)=?[^\\s&]+/i', '$1=***', $exception->getMessage()) ?? '', 0, 500);
    }
}
