<?php

namespace App\Services;

use App\Models\Boq;
use Illuminate\Validation\ValidationException;

class BoqExtractionService
{
    public function __construct(
        private readonly BoqSpreadsheetImporter $spreadsheetImporter,
        private readonly BoqDocumentExtractionService $documentExtractor,
    ) {}

    /**
     * @return array{count: int, warnings: list<string>, provider: string}
     */
    public function extract(Boq $boq): array
    {
        return match ($boq->source_type) {
            'excel' => [
                'count' => $this->spreadsheetImporter->import($boq),
                'warnings' => [],
                'provider' => 'spreadsheet',
            ],
            'pdf', 'scan' => $this->documentExtractor->extract($boq),
            default => throw ValidationException::withMessages([
                'boq' => 'Only Excel, CSV, PDF, and image BOQs can be generated automatically.',
            ]),
        };
    }
}
