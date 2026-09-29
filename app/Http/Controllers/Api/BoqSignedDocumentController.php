<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boq;
use App\Models\BoqSignedDocument;
use App\Services\BoqSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploaded copies of the physically signed BOQ, for the mobile app. Listing
 * and downloading need BoqPolicy::view; uploading and deleting need update.
 */
class BoqSignedDocumentController extends Controller
{
    public function __construct(private BoqSignatureService $signatures) {}

    public function index(Boq $boq): JsonResponse
    {
        $this->authorize('view', $boq);

        return response()->json([
            'success' => true,
            'data' => $boq->signedDocuments()->with('user:id,name')->get()->map(fn (BoqSignedDocument $d) => $this->present($boq, $d))->values(),
        ]);
    }

    public function store(Request $request, Boq $boq): JsonResponse
    {
        $this->authorize('update', $boq);

        $request->validate([
            'file' => ['required', ...BoqSignatureService::DOCUMENT_RULES],
        ], ['file.max' => 'The signed copy may not be larger than 20 MB.']);

        $document = $this->signatures->storeSignedDocument($boq, $request->file('file'), $request->user());

        return response()->json(['success' => true, 'data' => $this->present($boq, $document->load('user:id,name'))], 201);
    }

    public function download(Boq $boq, BoqSignedDocument $document): StreamedResponse
    {
        $this->authorize('view', $boq);
        abort_unless($document->boq_id === $boq->id, 404);

        $disk = Storage::disk($document->disk ?: config('filesystems.default'));
        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $this->signatures->downloadName($document), [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
        ]);
    }

    public function destroy(Boq $boq, BoqSignedDocument $document): JsonResponse
    {
        $this->authorize('update', $boq);
        abort_unless($document->boq_id === $boq->id, 404);

        $this->signatures->deleteSignedDocument($document);

        return response()->json(['success' => true, 'message' => 'Signed copy deleted.']);
    }

    private function present(Boq $boq, BoqSignedDocument $document): array
    {
        return [
            'id' => $document->id,
            'original_name' => $document->original_name,
            'size' => $document->size,
            'mime' => $document->mime,
            'uploaded_by' => $document->user ? ['id' => $document->user->id, 'name' => $document->user->name] : null,
            'uploaded_at' => $document->created_at?->toIso8601String(),
            'download_url' => url('/api/v1/boqs/'.$boq->id.'/signed-documents/'.$document->id.'/download'),
        ];
    }
}
