<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Services\AiProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * A self-hosted OpenAI-compatible gateway (WITU AI on a CPU server) is slow and
 * runs each request as a queued job: retries must reattach to that job, not
 * queue new ones, and small models' answers need lenient JSON parsing.
 */
class OpenAiCompatibleGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://ai.example.test/gw/v1';

    private function gateway(array $settings = []): AiProvider
    {
        return AiProvider::create([
            'key' => 'witu',
            'name' => 'WITU AI',
            'provider_type' => 'custom',
            'api_base_url' => self::URL,
            'default_model' => 'qwen2.5:1.5b',
            'api_key' => 'uhk_live_secret',
            'is_enabled' => true,
            'is_default' => true,
            'sort_order' => 1,
            'settings' => $settings,
        ]);
    }

    private function answer(string $content, string $finish = 'stop'): array
    {
        return ['choices' => [['message' => ['content' => $content], 'finish_reason' => $finish]], 'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 12]];
    }

    public function test_retries_reuse_one_idempotency_key_and_ask_for_json(): void
    {
        $this->gateway();
        Http::fakeSequence(self::URL.'/*')
            ->push(['error' => ['code' => 'TIMEOUT']], 504)
            ->push($this->answer('{"suggested_rate": 42000, "currency": "UGX"}'));

        $result = app(AiProviderService::class)->json('Price for cement', 'test', maxTokens: 200);

        $this->assertSame(42000, $result['suggested_rate']);
        $sent = Http::recorded();
        $this->assertCount(2, $sent);
        $keys = $sent->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0] ?? null)->unique();
        $this->assertCount(1, $keys);
        $this->assertNotNull($keys->first());
        Http::assertSent(fn (Request $r) => $r['response_format'] === ['type' => 'json_object'] && $r['max_tokens'] === 200);
    }

    public function test_each_call_gets_its_own_key_and_the_smaller_token_cap_wins(): void
    {
        $this->gateway(['max_tokens' => 120]);
        Http::fake([self::URL.'/*' => Http::response($this->answer('{"ok": true}'))]);

        app(AiProviderService::class)->json('a', 'test', maxTokens: 200);
        app(AiProviderService::class)->json('b', 'test');

        $sent = Http::recorded();
        $this->assertNotSame($sent[0][0]->header('Idempotency-Key')[0], $sent[1][0]->header('Idempotency-Key')[0]);
        $this->assertSame(120, $sent[0][0]['max_tokens']);
        $this->assertSame(120, $sent[1][0]['max_tokens']);
    }

    public function test_fenced_or_wrapped_json_is_accepted(): void
    {
        $this->gateway();
        Http::fake([self::URL.'/*' => Http::response($this->answer("Here you go:\n```json\n{\"suggested_rate\": 9500}\n```"))]);

        $this->assertSame(9500, app(AiProviderService::class)->json('x', 'test')['suggested_rate']);
    }

    public function test_a_cut_off_answer_says_so(): void
    {
        $this->gateway();
        Http::fake([self::URL.'/*' => Http::response($this->answer('{"items": [{"item_name": "Cem', 'length'))]);

        try {
            app(AiProviderService::class)->json('x', 'test');
            $this->fail('Expected a failure');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cut off at its token limit', $e->getPrevious()?->getMessage() ?? '');
        }
    }

    public function test_connection_test_explains_why_the_key_was_refused(): void
    {
        // Old service on /v1: wrong base URL.
        $old = $this->gateway();
        $old->forceFill(['api_base_url' => 'https://ai.witulibrary.org'])->save();
        Http::fake(['ai.witulibrary.org/*' => Http::response(['detail' => 'Invalid API key'], 401)]);

        $result = app(AiProviderService::class)->test($old);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('HTTP 401', $result['message']);
        $this->assertStringContainsString('Invalid API key', $result['message']);
        $this->assertStringContainsString('https://ai.witulibrary.org/gw/v1', $result['message']);
        $this->assertStringNotContainsString('uhk_live_secret', $result['message']);
    }

    public function test_connection_test_names_a_missing_chat_task(): void
    {
        $provider = $this->gateway();
        Http::fake([self::URL.'/*' => Http::response(['error' => ['code' => 'TASK_NOT_ALLOWED', 'message' => 'Task not allowed for this project']], 403)]);

        $message = app(AiProviderService::class)->test($provider)['message'];
        $this->assertStringContainsString('TASK_NOT_ALLOWED', $message);
        $this->assertStringContainsString('Allow the "chat" task', $message);
    }

    public function test_a_timeout_is_not_retried(): void
    {
        $this->gateway();
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('cURL error 28: Operation timed out after 45001 milliseconds');
        });

        try {
            app(AiProviderService::class)->json('x', 'test');
        } catch (RuntimeException) {
        }

        $this->assertSame(1, $attempts);
    }
}
