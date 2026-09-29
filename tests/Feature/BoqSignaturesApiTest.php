<?php

namespace Tests\Feature;

use App\Models\BoqSignature;
use App\Models\SignatureRequest;
use App\Services\BoqSignatureService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\CreatesSignatures;
use Tests\TestCase;

class BoqSignaturesApiTest extends TestCase
{
    use CreatesSignatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_signatures_can_be_listed_added_as_base64_or_multipart_and_deleted(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/boqs/{$boq->id}/signatures", [
            'role' => 'preparer',
            'name' => 'Kenneth Wakabala',
            'title' => 'Quantity Surveyor',
            'date' => '2026-09-29',
            'signature' => base64_encode($this->signaturePng()), // bare base64 is accepted too
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'preparer')
            ->assertJsonPath('data.method', 'drawn')
            ->assertJsonPath('data.signed_at', '2026-09-29');

        $this->post("/api/v1/boqs/{$boq->id}/signatures", [
            'role' => 'client',
            'name' => 'Jane Achieng',
            'signature_file' => UploadedFile::fake()->createWithContent('client.jpg', $this->signatureJpeg()),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.method', 'uploaded');

        $this->getJson("/api/v1/boqs/{$boq->id}/signatures")
            ->assertOk()
            ->assertJsonCount(2, 'data.signatures')
            ->assertJsonPath('data.signatures.0.role', 'preparer')
            ->assertJsonPath('data.client_request', null)
            ->assertJsonMissingPath('data.signatures.0.ip');

        $this->deleteJson("/api/v1/boqs/{$boq->id}/signatures/client")->assertOk();
        $this->assertNull($boq->clientSignature()->first());
        $this->deleteJson("/api/v1/boqs/{$boq->id}/signatures/client")->assertNotFound();
        $this->deleteJson("/api/v1/boqs/{$boq->id}/signatures/nobody")->assertNotFound();
    }

    public function test_invalid_signatures_are_rejected(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/boqs/{$boq->id}/signatures", ['role' => 'preparer', 'name' => 'A'])
            ->assertUnprocessable();
        $this->postJson("/api/v1/boqs/{$boq->id}/signatures", ['role' => 'witness', 'name' => 'A', 'signature' => $this->signatureDataUrl()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
        $this->postJson("/api/v1/boqs/{$boq->id}/signatures", [
            'role' => 'preparer',
            'name' => 'A',
            'signature' => 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ])->assertUnprocessable()->assertJsonValidationErrors('signature');
        $this->post("/api/v1/boqs/{$boq->id}/signatures", [
            'role' => 'preparer',
            'name' => 'A',
            'signature_file' => UploadedFile::fake()->createWithContent('sig.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('signature_file');

        $this->assertSame(0, BoqSignature::count());
    }

    public function test_preparer_can_use_the_saved_signature(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/boqs/{$boq->id}/signatures", ['role' => 'preparer', 'use_saved' => true])
            ->assertUnprocessable();

        app(BoqSignatureService::class)->saveDefault($user, $this->signatureDataUrl(), 'Saved Name', 'Engineer');

        $this->postJson("/api/v1/boqs/{$boq->id}/signatures", ['role' => 'preparer', 'use_saved' => true])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Saved Name')
            ->assertJsonPath('data.title', 'Engineer');

        $this->postJson("/api/v1/boqs/{$boq->id}/signatures", ['role' => 'client', 'use_saved' => true])
            ->assertStatus(422);
    }

    public function test_client_signing_link_can_be_created_emailed_and_cancelled(): void
    {
        Mail::fake();
        $user = $this->customer();
        $boq = $this->boqFor($user);
        Sanctum::actingAs($user);

        $first = $this->postJson("/api/v1/boqs/{$boq->id}/signatures/client-request")
            ->assertCreated()
            ->assertJsonPath('data.expires_in_days', 14)
            ->json('data');

        $this->assertStringContainsString('/sign/boqs/', $first['url']);
        $this->assertStringStartsWith('https://wa.me/?text=', $first['whatsapp_url']);

        // Same open link until a new one is asked for.
        $again = $this->postJson("/api/v1/boqs/{$boq->id}/signatures/client-request", ['email' => 'client@example.com'])
            ->assertCreated()
            ->json('data');
        $this->assertSame($first['url'], $again['url']);
        Mail::assertSent(\App\Mail\BoqSignatureRequested::class, fn ($mail) => $mail->hasTo('client@example.com'));

        $new = $this->postJson("/api/v1/boqs/{$boq->id}/signatures/client-request", ['new' => true])->json('data');
        $this->assertNotSame($first['url'], $new['url']);

        $this->getJson("/api/v1/boqs/{$boq->id}/signatures")->assertJsonPath('data.client_request.url', $new['url']);

        $this->deleteJson("/api/v1/boqs/{$boq->id}/signatures/client-request")->assertOk();
        $this->assertSame(0, SignatureRequest::open()->count());
    }

    public function test_signed_documents_can_be_uploaded_listed_downloaded_and_deleted(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);
        Sanctum::actingAs($user);
        $pdf = "%PDF-1.4\n%%EOF";

        $id = $this->post("/api/v1/boqs/{$boq->id}/signed-documents", [
            'file' => UploadedFile::fake()->createWithContent('signed.pdf', $pdf),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'signed.pdf')
            ->assertJsonPath('data.mime', 'application/pdf')
            ->assertJsonPath('data.uploaded_by.id', $user->id)
            ->assertJsonMissingPath('data.path')
            ->json('data.id');

        $this->getJson("/api/v1/boqs/{$boq->id}/signed-documents")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response = $this->get("/api/v1/boqs/{$boq->id}/signed-documents/{$id}/download");
        $response->assertOk();
        $this->assertSame($pdf, $response->streamedContent());

        $this->post("/api/v1/boqs/{$boq->id}/signed-documents", [
            'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->deleteJson("/api/v1/boqs/{$boq->id}/signed-documents/{$id}")->assertOk();
        $this->getJson("/api/v1/boqs/{$boq->id}/signed-documents")->assertJsonCount(0, 'data');
    }

    public function test_other_users_are_forbidden(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $service = app(BoqSignatureService::class);
        $service->sign($boq, 'preparer', ['name' => 'Owner'], $this->signatureDataUrl(), $owner);
        $document = $service->storeSignedDocument($boq, UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.4\n%%EOF"), $owner);

        Sanctum::actingAs($this->customer());

        $this->getJson("/api/v1/boqs/{$boq->id}/signatures")->assertForbidden();
        $this->postJson("/api/v1/boqs/{$boq->id}/signatures", ['role' => 'preparer', 'name' => 'X', 'signature' => $this->signatureDataUrl()])->assertForbidden();
        $this->deleteJson("/api/v1/boqs/{$boq->id}/signatures/preparer")->assertForbidden();
        $this->postJson("/api/v1/boqs/{$boq->id}/signatures/client-request")->assertForbidden();
        $this->getJson("/api/v1/boqs/{$boq->id}/signed-documents")->assertForbidden();
        $this->get("/api/v1/boqs/{$boq->id}/signed-documents/{$document->id}/download", ['Accept' => 'application/json'])->assertForbidden();
        $this->deleteJson("/api/v1/boqs/{$boq->id}/signed-documents/{$document->id}")->assertForbidden();

        $this->assertNotNull($boq->preparerSignature()->first());
        $this->assertModelExists($document);
        $this->assertSame(0, SignatureRequest::count());
    }

    public function test_guests_are_unauthenticated(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);

        $this->getJson("/api/v1/boqs/{$boq->id}/signatures")->assertUnauthorized();
        $this->getJson("/api/v1/boqs/{$boq->id}/signed-documents")->assertUnauthorized();
    }
}
