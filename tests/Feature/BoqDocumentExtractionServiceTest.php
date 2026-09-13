<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\User;
use App\Services\BoqDocumentExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BoqDocumentExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_extracts_a_pdf_from_inline_data_and_persists_validated_items(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        $boq = $this->makeBoq('pdf', 'boqs/document.pdf');
        $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        Storage::disk('local')->put($boq->source_file_path, $pdf);
        Http::fake(['*' => Http::response($this->geminiResponse([
            'items' => [[
                'item_code' => 'A1',
                'description' => 'Concrete foundation',
                'unit' => 'm3',
                'quantity' => 2,
                'original_rate' => 10,
                'amount' => 24,
            ]],
            'warnings' => ['Faint rate column'],
        ]))]);

        $result = app(BoqDocumentExtractionService::class)->extract($boq);

        $this->assertSame(['count' => 1, 'warnings' => ['Faint rate column'], 'provider' => 'gemini'], $result);
        $this->assertDatabaseHas('boq_items', [
            'boq_id' => $boq->id,
            'description' => 'Concrete foundation',
            'original_rate' => 10,
            'approved_rate' => null,
            'amount' => 24,
            'status' => 'pending',
        ]);
        $this->assertSame('under_review', $boq->fresh()->status);
        Http::assertSent(function (Request $request) use ($pdf): bool {
            $data = $request->data();

            return str_contains($request->url(), ':generateContent?key=gemini-test-key')
                && $data['contents'][0]['parts'][1]['inline_data']['mime_type'] === 'application/pdf'
                && $data['contents'][0]['parts'][1]['inline_data']['data'] === base64_encode($pdf)
                && $data['generationConfig']['responseMimeType'] === 'application/json';
        });
    }

    public function test_gemini_sends_image_bytes_as_inline_data(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        $boq = $this->makeBoq('scan', 'boqs/scan.png');
        $image = $this->png();
        Storage::disk('local')->put($boq->source_file_path, $image);
        Http::fake(['*' => Http::response($this->geminiResponse($this->validExtraction()))]);

        app(BoqDocumentExtractionService::class)->extract($boq);

        Http::assertSent(fn (Request $request): bool => $request->data()['contents'][0]['parts'][1]['inline_data'] === [
            'mime_type' => 'image/png',
            'data' => base64_encode($image),
        ]
        );
    }

    public function test_openai_sends_an_image_as_a_data_uri(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', 'openai');
        config()->set('services.openai.key', 'openai-test-key');
        $boq = $this->makeBoq('scan', 'boqs/scan.png');
        $image = $this->png();
        Storage::disk('local')->put($boq->source_file_path, $image);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($this->validExtraction())]]],
        ])]);

        $result = app(BoqDocumentExtractionService::class)->extract($boq);

        $this->assertSame('openai', $result['provider']);
        Http::assertSent(function (Request $request) use ($image): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer openai-test-key')
                && $data['messages'][0]['content'][1]['image_url']['url'] === 'data:image/png;base64,'.base64_encode($image)
                && $data['response_format'] === ['type' => 'json_object'];
        });
    }

    public function test_malformed_provider_response_does_not_replace_existing_items(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        $boq = $this->makeBoq('pdf', 'boqs/document.pdf');
        Storage::disk('local')->put($boq->source_file_path, "%PDF-1.4\n%%EOF");
        $existing = BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Existing item']);
        Http::fake(['*' => Http::response($this->geminiResponse(['items' => [['description' => 'Missing quantity']]]))]);

        try {
            app(BoqDocumentExtractionService::class)->extract($boq);
            $this->fail('A malformed response should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertSame('The BOQ document could not be extracted by the configured AI provider.', $exception->errors()['boq'][0]);
        }

        $this->assertDatabaseHas('boq_items', ['id' => $existing->id, 'description' => 'Existing item']);
        $this->assertSame(1, $boq->items()->count());
    }

    public function test_provider_failure_is_sanitized_and_preserves_existing_items(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        $boq = $this->makeBoq('pdf', 'boqs/document.pdf');
        Storage::disk('local')->put($boq->source_file_path, "%PDF-1.4\n%%EOF");
        $existing = BoqItem::factory()->create(['boq_id' => $boq->id]);
        Http::fake(['*' => Http::response(['provider_secret' => 'do not expose'], 500)]);

        try {
            app(BoqDocumentExtractionService::class)->extract($boq);
            $this->fail('A provider failure should fail extraction.');
        } catch (ValidationException $exception) {
            $this->assertSame('The BOQ document could not be extracted by the configured AI provider.', $exception->errors()['boq'][0]);
            $this->assertStringNotContainsString('provider_secret', $exception->getMessage());
        }

        $this->assertDatabaseHas('boq_items', ['id' => $existing->id]);
    }

    public function test_openai_pdf_is_rejected_without_an_external_request(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', 'openai');
        config()->set('services.openai.key', 'openai-test-key');
        $boq = $this->makeBoq('pdf', 'boqs/document.pdf');
        Storage::disk('local')->put($boq->source_file_path, "%PDF-1.4\n%%EOF");
        Http::fake();

        try {
            app(BoqDocumentExtractionService::class)->extract($boq);
            $this->fail('OpenAI PDF extraction should not be attempted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('PDF extraction is not supported', $exception->errors()['boq'][0]);
        }

        Http::assertNothingSent();
    }

    public function test_missing_provider_key_fails_before_an_external_request(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', null);
        $boq = $this->makeBoq('scan', 'boqs/scan.png');
        Storage::disk('local')->put($boq->source_file_path, $this->png());
        Http::fake();

        $this->expectException(ValidationException::class);

        try {
            app(BoqDocumentExtractionService::class)->extract($boq);
        } finally {
            Http::assertNothingSent();
        }
    }

    private function makeBoq(string $sourceType, string $path): Boq
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $project = Project::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
            'currency' => 'UGX',
        ]);

        return Boq::factory()->create([
            'organisation_id' => $organisation->id,
            'project_id' => $project->id,
            'source_type' => $sourceType,
            'source_file_path' => $path,
            'currency' => 'UGX',
            'status' => 'uploaded',
        ]);
    }

    private function geminiResponse(array $extraction): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode($extraction)]]],
            ]],
        ];
    }

    private function validExtraction(): array
    {
        return [
            'items' => [[
                'item_code' => null,
                'description' => 'Excavation',
                'unit' => 'm3',
                'quantity' => 3.5,
                'original_rate' => null,
                'amount' => null,
            ]],
            'warnings' => [],
        ];
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    }
}
