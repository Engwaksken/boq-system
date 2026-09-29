<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boq;
use App\Models\BoqSignature;
use App\Models\SignatureRequest;
use App\Services\BoqSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * BOQ signatures for the mobile app. Listing needs BoqPolicy::view; signing,
 * removing and creating client links need BoqPolicy::update.
 */
class BoqSignatureController extends Controller
{
    public function __construct(private BoqSignatureService $signatures) {}

    public function index(Request $request, Boq $boq): JsonResponse
    {
        $this->authorize('view', $boq);

        $link = $request->user()->can('update', $boq) ? $this->signatures->activeRequest($boq) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'signatures' => $boq->signatures()->orderBy('role', 'desc')->get()->map(fn (BoqSignature $s) => $this->present($s))->values(),
                'client_request' => $link ? $this->presentRequest($boq, $link) : null,
                'saved_signature_url' => $request->user()->signatureUrl(),
            ],
        ]);
    }

    /**
     * Adds or replaces a signature. Send the image as `signature` (a PNG/JPEG/WebP
     * data URL or bare base64) or as a multipart `signature_file`; or, for the
     * preparer, `use_saved=1` to use the saved profile signature.
     */
    public function store(Request $request, Boq $boq): JsonResponse
    {
        $this->authorize('update', $boq);

        $data = $request->validate([
            'role' => ['required', Rule::in(BoqSignature::ROLES)],
            'name' => ['required_unless:use_saved,1,true', 'nullable', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
            'date' => ['nullable', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'use_saved' => ['sometimes', 'boolean'],
            'signature' => ['nullable', 'string', 'max:3000000'],
            'signature_file' => ['nullable', ...BoqSignatureService::IMAGE_RULES],
        ]);

        if ($request->boolean('use_saved')) {
            abort_unless($data['role'] === BoqSignature::ROLE_PREPARER, 422, 'Only the preparer can use a saved signature.');
            $signature = $this->signatures->signWithSaved($boq, $request->user(), $request, $data);
        } else {
            $source = $request->file('signature_file') ?? ($data['signature'] ?? null);

            if ($source === null || $source === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Send the signature as "signature" (base64 PNG) or "signature_file".',
                    'errors' => ['signature' => ['Draw or upload a signature first.']],
                ], 422);
            }

            $signature = $this->signatures->sign($boq, $data['role'], $data, $source, $request->user(), $request);
        }

        return response()->json(['success' => true, 'data' => $this->present($signature)], 201);
    }

    public function destroy(Request $request, Boq $boq, string $role): JsonResponse
    {
        $this->authorize('update', $boq);
        abort_unless(in_array($role, BoqSignature::ROLES, true), 404);

        $signature = $boq->signatures()->where('role', $role)->firstOrFail();
        $this->signatures->remove($signature);

        return response()->json(['success' => true, 'message' => 'Signature removed.']);
    }

    /**
     * The client signing link (the open one, or a new one with `new=1`).
     * With `email`, it is also emailed to the client.
     */
    public function requestClient(Request $request, Boq $boq): JsonResponse
    {
        $this->authorize('update', $boq);

        $data = $request->validate([
            'new' => ['sometimes', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $link = $request->boolean('new')
            ? $this->signatures->createRequest($boq, $request->user())
            : $this->signatures->currentOrNewRequest($boq, $request->user());

        if (! empty($data['email'])) {
            try {
                $this->signatures->emailRequest($boq, $link, $request->user(), $data['email'], $data['message'] ?? null);
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'success' => false,
                    'error_code' => 'SHARE_FAILED',
                    'message' => 'The email could not be sent. Please try again later.',
                    'data' => $this->presentRequest($boq, $link),
                ], 502);
            }
        }

        return response()->json(['success' => true, 'data' => $this->presentRequest($boq, $link)], 201);
    }

    public function cancelClientRequest(Request $request, Boq $boq): JsonResponse
    {
        $this->authorize('update', $boq);
        $this->signatures->revokeRequests($boq);

        return response()->json(['success' => true, 'message' => 'Signing link cancelled.']);
    }

    private function present(BoqSignature $signature): array
    {
        return [
            'id' => $signature->id,
            'role' => $signature->role,
            'name' => $signature->name,
            'title' => $signature->title,
            'signed_at' => $signature->signed_at?->toDateString(),
            'method' => $signature->method,
            'signed_remotely' => $signature->signedRemotely(),
            'image_url' => $signature->image_url,
            'user_id' => $signature->user_id,
            'created_at' => $signature->created_at?->toIso8601String(),
            'updated_at' => $signature->updated_at?->toIso8601String(),
        ];
    }

    private function presentRequest(Boq $boq, SignatureRequest $link): array
    {
        return [
            'url' => $link->url,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'expires_in_days' => BoqSignatureService::LINK_DAYS,
            'email' => $link->email,
            'message' => $this->signatures->requestMessage($boq, (string) $link->url),
            'whatsapp_url' => $this->signatures->whatsappUrl($boq, (string) $link->url),
        ];
    }
}
