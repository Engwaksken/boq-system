<?php

namespace App\Services;

use App\Models\Boq;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use Throwable;

class BoqSpreadsheetImporter
{
    private const MAX_COLUMNS = 100;

    private const MAX_ROWS = 10000;

    private const MAX_SCANNED_ROWS = 20000;

    public function import(Boq $boq): int
    {
        if ($boq->source_type !== 'excel' || ! $boq->source_file_path) {
            throw ValidationException::withMessages([
                'boq' => 'Only Excel and CSV BOQs can be generated automatically.',
            ]);
        }

        $raw = str_replace('\\', '/', $boq->source_file_path);
        if (str_starts_with($raw, '/') || preg_match('/^[A-Za-z]:\//', $raw) || in_array('..', explode('/', $raw), true)) {
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file path is invalid.']);
        }
        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($raw)) {
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file could not be found.']);
        }
        // For S3 etc. need local temp copy; for local disk use concrete path
        $path = method_exists($disk, 'path') ? $disk->path($raw) : Storage::path($raw);
        if (! is_file($path)) {
            // Fallback for fake disks in tests
            $path = Storage::path($raw);
            if (! is_file($path)) {
                throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file could not be found.']);
            }
        }
        if (filesize($path) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['boq' => 'The spreadsheet must be no larger than 10 MB.']);
        }

        $reader = null;
        $rows = [];
        $foundHeaders = false;
        $scannedRows = 0;

        try {
            $reader = ReaderFactory::createFromFile($path);
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;

                foreach ($sheet->getRowIterator() as $row) {
                    $scannedRows++;

                    if ($scannedRows > self::MAX_SCANNED_ROWS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet is too large to process safely.']);
                    }

                    $values = array_map(
                        fn ($cell) => trim((string) ($cell->getValue() ?? '')),
                        $row->getCells()
                    );

                    if (count($values) > self::MAX_COLUMNS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet contains too many columns.']);
                    }

                    if ($headers === null) {
                        $candidate = array_map(fn ($value) => strtoupper($value), $values);

                        if ($this->headerIndex($candidate, ['DESCRIPTION']) !== null
                            && $this->headerIndex($candidate, ['QUANTITY', 'QTY']) !== null) {
                            $headers = $candidate;
                            $foundHeaders = true;
                        }

                        continue;
                    }

                    $description = $this->value($values, $headers, ['DESCRIPTION']);

                    if ($description === '') {
                        continue;
                    }

                    if (mb_strlen($description) > 2000 || count($rows) >= self::MAX_ROWS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet is too large to process safely.']);
                    }

                    $quantity = $this->number($this->value($values, $headers, ['QUANTITY', 'QTY']), 'quantity', true);
                    $rate = $this->number($this->value($values, $headers, ['RATE']), 'rate');
                    $amount = $this->number($this->value($values, $headers, ['AMOUNT']), 'amount');

                    $rows[] = [
                        'boq_id' => $boq->id,
                        'item_code' => $this->value($values, $headers, ['ITEM', 'ITEM CODE']) ?: null,
                        'description' => $description,
                        'unit' => $this->value($values, $headers, ['UNIT']) ?: null,
                        'quantity' => $quantity,
                        'original_rate' => $rate,
                        'approved_rate' => null,
                        'amount' => $amount ?? $quantity * ($rate ?? 0),
                        'currency' => $boq->currency,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ could not be read.']);
        } finally {
            if ($reader) {
                try {
                    $reader->close();
                } catch (Throwable) {
                    // The original read error is more useful than a cleanup failure.
                }
            }
        }

        if (! $foundHeaders) {
            throw ValidationException::withMessages([
                'boq' => 'The spreadsheet must contain Description and Quantity (or Qty) columns.',
            ]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['boq' => 'No BOQ items were found in the spreadsheet.']);
        }

        DB::transaction(function () use ($boq, $rows): void {
            $boq->items()->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('boq_items')->insert($chunk);
            }
        });

        return count($rows);
    }

    private function value(array $values, array $headers, array $names): string
    {
        $index = $this->headerIndex($headers, $names);

        return $index === null ? '' : ($values[$index] ?? '');
    }

    private function headerIndex(array $headers, array $names): ?int
    {
        foreach ($headers as $index => $header) {
            $normalized = trim($header);
            foreach ($names as $name) {
                if ($normalized === $name) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function number(string $value, string $column, bool $required = false): ?float
    {
        $normalised = str_replace([',', ' '], '', $value);

        if ($normalised === '' && ! $required) {
            return null;
        }

        if ($normalised === '' || ! is_numeric($normalised)) {
            throw ValidationException::withMessages([
                'boq' => "The {$column} column contains a non-numeric value.",
            ]);
        }

        return (float) $normalised;
    }
}
