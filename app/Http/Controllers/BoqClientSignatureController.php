<?php

namespace App\Http\Controllers;

use App\Models\BoqSignature;
use App\Models\SignatureRequest;
use App\Services\BoqPdfService;
use App\Services\BoqShareService;
use App\Services\BoqSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The public page a client opens from a signing link: a summary of the BOQ,
 * the PDF, and a form to sign it. Links are signed, expire after
 * BoqSignatureService::LINK_DAYS days and work once.
 */
class BoqClientSignatureController extends Controller
{
    public function __construct(private BoqSignatureService $signatures) {}

    public function show(Request $request, string $token, BoqPdfService $pdfs, BoqShareService $shares): Response
    {
        [$link, $problem] = $this->resolve($request, $token);

        if ($problem !== null) {
            return $this->unavailable($problem, $link);
        }

        $boq = $link->boq;

        return response()->view('signatures.client-sign', [
            'boq' => $boq,
            'link' => $link,
            'company' => $boq->brandingIdentity() ?? [],
            'totals' => $pdfs->totals($boq),
            'pdfUrl' => $shares->link($boq),
            'formAction' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, string $token): Response|RedirectResponse
    {
        [$link, $problem] = $this->resolve($request, $token);

        if ($problem !== null) {
            return $this->unavailable($problem, $link);
        }

        $validator = Validator::make($request->all(), BoqSignatureService::detailRules() + [
            'approve' => ['accepted'],
            'signature_data' => ['nullable', 'string', 'max:3000000', 'required_without:signature_file'],
            'signature_file' => ['nullable', ...BoqSignatureService::IMAGE_RULES],
        ], [
            'approve.accepted' => __('Tick "I approve this Bill of Quantities" to sign.'),
            'signature_data.required_without' => __('Draw your signature or upload an image of it.'),
        ]);

        // The drawing is not flashed back: it is large and the pad starts empty.
        if ($validator->fails()) {
            return redirect()->to($request->fullUrl())
                ->withErrors($validator)
                ->withInput($request->except(['signature_data', 'signature_file']));
        }

        $data = $validator->validated();

        try {
            $this->signatures->sign(
                $link->boq,
                BoqSignature::ROLE_CLIENT,
                $data,
                $request->file('signature_file') ?? (string) $data['signature_data'],
                null,
                $request,
                $link,
            );
        } catch (ValidationException $e) {
            $field = $request->hasFile('signature_file') ? 'signature_file' : 'signature_data';

            return redirect()->to($request->fullUrl())
                ->withErrors([$field => collect($e->errors())->flatten()->first()])
                ->withInput($request->except(['signature_data', 'signature_file']));
        }

        return response()->view('signatures.client-signed', [
            'boq' => $link->boq,
            'company' => $link->boq->brandingIdentity() ?? [],
            'signature' => $link->boq->clientSignature()->first(),
        ]);
    }

    /**
     * The request behind a token, and why it cannot be used (null when it can).
     *
     * @return array{0: ?SignatureRequest, 1: ?string}
     */
    private function resolve(Request $request, string $token): array
    {
        $link = $this->signatures->findRequest($token);

        if ($link === null || $link->boq === null || ! URL::hasCorrectSignature($request)) {
            return [null, 'invalid'];
        }

        if ($link->used_at !== null) {
            return [$link, 'used'];
        }

        if ($link->revoked_at !== null) {
            return [$link, 'revoked'];
        }

        if (! URL::signatureHasNotExpired($request) || ! $link->expires_at?->isFuture()) {
            return [$link, 'expired'];
        }

        return [$link, null];
    }

    private function unavailable(string $reason, ?SignatureRequest $link): Response
    {
        $status = match ($reason) {
            'invalid' => 403,
            default => 410,
        };

        return response()->view('signatures.client-unavailable', [
            'reason' => $reason,
            'boq' => $link?->boq,
        ], $status);
    }
}
