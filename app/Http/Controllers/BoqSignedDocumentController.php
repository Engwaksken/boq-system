<?php

namespace App\Http\Controllers;

use App\Models\Boq;
use App\Models\BoqSignedDocument;
use App\Services\BoqSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads of uploaded signed copies. The files are private (default disk),
 * so they are only served here, to users who can view the BOQ.
 */
class BoqSignedDocumentController extends Controller
{
    public function download(Request $request, Boq $boq, BoqSignedDocument $document, BoqSignatureService $signatures): StreamedResponse
    {
        abort_unless($document->boq_id === $boq->id, 404);
        abort_unless($request->user()?->can('view', $boq), 403);

        $disk = Storage::disk($document->disk ?: config('filesystems.default'));
        abort_unless($disk->exists($document->path), 404);

        $name = $signatures->downloadName($document);

        return $request->boolean('inline')
            ? $disk->response($document->path, $name, ['Content-Type' => $document->mime ?: 'application/octet-stream'])
            : $disk->download($document->path, $name, ['Content-Type' => $document->mime ?: 'application/octet-stream']);
    }
}
