<?php

namespace App\Services;

use App\Models\Boq;
use App\Models\BoqItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Applies a file of estimated prices (one rate per BOQ item) to a BOQ.
 *
 * Rows are matched to items by the "ID" column from the template, then by
 * item code, then by description. The rate becomes the item's estimate
 * (original_rate) and its amount is recalculated.
 */
class BoqEstimateImporter
{
    private const MAX_ROWS = 10000;

    private const HEADER_SCAN_ROWS = 40;

    public function __construct(
        private BoqUploadNormalizer $normalizer,
        private BoqSpreadsheetImporter $spreadsheets,
    ) {}

    /** CSV template listing the BOQ's items with an empty "Estimated Rate" column. */
    public function template(Boq $boq): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['ID', 'Item', 'Description', 'Unit', 'Quantity', 'Estimated Rate'], ',', '"', '');

        $boq->items()->orderBy('id')->each(function (BoqItem $item) use ($out): void {
            fputcsv($out, [
                $item->id,
                $item->item_code,
                $item->description,
                $item->unit,
                (float) $item->quantity,
                $item->original_rate !== null ? (float) $item->original_rate : '',
            ], ',', '"', '');
        });

        rewind($out);
        $csv = "\xEF\xBB\xBF".stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * @return array{updated: int, unchanged: int, unmatched: array<int, string>, rows: int}
     *
     * @throws ValidationException
     */
    public function import(Boq $boq, UploadedFile $file): array
    {
        $stored = $this->normalizer->store($file, "boq-estimates/{$boq->id}");

        if (! in_array($stored['extension'], ['xlsx', 'csv'], true)) {
            Storage::delete($stored['path']);
            throw ValidationException::withMessages(['file' => 'Upload the estimated prices as an Excel (.xlsx) or CSV file.']);
        }

        $items = $boq->items()->get(['id', 'item_code', 'description', 'quantity', 'original_rate']);
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'This BOQ has no items yet. Import the BOQ items first.']);
        }

        $byId = $items->keyBy('id');
        $byCode = $items->filter(fn ($i) => $i->item_code !== null && $i->item_code !== '')
            ->groupBy(fn ($i) => $this->key($i->item_code));
        $byDescription = $items->groupBy(fn ($i) => $this->key($i->description));

        $rates = [];
        $unmatched = [];
        $rows = 0;

        $reader = $this->spreadsheets->readerFor(Storage::path($stored['path']));

        try {
            $reader->open(Storage::path($stored['path']));

            foreach ($reader->getSheetIterator() as $sheet) {
                $columns = null;
                $scanned = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(fn ($cell) => $this->spreadsheets->getCellValue($cell), $row->getCells());

                    if ($columns === null) {
                        if (++$scanned > self::HEADER_SCAN_ROWS) {
                            break;
                        }
                        $columns = $this->detectColumns($values);
                        continue;
                    }

                    $cell = fn (string $key) => isset($columns[$key]) ? trim((string) ($values[$columns[$key]] ?? '')) : '';
                    $rate = $this->number($cell('rate'));
                    if ($rate === null) {
                        continue; // no estimate on this row
                    }

                    if (++$rows > self::MAX_ROWS) {
                        throw ValidationException::withMessages(['file' => 'The file has too many rows.']);
                    }

                    $item = $this->match($cell('id'), $cell('item'), $cell('description'), $byId, $byCode, $byDescription, $rates);
                    if ($item === null) {
                        $label = $cell('description') ?: $cell('item') ?: $cell('id');
                        if (count($unmatched) < 50 && $label !== '') {
                            $unmatched[] = mb_substr($label, 0, 120);
                        }
                        continue;
                    }

                    $rates[$item->id] = $rate;
                }

                if ($columns !== null) {
                    break; // the first sheet with a rate column holds the estimates
                }
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['file' => 'The estimated prices file could not be read. Save it as .xlsx or .csv and try again.']);
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
            }
        }

        if ($rows === 0) {
            throw ValidationException::withMessages(['file' => 'No estimated rates were found. Use the template, or add a column named "Estimated Rate" (or "Rate") with a Description or Item column.']);
        }

        $updated = 0;
        DB::transaction(function () use ($boq, $rates, $byId, &$updated): void {
            foreach ($rates as $id => $rate) {
                $item = $byId[$id];
                if ($item->original_rate !== null && abs((float) $item->original_rate - $rate) < 0.005) {
                    continue;
                }
                BoqItem::whereKey($id)->update([
                    'original_rate' => $rate,
                    'amount' => round((float) $item->quantity * $rate, 2),
                    'updated_at' => now(),
                ]);
                $updated++;
            }

            $boq->summaries()->delete();
            $this->spreadsheets->buildSummaries($boq);

            $metadata = $boq->metadata ?? [];
            $metadata['estimates_uploaded_at'] = now()->toIso8601String();
            $boq->update(['metadata' => $metadata]);
        });

        return [
            'updated' => $updated,
            'unchanged' => count($rates) - $updated,
            'unmatched' => $unmatched,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, int>|null
     */
    private function detectColumns(array $values): ?array
    {
        $columns = [];

        foreach ($values as $index => $value) {
            $name = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z ]/', ' ', preg_replace('/\(.*?\)/', ' ', strtoupper((string) $value)) ?? '') ?? '') ?? '');

            $key = match (true) {
                $name === 'ID' || $name === 'ITEM ID' => 'id',
                (bool) preg_match('/^(ESTIMATED|ESTIMATE|EST)( UNIT)? (RATE|PRICE|COST)$|^(ESTIMATE|ESTIMATED RATE)$|^(UNIT RATE|RATE|UNIT PRICE|PRICE)$/', $name) => 'rate',
                (bool) preg_match('/^(ITEM )?(DESCRIPTION|DESC|PARTICULARS)\b/', $name) => 'description',
                (bool) preg_match('/^(ITEM|ITEM NO|ITEM CODE|CODE|REF|NO)$/', $name) => 'item',
                default => null,
            };

            // Prefer an "Estimated ..." column over a plain "Rate" one.
            if ($key === 'rate' && isset($columns['rate']) && ! str_starts_with($name, 'EST')) {
                continue;
            }
            if ($key !== null && ($key === 'rate' || ! isset($columns[$key]))) {
                $columns[$key] = $index;
            }
        }

        $identifies = isset($columns['id']) || isset($columns['item']) || isset($columns['description']);

        return isset($columns['rate']) && $identifies ? $columns : null;
    }

    private function match(string $id, string $code, string $description, $byId, $byCode, $byDescription, array $taken): ?BoqItem
    {
        if ($id !== '' && ctype_digit($id) && $byId->has((int) $id)) {
            return $byId[(int) $id];
        }

        // Codes and descriptions can repeat: take the first item not matched yet.
        foreach ([[$byCode, $code], [$byDescription, $description]] as [$index, $value]) {
            if ($value === '') {
                continue;
            }
            $candidates = $index->get($this->key($value));
            if ($candidates) {
                return $candidates->first(fn ($item) => ! isset($taken[$item->id])) ?? $candidates->first();
            }
        }

        return null;
    }

    private function key(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }

    private function number(string $value): ?float
    {
        $text = preg_replace('/\/=|\b(UGX|USH|USHS|KES|KSH|TZS|RWF|USD|EUR|SHS?)\b|[$€£\s,]/iu', '', trim($value)) ?? '';

        return $text !== '' && is_numeric($text) && (float) $text >= 0 ? (float) $text : null;
    }
}
