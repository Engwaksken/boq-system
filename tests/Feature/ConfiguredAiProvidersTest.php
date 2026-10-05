<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Boq;
use App\Models\Project;
use App\Models\User;
use App\Services\BoqDocumentExtractionService;
use App\Services\HardwarePriceFetchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConfiguredAiProvidersTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $type, string $key, array $attributes = []): AiProvider
    {
        return AiProvider::create($attributes + [
            'key' => $key,
            'name' => ucfirst($key),
            'provider_type' => $type,
            'api_base_url' => $type === 'gemini' ? 'https://generativelanguage.googleapis.com' : 'https://api.deepseek.com/v1',
            'default_model' => $type === 'gemini' ? 'gemini-2.5-flash' : 'deepseek-chat',
            'api_key' => "{$key}-secret",
            'is_enabled' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_price_scan_uses_the_default_configured_provider_not_the_env_gemini_key(): void
    {
        config()->set('services.gemini.key', null);
        $this->provider('deepseek', 'deepseek');
        $user = User::factory()->create();
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'item_name' => 'Portland Cement 42.5N',
                'brand' => 'Hima',
                'category' => 'Cement',
                'price_type' => 'hardware',
                'specification' => '50kg bag',
                'unit' => 'bag',
                'price' => 36000,
                'currency' => 'UGX',
                'supplier' => 'Kampala Hardware',
                'location' => 'Kampala',
            ])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
        ])]);

        $results = app(HardwarePriceFetchingService::class)
            ->fetchPricesForCategory('Cement', 'Kampala', 1, $user->organisation_id);

        $this->assertNotEmpty($results);
        $this->assertEquals(36000, (float) $results[0]['price']);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.deepseek.com/v1/chat/completions')
            && $request->hasHeader('Authorization', 'Bearer deepseek-secret'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'generativelanguage'));
    }

    public function test_pdf_boq_extraction_uses_a_configured_gemini_provider(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', null);
        config()->set('services.gemini.key', null);
        $this->provider('deepseek', 'deepseek');
        $this->provider('gemini', 'gemini', ['is_default' => false, 'sort_order' => 2]);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => 'pdf',
            'source_file_path' => 'boqs/doc.pdf',
        ]);
        Storage::disk('local')->put('boqs/doc.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['items' => [[
                'item_code' => 'A1', 'description' => 'Excavation', 'unit' => 'm3',
                'quantity' => 5, 'original_rate' => 100, 'amount' => 500,
            ]], 'warnings' => []])]]]]],
        ])]);

        $result = app(BoqDocumentExtractionService::class)->extract($boq);

        $this->assertSame(1, $result['count']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'models/gemini-2.5-flash:generateContent?key=gemini-secret'));
    }

    public function test_pdf_extraction_explains_that_a_vision_provider_is_needed(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', null);
        $this->provider('deepseek', 'deepseek');
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => 'pdf',
            'source_file_path' => 'boqs/doc.pdf',
        ]);
        Storage::disk('local')->put('boqs/doc.pdf', "%PDF-1.4\n%%EOF");
        Http::fake();

        try {
            app(BoqDocumentExtractionService::class)->extract($boq);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('needs a vision-capable AI provider', $e->errors()['boq'][0]);
        }
        Http::assertNothingSent();
    }
}
