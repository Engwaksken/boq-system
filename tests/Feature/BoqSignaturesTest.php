<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Signatures;
use App\Livewire\Profile\Signature as ProfileSignature;
use App\Mail\BoqSignatureRequested;
use App\Models\BoqSignature;
use App\Models\BoqSignedDocument;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\BoqPdfService;
use App\Services\BoqSignatureService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\CreatesSignatures;
use Tests\TestCase;

class BoqSignaturesTest extends TestCase
{
    use CreatesSignatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    /* ---------------------------- preparer ---------------------------- */

    public function test_preparer_can_draw_a_signature(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);

        Livewire::actingAs($user)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openCapture', 'preparer')
            ->assertSet('details.name', $user->name)
            ->set('drawnSignature', $this->signatureDataUrl())
            ->set('details.title', 'Quantity Surveyor')
            ->call('saveSignature')
            ->assertHasNoErrors()
            ->assertSet('capturing', null)
            ->assertSee('Quantity Surveyor');

        $signature = $boq->preparerSignature()->firstOrFail();
        $this->assertSame('drawn', $signature->method);
        $this->assertSame($user->id, $signature->user_id);
        $this->assertSame(now()->toDateString(), $signature->signed_at->toDateString());
        $this->assertStringStartsWith('signatures/boqs/'.$boq->id.'/', $signature->image_path);
        Storage::disk('public')->assertExists($signature->image_path);

        // Stored as a trimmed PNG: smaller than the 600x200 pad, transparent corners.
        $image = imagecreatefromstring(Storage::disk('public')->get($signature->image_path));
        $this->assertLessThan(600, imagesx($image));
        $this->assertLessThan(200, imagesy($image));
        $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7F);
    }

    public function test_preparer_can_upload_a_photo_of_a_signature(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);

        Livewire::actingAs($user)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openCapture', 'preparer')
            ->call('setMethod', 'upload')
            ->set('uploadedSignature', UploadedFile::fake()->createWithContent('signature.jpg', $this->signatureJpeg()))
            ->call('saveSignature')
            ->assertHasNoErrors();

        $signature = $boq->preparerSignature()->firstOrFail();
        $this->assertSame('uploaded', $signature->method);
        $this->assertStringEndsWith('.png', $signature->image_path);

        // The white paper is removed.
        $image = imagecreatefromstring(Storage::disk('public')->get($signature->image_path));
        $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7F);
    }

    public function test_svg_and_empty_signatures_are_rejected(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path d="M0 0L10 10"/></svg>';

        Livewire::actingAs($user)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openCapture', 'preparer')
            ->call('setMethod', 'upload')
            ->set('uploadedSignature', UploadedFile::fake()->createWithContent('signature.svg', $svg))
            ->call('saveSignature')
            ->assertHasErrors('uploadedSignature')
            ->call('setMethod', 'draw')
            ->set('drawnSignature', 'data:image/svg+xml;base64,'.base64_encode($svg))
            ->call('saveSignature')
            ->assertHasErrors('drawnSignature');

        // A blank (fully transparent) pad is not a signature.
        $blank = imagecreatetruecolor(300, 100);
        imagesavealpha($blank, true);
        imagealphablending($blank, false);
        imagefill($blank, 0, 0, imagecolorallocatealpha($blank, 0, 0, 0, 127));
        ob_start();
        imagepng($blank);
        $png = (string) ob_get_clean();

        Livewire::actingAs($user)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openCapture', 'preparer')
            ->set('drawnSignature', 'data:image/png;base64,'.base64_encode($png))
            ->call('saveSignature')
            ->assertHasErrors('drawnSignature');

        $this->assertSame(0, BoqSignature::count());
        $this->assertSame([], Storage::disk('public')->allFiles('signatures'));
    }

    public function test_replacing_a_signature_deletes_the_old_image(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);
        $service = app(BoqSignatureService::class);

        $first = $service->sign($boq, 'preparer', ['name' => 'A'], $this->signatureDataUrl(), $user);
        $oldPath = $first->image_path;
        $second = $service->sign($boq, 'preparer', ['name' => 'B'], $this->signatureDataUrl(), $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $boq->signatures()->count());
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($second->image_path);
    }

    public function test_saved_default_signature_is_reused_with_one_click(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);

        Livewire::actingAs($user)
            ->test(ProfileSignature::class)
            ->assertSet('replacing', true)
            ->set('drawnSignature', $this->signatureDataUrl())
            ->set('name', 'K. Wakabala')
            ->set('title', 'Senior QS')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('replacing', false);

        $user->refresh();
        $this->assertNotNull($user->signature_path);
        $this->assertStringStartsWith('signatures/users/'.$user->id.'/', $user->signature_path);
        Storage::disk('public')->assertExists($user->signature_path);

        Livewire::actingAs($user)
            ->test(Signatures::class, ['boq' => $boq])
            ->assertSee('Use my saved signature')
            ->call('useSavedSignature')
            ->assertHasNoErrors();

        $signature = $boq->preparerSignature()->firstOrFail();
        $this->assertSame('K. Wakabala', $signature->name);
        $this->assertSame('Senior QS', $signature->title);
        // The BOQ keeps its own copy, so changing the profile signature later does not alter it.
        $this->assertNotSame($user->signature_path, $signature->image_path);
        Storage::disk('public')->assertExists($signature->image_path);

        // It can still be replaced for this BOQ only.
        Livewire::actingAs($user)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openCapture', 'preparer')
            ->set('drawnSignature', $this->signatureDataUrl())
            ->set('details.name', 'Kenneth W.')
            ->call('saveSignature')
            ->assertHasNoErrors();

        $this->assertSame('Kenneth W.', $boq->preparerSignature()->first()->name);
        Storage::disk('public')->assertExists($user->fresh()->signature_path);
    }

    public function test_profile_has_a_signature_tab(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->get(route('profile.edit', ['tab' => 'signature']))
            ->assertOk()
            ->assertSee('My signature')
            ->assertSee('data-signature-pad', false);
    }

    /* ----------------------------- client ----------------------------- */

    private function clientLink($boq, User $user): string
    {
        Livewire::actingAs($user)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openRequest')
            ->assertSet('showRequestModal', true)
            ->assertSee('wa.me');

        $request = SignatureRequest::where('boq_id', $boq->id)->latest('id')->firstOrFail();
        $this->assertTrue($request->isOpen());
        $this->assertTrue($request->expires_at->between(now()->addDays(13), now()->addDays(15)));

        return (string) $request->url;
    }

    public function test_client_signs_through_the_link_once(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $url = $this->clientLink($boq, $owner);

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);

        // The token is not stored in plain text.
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertDatabaseMissing('signature_requests', ['token_hash' => $token]);
        $this->assertDatabaseHas('signature_requests', ['token_hash' => hash('sha256', $token)]);

        auth()->logout();

        $this->get($url)
            ->assertOk()
            ->assertSee('Clinic Block A')
            ->assertSee('Kampala Clinic')
            ->assertSee('Grand Total')
            ->assertSee('I approve this Bill of Quantities')
            ->assertSee('data-signature-pad', false);

        // The approval box must be ticked.
        $this->post($url, ['name' => 'Jane Achieng', 'signature_data' => $this->signatureDataUrl()])
            ->assertRedirect()
            ->assertSessionHasErrors('approve');
        $this->assertNull($boq->clientSignature()->first());

        $this->withHeader('User-Agent', 'ClientBrowser/1.0')
            ->post($url, [
                'name' => 'Jane Achieng',
                'title' => 'Director',
                'date' => now()->toDateString(),
                'approve' => '1',
                'signature_data' => $this->signatureDataUrl(),
            ])
            ->assertOk()
            ->assertSee('Thank you, the BOQ is signed');

        $signature = $boq->clientSignature()->firstOrFail();
        $this->assertSame('Jane Achieng', $signature->name);
        $this->assertSame('Director', $signature->title);
        $this->assertSame('127.0.0.1', $signature->ip);
        $this->assertSame('ClientBrowser/1.0', $signature->user_agent);
        $this->assertNull($signature->user_id);
        $this->assertTrue($signature->signedRemotely());
        $this->assertNotNull(SignatureRequest::first()->used_at);

        // The owner is told in the app.
        $notification = UserNotification::where('user_id', $owner->id)->where('type', 'boq_signed')->firstOrFail();
        $this->assertStringContainsString('Jane Achieng', $notification->message);
        $this->assertSame($boq->id, $notification->data['boq_id']);

        // The link cannot be used again.
        $this->get($url)->assertStatus(410)->assertSee('This BOQ has already been signed');
        $this->post($url, [
            'name' => 'Someone Else',
            'approve' => '1',
            'signature_data' => $this->signatureDataUrl(),
        ])->assertStatus(410);

        $this->assertSame('Jane Achieng', $boq->clientSignature()->first()->name);
        $this->assertSame(1, UserNotification::where('type', 'boq_signed')->count());
    }

    public function test_client_can_upload_a_signature_image_through_the_link(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $url = $this->clientLink($boq, $owner);
        auth()->logout();

        $this->post($url, [
            'name' => 'Jane Achieng',
            'approve' => '1',
            'signature_file' => UploadedFile::fake()->createWithContent('sig.png', $this->signaturePng(true)),
        ])->assertOk();

        $this->assertSame('uploaded', $boq->clientSignature()->first()->method);
    }

    public function test_expired_tampered_and_replaced_links_do_not_work(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $url = $this->clientLink($boq, $owner);
        auth()->logout();

        $this->get($url.'x')->assertForbidden();
        $this->get(str_replace('signature=', 'signature=0', $url))->assertForbidden();
        $this->get(url('/sign/boqs/'.str_repeat('a', 48)))->assertForbidden();

        $this->travel(BoqSignatureService::LINK_DAYS + 1)->days();

        $this->get($url)->assertStatus(410)->assertSee('This signing link has expired');
        $this->post($url, ['name' => 'Late', 'approve' => '1', 'signature_data' => $this->signatureDataUrl()])->assertStatus(410);
        $this->assertNull($boq->clientSignature()->first());

        $this->travelBack();

        // A new link replaces the old one.
        $service = app(BoqSignatureService::class);
        $first = $service->createRequest($boq, $owner);
        $second = $service->createRequest($boq, $owner);
        $this->get((string) $first->url)->assertStatus(410)->assertSee('no longer valid');
        $this->get((string) $second->url)->assertOk();
    }

    public function test_signing_link_can_be_emailed(): void
    {
        Mail::fake();
        $owner = $this->customer();
        $boq = $this->boqFor($owner);

        Livewire::actingAs($owner)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openRequest')
            ->set('requestEmail', 'client@example.com')
            ->set('requestMessage', 'Please sign by Friday.')
            ->call('sendRequestEmail')
            ->assertHasNoErrors()
            ->assertSet('showRequestModal', false);

        $link = SignatureRequest::firstOrFail();
        $this->assertSame('client@example.com', $link->email);

        Mail::assertSent(BoqSignatureRequested::class, function (BoqSignatureRequested $mail) use ($link) {
            return $mail->hasTo('client@example.com')
                && $mail->url === $link->url
                && str_contains($mail->render(), 'Please sign by Friday.');
        });
    }

    public function test_client_can_sign_in_person_on_the_boq_page(): void
    {
        $owner = $this->customer();
        $colleague = User::factory()->create(['organisation_id' => $owner->organisation_id]);
        $colleague->roles()->attach(\App\Models\Role::where('slug', 'user')->value('id'));
        $boq = $this->boqFor($owner);
        $link = app(BoqSignatureService::class)->createRequest($boq, $owner);

        $boq->project->assignments()->create([
            'user_id' => $colleague->id, 'role' => 'project-manager', 'assigned_by' => $owner->id,
        ]);

        Livewire::actingAs($colleague)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openCapture', 'client')
            ->set('drawnSignature', $this->signatureDataUrl())
            ->set('details.name', 'Jane Achieng')
            ->call('saveSignature')
            ->assertHasErrors('approved')
            ->set('approved', true)
            ->call('saveSignature')
            ->assertHasNoErrors();

        $signature = $boq->clientSignature()->firstOrFail();
        $this->assertSame($colleague->id, $signature->user_id);
        $this->assertFalse($signature->signedRemotely());
        // The outstanding link is no longer needed.
        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertTrue(UserNotification::where('user_id', $owner->id)->where('type', 'boq_signed')->exists());
    }

    public function test_owner_signing_in_person_is_not_notified_about_their_own_action(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);

        app(BoqSignatureService::class)->sign($boq, 'client', ['name' => 'Jane'], $this->signatureDataUrl(), $owner);

        $this->assertSame(0, UserNotification::where('type', 'boq_signed')->count());
    }

    /* -------------------------- signed copies ------------------------- */

    public function test_signed_copies_can_be_uploaded_downloaded_and_deleted(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF";

        $component = Livewire::actingAs($owner)
            ->test(Signatures::class, ['boq' => $boq])
            ->set('signedDocument', UploadedFile::fake()->createWithContent('Signed BOQ.pdf', $pdf))
            ->call('uploadSignedDocument')
            ->assertHasNoErrors()
            ->set('signedDocument', UploadedFile::fake()->createWithContent('photo.jpg', $this->signatureJpeg()))
            ->call('uploadSignedDocument')
            ->assertHasNoErrors()
            ->assertSee('Signed BOQ.pdf')
            ->assertSee('photo.jpg')
            ->assertSee('Version 2');

        $this->assertSame(2, $boq->signedDocuments()->count());
        $document = $boq->signedDocuments()->where('original_name', 'Signed BOQ.pdf')->firstOrFail();
        $this->assertSame('application/pdf', $document->mime);
        $this->assertSame($owner->id, $document->user_id);
        $this->assertSame(strlen($pdf), $document->size);

        // Private: on the default disk, never on the public disk.
        Storage::disk('local')->assertExists($document->path);
        Storage::disk('public')->assertMissing($document->path);

        $response = $this->actingAs($owner)->get(route('boqs.signed-documents.download', [$boq, $document]));
        $response->assertOk();
        $this->assertSame($pdf, $response->streamedContent());
        $this->assertStringContainsString('signed-boq.pdf', (string) $response->headers->get('content-disposition'));

        $component->call('confirmDeleteDocument', $document->id)
            ->call('deleteDocument');

        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->path);
        $this->assertSame(1, $boq->signedDocuments()->count());
    }

    public function test_signed_copies_must_be_pdf_or_images_up_to_20_mb(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);

        Livewire::actingAs($owner)
            ->test(Signatures::class, ['boq' => $boq])
            ->set('signedDocument', UploadedFile::fake()->create('notes.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
            ->call('uploadSignedDocument')
            ->assertHasErrors('signedDocument')
            ->set('signedDocument', UploadedFile::fake()->create('huge.pdf', 21 * 1024, 'application/pdf'))
            ->call('uploadSignedDocument')
            ->assertHasErrors('signedDocument');

        $this->assertSame(0, BoqSignedDocument::count());
    }

    public function test_other_users_cannot_see_or_change_signatures_or_signed_copies(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $document = app(BoqSignatureService::class)->storeSignedDocument(
            $boq,
            UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.4\n%%EOF"),
            $owner,
        );
        $stranger = $this->customer(); // another organisation

        $this->actingAs($stranger)
            ->get(route('boqs.signed-documents.download', [$boq, $document]))
            ->assertForbidden();

        Livewire::actingAs($stranger)
            ->test(Signatures::class, ['boq' => $boq])
            ->assertDontSee('signed.pdf')
            ->call('openCapture', 'preparer')
            ->assertForbidden();

        Livewire::actingAs($stranger)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('confirmDeleteDocument', $document->id)
            ->assertForbidden();

        Livewire::actingAs($stranger)
            ->test(Signatures::class, ['boq' => $boq])
            ->call('openRequest')
            ->assertForbidden();

        $this->assertModelExists($document);
        $this->assertSame(0, SignatureRequest::count());

        // A document id from another BOQ is not served through this BOQ.
        $otherBoq = $this->boqFor($owner);
        $this->actingAs($owner)
            ->get(route('boqs.signed-documents.download', [$otherBoq, $document]))
            ->assertNotFound();
    }

    public function test_view_only_users_see_signatures_but_cannot_change_them(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        app(BoqSignatureService::class)->sign($boq, 'preparer', ['name' => 'Owner Name'], $this->signatureDataUrl(), $owner);

        $viewer = User::factory()->create(['organisation_id' => $owner->organisation_id]);
        $viewer->roles()->attach(\App\Models\Role::where('slug', 'site-engineer')->value('id'));
        $boq->project->assignments()->create([
            'user_id' => $viewer->id, 'role' => 'site-engineer', 'assigned_by' => $owner->id,
        ]);
        $this->assertTrue($viewer->can('view', $boq));
        $this->assertFalse($viewer->can('update', $boq));

        Livewire::actingAs($viewer)
            ->test(Signatures::class, ['boq' => $boq])
            ->assertSee('Owner Name')
            ->assertDontSee('Replace signature')
            ->call('confirmRemoveSignature', 'preparer')
            ->assertForbidden();
    }

    public function test_boq_page_embeds_the_signatures_panel(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);

        $this->actingAs($owner)
            ->get(route('boqs.show', $boq))
            ->assertOk()
            ->assertSeeLivewire(Signatures::class)
            ->assertSee('Client signs here')
            ->assertSee('Signed copies');
    }

    /* ------------------------------- PDF ------------------------------ */

    public function test_pdf_has_a_signature_block_with_both_signatures(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $service = app(BoqSignatureService::class);
        $pdfs = app(BoqPdfService::class);

        // Unsigned: blank lines to sign by hand.
        $pdf = $pdfs->pdf($boq);
        $html = $pdfs->html($boq->fresh());
        $this->assertStringContainsString('class="signatures"', $html);
        $this->assertStringContainsString('Client / Approved by', $html);
        $this->assertStringNotContainsString('data:image/png;base64', $html);
        $this->assertStringStartsWith('%PDF', $pdf->output());

        $service->sign($boq, 'preparer', ['name' => 'Kenneth Wakabala', 'title' => 'Quantity Surveyor', 'date' => '2026-09-29'], $this->signatureDataUrl(), $owner);
        $service->sign($boq, 'client', ['name' => 'Jane Achieng', 'title' => 'Director', 'date' => '2026-09-30'], $this->signatureDataUrl(), $owner);

        $pdf = app(BoqPdfService::class)->pdf($boq->fresh());
        $html = $pdfs->html($boq->fresh());
        $bytes = $pdf->output();

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertGreaterThan(2000, strlen($bytes));
        $this->assertSame(2, substr_count($html, 'data:image/png;base64'));
        $this->assertStringContainsString('Kenneth Wakabala', $html);
        $this->assertStringContainsString('Quantity Surveyor', $html);
        $this->assertStringContainsString('Jane Achieng', $html);
        $this->assertStringContainsString('Director', $html);
        $this->assertStringNotContainsString('class="signoff"', $html);
        // Header and footer are unchanged.
        $this->assertStringContainsString('<header>', $html);
        $this->assertStringContainsString('<footer>', $html);
        $this->assertStringContainsString('BILL OF QUANTITIES', $html);
    }
    public function test_pdf_uses_the_saved_profile_signature_when_the_boq_is_not_signed(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $pdfs = app(BoqPdfService::class);

        // Name and title alone (no image yet) are shown too.
        $owner->forceFill(['signature_name' => 'K. Wakabala', 'signature_title' => 'Senior QS'])->save();
        $html = $pdfs->html($boq->fresh());
        $this->assertStringContainsString('K. Wakabala', $html);
        $this->assertStringContainsString('Senior QS', $html);
        $this->assertStringNotContainsString('data:image/png;base64', $html);

        Livewire::actingAs($owner)
            ->test(ProfileSignature::class)
            ->set('drawnSignature', $this->signatureDataUrl())
            ->set('name', 'K. Wakabala')
            ->set('title', 'Senior QS')
            ->call('save')
            ->assertHasNoErrors();

        $html = $pdfs->html($boq->fresh());
        $this->assertSame(1, substr_count($html, 'data:image/png;base64'));
        $this->assertStringContainsString('K. Wakabala', $html);
        $this->assertStringContainsString('Senior QS', $html);
        $this->assertStringStartsWith('%PDF', $pdfs->pdf($boq->fresh())->output());

        // A signature made on the BOQ itself wins over the profile default.
        app(BoqSignatureService::class)->sign($boq, 'preparer', ['name' => 'Kenneth W.', 'title' => 'QS'], $this->signatureDataUrl(), $owner);
        $html = $pdfs->html($boq->fresh());
        $this->assertStringContainsString('Kenneth W.', $html);
        $this->assertStringNotContainsString('Senior QS', $html);
    }

    public function test_pdf_download_is_never_cached(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);

        $response = $this->actingAs($owner)->get(route('boqs.pdf', $boq));
        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
