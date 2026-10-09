<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\User;
use App\Services\ReceiptExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReceiptExtractionTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(): Organisation
    {
        return Organisation::factory()->create();
    }

    private function user(Organisation $organisation, bool $verified = true): User
    {
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'email_verified_at' => $verified ? now() : null,
        ]);
        $user->roles()->attach(\App\Models\Role::firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User']
        )->id, ['organisation_id' => $organisation->id]);

        return $user;
    }

    private function validExtraction(): array
    {
        return [
            'supplier' => 'Ace Hardware',
            'purchase_date' => '2026-10-01',
            'description' => 'Cement 50kg',
            'quantity' => 2,
            'unit' => 'bags',
            'rate' => 15.5,
            'total' => 31,
            'currency' => 'UGX',
            'payment_method' => 'cash',
            'warnings' => ['Total appears to include tax'],
        ];
    }

    private function geminiResponse(array $extraction): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode($extraction)]]],
            ]],
        ];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    }

    public function test_gemini_extracts_expense_fields_from_a_pdf_receipt(): void
    {
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        Http::fake(['*' => Http::response($this->geminiResponse($this->validExtraction()))]);

        $result = app(ReceiptExtractionService::class)->extract($this->pdf(), $this->organisation()->id);

        $this->assertSame('Ace Hardware', $result['supplier']);
        $this->assertSame('2026-10-01', $result['purchase_date']);
        $this->assertSame('Cement 50kg', $result['description']);
        $this->assertSame(2.0, $result['quantity']);
        $this->assertSame('bags', $result['unit']);
        $this->assertSame(15.5, $result['rate']);
        $this->assertSame('UGX', $result['currency']);
        $this->assertSame(['Total appears to include tax'], $result['warnings']);
        $this->assertSame('gemini', $result['provider']);
    }

    public function test_openai_extracts_expense_fields_from_an_image_receipt(): void
    {
        config()->set('services.ai_provider', 'openai');
        config()->set('services.openai.key', 'openai-test-key');
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($this->validExtraction())]]],
        ])]);

        $image = UploadedFile::fake()->image('receipt.jpg', 100, 100);
        $result = app(ReceiptExtractionService::class)->extract($image, $this->organisation()->id);

        $this->assertSame('openai', $result['provider']);
        $this->assertSame('Ace Hardware', $result['supplier']);
    }

    public function test_deepseek_provider_is_used_for_receipt_extraction(): void
    {
        $organisation = $this->organisation();
        AiProvider::create([
            'key' => 'deepseek', 'name' => 'DeepSeek', 'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1', 'default_model' => 'deepseek-chat',
            'api_key' => 'deepseek-secret', 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($this->validExtraction())]]],
        ])]);

        $image = UploadedFile::fake()->image('receipt.jpg', 100, 100);
        $result = app(ReceiptExtractionService::class)->extract($image, $organisation->id);

        $this->assertSame('openai', $result['provider']);
        $this->assertSame('Ace Hardware', $result['supplier']);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.deepseek.com/v1/chat/completions'));
    }

    public function test_extraction_fails_cleanly_when_no_vision_provider_is_configured(): void
    {
        config()->set('services.ai_provider', null);
        Http::fake();

        $this->expectException(ValidationException::class);

        try {
            app(ReceiptExtractionService::class)->extract($this->pdf(), $this->organisation()->id);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_extraction_rejects_an_unsupported_file_type(): void
    {
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        Http::fake();

        try {
            app(ReceiptExtractionService::class)->extract(
                UploadedFile::fake()->createWithContent('receipt.txt', 'not a receipt'),
                $this->organisation()->id
            );
            $this->fail('A text file should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('PDF, JPEG, PNG, or WebP', $exception->errors()['file'][0]);
        }

        Http::assertNothingSent();
    }

    public function test_malformed_provider_response_fails_cleanly(): void
    {
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        Http::fake(['*' => Http::response($this->geminiResponse(['description' => 'Missing fields']))]);

        $this->expectException(ValidationException::class);

        app(ReceiptExtractionService::class)->extract($this->pdf(), $this->organisation()->id);
    }

    public function test_api_endpoint_returns_extracted_fields_for_review(): void
    {
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        $organisation = $this->organisation();
        $user = $this->user($organisation);
        Http::fake(['*' => Http::response($this->geminiResponse($this->validExtraction()))]);

        $this->actingAs($user, 'sanctum')->post('/api/v1/expenses/extract', [
            'file' => $this->pdf(),
        ])->assertOk()
            ->assertJsonPath('data.supplier', 'Ace Hardware')
            ->assertJsonPath('data.description', 'Cement 50kg')
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.provider', 'gemini');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), ':generateContent?key=gemini-test-key'));
    }

    public function test_api_extract_endpoint_rejects_unverified_users(): void
    {
        $organisation = $this->organisation();
        $user = $this->user($organisation, verified: false);

        $this->actingAs($user, 'sanctum')->withHeader('Accept', 'application/json')->post('/api/v1/expenses/extract', [
            'file' => $this->pdf(),
        ])->assertForbidden();
    }

    public function test_api_extract_endpoint_requires_a_file(): void
    {
        $organisation = $this->organisation();
        $user = $this->user($organisation);

        $this->actingAs($user, 'sanctum')->post('/api/v1/expenses/extract', [])->assertUnprocessable();
    }

    public function test_extracts_separate_items_and_flags_total_discrepancies(): void
    {
        config(['services.ai_provider' => 'gemini', 'services.gemini.key' => 'test']);
        $fields = $this->validExtraction();
        $fields['items'] = [
            ['description' => 'Cement', 'quantity' => 2, 'unit' => 'bags', 'rate' => 10, 'total' => 20],
            ['description' => 'Sand', 'quantity' => 1, 'unit' => 'load', 'rate' => 5, 'total' => 5],
        ];
        Http::fake(['*' => Http::response($this->geminiResponse($fields))]);
        $result = app(ReceiptExtractionService::class)->extract($this->pdf(), $this->organisation()->id);
        $this->assertCount(2, $result['items']);
        $this->assertSame('Sand', $result['items'][1]['description']);
        $this->assertStringContainsString('grand total', $result['warnings'][1]);
        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'handwritten receipts'));
    }

    public function test_batch_extraction_keeps_success_and_failure_results_separate(): void
    {
        config(['services.ai_provider' => 'gemini', 'services.gemini.key' => 'test']);
        Http::fake(['*' => Http::sequence()->push($this->geminiResponse($this->validExtraction()))->push([], 500)]);
        $user = $this->user($this->organisation());
        $this->actingAs($user, 'sanctum')->post('/api/v1/expenses/extract', ['files' => [$this->pdf(), $this->pdf()]])
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.data.supplier', 'Ace Hardware')->assertJsonPath('data.0.error', null)
            ->assertJsonPath('data.1.data', null)->assertJsonPath('data.1.index', 1);
        Http::assertSentCount(2);
    }

    public function test_batch_extraction_rejects_more_than_ten_receipts(): void
    {
        Http::fake();
        $user = $this->user($this->organisation());
        $this->actingAs($user, 'sanctum')->post('/api/v1/expenses/extract', ['files' => array_map(fn () => $this->pdf(), range(1, 11))])
            ->assertUnprocessable();
        Http::assertNothingSent();
    }
}
