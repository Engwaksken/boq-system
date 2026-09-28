<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqSummary;
use App\Models\Element;
use App\Models\Facility;
use App\Models\SubElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use Throwable;

class BoqSpreadsheetImporter
{
    private const MAX_COLUMNS = 100;
    private const MAX_ROWS = 10000;
    private const MAX_SCANNED_ROWS = 20000;

    /** Rows searched for the column headings before a sheet is skipped. */
    private const HEADER_SCAN_ROWS = 40;

    public function import(Boq $boq): int
    {
        if ($boq->source_type !== 'excel' || !$boq->source_file_path) {
            throw ValidationException::withMessages([
                'boq' => 'Only Excel and CSV BOQs can be generated automatically.',
            ]);
        }

        $raw = str_replace('\\', '/', $boq->source_file_path);
        if (str_starts_with($raw, '/') || preg_match('/^[A-Za-z]:\//', $raw) || in_array('..', explode('/', $raw), true)) {
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file path is invalid.']);
        }
        $disk = Storage::disk(config('filesystems.default'));
        if (!$disk->exists($raw)) {
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file could not be found.']);
        }
        $path = method_exists($disk, 'path') ? $disk->path($raw) : Storage::path($raw);
        if (!is_file($path)) {
            $path = Storage::path($raw);
            if (!is_file($path)) {
                throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file could not be found.']);
            }
        }
        if (filesize($path) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['boq' => 'The spreadsheet must be no larger than 10 MB.']);
        }

        $reader = null;
        $scannedRows = 0;
        $allRows = [];
        $sheetMetadata = [];
        $detectedLanguage = null;
        $translationMetadata = [];

        try {
            $reader = $this->readerFor($path);
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheetIndex => $sheet) {
                $columns = null;
                $headerScan = 0;
                $sheetItems = 0;
                $facility = null;
                $bill = null;
                $element = null;
                $subElement = null;
                $order = ['facility' => 0, 'bill' => 0, 'element' => 0, 'sub_element' => 0];

                foreach ($sheet->getRowIterator() as $row) {
                    $scannedRows++;
                    if ($scannedRows > self::MAX_SCANNED_ROWS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet is too large to process safely.']);
                    }

                    $values = array_map(fn ($cell) => $this->getCellValue($cell), $row->getCells());

                    if (count($values) > self::MAX_COLUMNS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet contains too many columns.']);
                    }

                    if ($columns === null) {
                        // Title rows often come before the column headings.
                        if (++$headerScan > self::HEADER_SCAN_ROWS) {
                            break;
                        }
                        $columns = $this->detectColumns($values);
                        continue;
                    }

                    $cell = fn (string $key) => isset($columns[$key]) ? trim((string) ($values[$columns[$key]] ?? '')) : '';
                    $description = preg_replace('/\s+/u', ' ', $cell('description')) ?? '';

                    if ($description === '' || $this->detectColumns($values) !== null) {
                        continue; // blank line, or the headings repeated on a new page
                    }

                    if (mb_strlen($description) > 2000 || count($allRows) >= self::MAX_ROWS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet is too large to process safely.']);
                    }

                    $quantityText = $cell('quantity');
                    $quantity = $this->parseNumber($quantityText);
                    $rate = $this->parseNumber($cell('rate'));
                    $amount = $this->parseNumber($cell('amount'));
                    $hasFigures = $quantity !== null || $rate !== null || $amount !== null;

                    if (! $hasFigures) {
                        if ($detectedLanguage === null && $this->looksLikeLanguageHeader($description, $values, $columns['headers'])) {
                            $detectedLanguage = $this->extractLanguage($values, $columns['headers']);
                            continue;
                        }

                        $level = $this->hierarchyLevel($description);
                        if ($level !== null) {
                            $name = mb_substr($level[1], 0, 250);
                            $order[$level[0]]++;
                            $node = ['name' => $name, 'order' => $order[$level[0]], 'translations' => $this->extractTranslations($values, $columns['headers'])];
                            match ($level[0]) {
                                'facility' => [$facility, $bill, $element, $subElement] = [$node, null, null, null],
                                'bill' => [$bill, $element, $subElement] = [$node, null, null],
                                'element' => [$element, $subElement] = [$node, null],
                                'sub_element' => $subElement = $node,
                            };
                        }

                        continue; // headings, notes and specification text
                    }

                    if ($this->isTotalRow($description)) {
                        continue; // "Carried to summary", "Sub-total", ...
                    }

                    // Lump sums and provisional sums: "Item", "LS", "Sum" or no quantity.
                    if ($quantity === null) {
                        if ($rate === null && $amount === null) {
                            continue;
                        }
                        $quantity = 1.0;
                        $rate ??= $amount;
                    }

                    if ($amount === null) {
                        $amount = $rate !== null ? round($quantity * $rate, 2) : 0;
                    } elseif ($rate === null && $quantity > 0) {
                        $rate = round($amount / $quantity, 2);
                    }

                    $allRows[] = [
                        'boq_id' => $boq->id,
                        'item_code' => mb_substr($cell('item'), 0, 100) ?: null,
                        'description' => $description,
                        'unit' => mb_substr($this->unitText($cell('unit'), $quantityText), 0, 50) ?: null,
                        'quantity' => $quantity,
                        'original_rate' => $rate,
                        'approved_rate' => null,
                        'amount' => round($amount, 2),
                        'currency' => $boq->currency,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                        '_facility' => $facility,
                        '_bill' => $bill,
                        '_element' => $element,
                        '_sub_element' => $subElement,
                    ];
                    $sheetItems++;
                }

                $sheetMetadata[] = [
                    'sheet_index' => $sheetIndex,
                    'sheet_name' => $sheet->getName(),
                    'rows_processed' => $sheetItems,
                    'columns_found' => $columns !== null,
                ];
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['boq' => $this->unreadableMessage($path)]);
        } finally {
            if ($reader) {
                try {
                    $reader->close();
                } catch (Throwable) {
                }
            }
        }

        if ($allRows === []) {
            $found = collect($sheetMetadata)->contains('columns_found', true);

            throw ValidationException::withMessages(['boq' => $found
                ? 'No BOQ items were found in the spreadsheet. Check that item rows have a quantity, rate or amount.'
                : 'The BOQ columns were not found. Add a heading row with Description and Quantity (or Qty) columns, then upload again.']);
        }

        DB::transaction(function () use ($boq, $allRows, $sheetMetadata, $detectedLanguage, $translationMetadata): void {
            $boq->items()->delete();
            $boq->facilities()->delete();
            $boq->summaries()->delete();

            $cache = [];
            $node = function (string $type, ?array $data, ?int $parentId, string $defaultName) use ($boq, &$cache): ?int {
                if ($data === null && $defaultName === '') {
                    return null;
                }
                $data ??= ['name' => $defaultName, 'order' => 0, 'translations' => null];
                $key = $type.'|'.$parentId.'|'.mb_strtolower($data['name']);

                return $cache[$key] ??= match ($type) {
                    'facility' => Facility::create(['boq_id' => $boq->id, 'name' => $data['name'], 'name_translations' => $data['translations'], 'display_order' => $data['order']])->id,
                    'bill' => Bill::create(['facility_id' => $parentId, 'name' => $data['name'], 'name_translations' => $data['translations'], 'display_order' => $data['order']])->id,
                    'element' => Element::create(['bill_id' => $parentId, 'name' => $data['name'], 'name_translations' => $data['translations'], 'display_order' => $data['order']])->id,
                    'sub_element' => SubElement::create(['element_id' => $parentId, 'name' => $data['name'], 'name_translations' => $data['translations'], 'display_order' => $data['order']])->id,
                };
            };

            foreach ($allRows as &$row) {
                // Bills, elements and sub-elements need a parent: use a default one when the sheet has none.
                $needsFacility = $row['_facility'] !== null || $row['_bill'] !== null || $row['_element'] !== null || $row['_sub_element'] !== null;
                $needsBill = $row['_bill'] !== null || $row['_element'] !== null || $row['_sub_element'] !== null;
                $needsElement = $row['_element'] !== null || $row['_sub_element'] !== null;

                $facilityId = $needsFacility ? $node('facility', $row['_facility'], null, 'Main works') : null;
                $billId = $needsBill ? $node('bill', $row['_bill'], $facilityId, 'General') : null;
                $elementId = $needsElement ? $node('element', $row['_element'], $billId, 'General') : null;
                $subElementId = $row['_sub_element'] !== null ? $node('sub_element', $row['_sub_element'], $elementId, '') : null;

                unset($row['_facility'], $row['_bill'], $row['_element'], $row['_sub_element']);
                $row['facility_id'] = $facilityId;
                $row['bill_id'] = $billId;
                $row['element_id'] = $elementId;
                $row['sub_element_id'] = $subElementId;
            }
            unset($row);

            foreach (array_chunk($allRows, 500) as $chunk) {
                DB::table('boq_items')->insert($chunk);
            }

            $this->buildSummaries($boq);

            $metadata = $boq->metadata ?? [];
            $metadata['import_sheets'] = $sheetMetadata;
            if ($detectedLanguage) {
                $metadata['detected_language'] = $detectedLanguage;
            }
            if ($translationMetadata) {
                $metadata['translation_sources'] = $translationMetadata;
            }
            $boq->update(['metadata' => $metadata]);
        });

        return count($allRows);
    }

    private function getCellValue($cell): string
    {
        if ($cell instanceof FormulaCell) {
            $computed = $cell->getComputedValue();
            if ($computed !== null && $computed !== '') {
                return trim((string)$computed);
            }
            return trim((string)$cell->getValue());
        }
        return trim((string)($cell->getValue() ?? ''));
    }

    /**
     * Column positions when this row holds the BOQ headings (it needs a
     * description and a quantity column), otherwise null.
     *
     * @return array{description: int, quantity: int, headers: array<int, string>, item?: int, unit?: int, rate?: int, amount?: int}|null
     */
    private function detectColumns(array $values): ?array
    {
        $headers = array_map(fn ($value) => strtoupper(trim((string) $value)), $values);
        $columns = [];

        foreach ($headers as $index => $header) {
            $key = $this->columnKey($header);
            if ($key !== null && ! isset($columns[$key])) {
                $columns[$key] = $index;
            }
        }

        if (! isset($columns['description'], $columns['quantity'])) {
            return null;
        }

        return $columns + ['headers' => $headers];
    }

    private function columnKey(string $header): ?string
    {
        // "RATE (UGX)" -> "RATE", "QTY." -> "QTY", "Description of works" -> "DESCRIPTION OF WORKS"
        $name = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z ]/', ' ', preg_replace('/\(.*?\)/', ' ', $header) ?? '') ?? '') ?? '');

        if ($name === '' || mb_strlen($name) > 40) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/^(ITEM )?(DESCRIPTION|DESC|PARTICULARS|WORK ITEM|DETAILS)\b/', $name) => 'description',
            (bool) preg_match('/^(QUANTITY|QTY|QUANTITIES|QNTY|QTTY)\b/', $name) => 'quantity',
            (bool) preg_match('/^(UNIT RATE|RATE|PRICE|UNIT PRICE|UNIT COST)\b/', $name) => 'rate',
            (bool) preg_match('/^(AMOUNT|TOTAL|TOTAL AMOUNT|COST|TOTAL COST|VALUE)\b/', $name) => 'amount',
            (bool) preg_match('/^(UNIT|UNITS|UOM)$/', $name) => 'unit',
            (bool) preg_match('/^(ITEM|ITEM NO|ITEM CODE|NO|REF|CODE|S N|SN)$/', $name) => 'item',
            default => null,
        };
    }

    /**
     * Section heading rows: [level, heading] for "Facility/Block/Building",
     * "Bill", "Element" and "Sub-element" headings.
     *
     * @return array{0: string, 1: string}|null
     */
    private function hierarchyLevel(string $description): ?array
    {
        $patterns = [
            'sub_element' => '/^SUB[\s\-]?ELEMENTS?\b\s*(?:NO\.?\s*)?[\w.]*\s*[:\-–—.]?\s*(.*)$/iu',
            'element' => '/^ELEMENTS?\b\s*(?:NO\.?\s*)?[\w.]*\s*[:\-–—.]?\s*(.*)$/iu',
            'bill' => '/^BILL\b\s*(?:NO\.?\s*)?[\w.]*\s*[:\-–—.]?\s*(.*)$/iu',
            'facility' => '/^(?:FACILITY|BUILDING|BLOCK)\b\s*[:\-–—.]?\s*(.*)$/iu',
        ];

        foreach ($patterns as $level => $pattern) {
            if (preg_match($pattern, $description)) {
                // Keep the whole heading ("BILL No. 1 - SUBSTRUCTURE") as the section name.
                return [$level, trim($description)];
            }
        }

        return null;
    }

    private function isTotalRow(string $description): bool
    {
        return (bool) preg_match('/^(sub[\s\-]?total|total|grand total|carried (to|forward)|brought forward|b\/f|c\/f|to collection|collection|page total|summary)\b/iu', trim($description));
    }

    /**
     * Lenient number parsing: "1,500.00", "UGX 1 500", "1500/=", "(200)".
     * Returns null for blanks, dashes and text such as "Item" or "Sum".
     */
    private function parseNumber(string $value): ?float
    {
        $text = trim($value);
        if ($text === '' || preg_match('/^[\-–—]+$/u', $text)) {
            return null;
        }

        $negative = (bool) preg_match('/^\(.*\)$/', $text);
        $text = preg_replace('/^([\d.,\s]+)[A-Za-z][A-Za-z0-9²³\/.]*$/u', '$1', $text) ?? $text;
        $text = preg_replace('/\/=|\b(UGX|USH|USHS|KES|KSH|TZS|RWF|USD|EUR|SHS?)\b|[$€£\s,()]/iu', '', $text) ?? '';

        if ($text === '' || ! is_numeric($text)) {
            return null;
        }

        return $negative ? -(float) $text : (float) $text;
    }

    /** Uses the unit column, or a unit typed into the quantity cell ("10 m3"). */
    private function unitText(string $unit, string $quantityText): string
    {
        if ($unit !== '') {
            return $unit;
        }

        return preg_match('/^[\d.,\s]+([A-Za-z][A-Za-z0-9²³\/.]*)$/u', trim($quantityText), $matches) ? $matches[1] : '';
    }

    private function headerIndex(array $headers, array $names): ?int
    {
        foreach ($headers as $index => $header) {
            if (in_array(trim((string) $header), $names, true)) {
                return $index;
            }
        }

        return null;
    }

    private function extractTranslations(array $values, array $headers): ?array
    {
        $translations = [];
        $langColumns = ['EN', 'ENGLISH', 'LG', 'LUGANDA', 'SW', 'SWAHILI', 'FR', 'FRENCH', 'AR', 'ARABIC'];
        foreach ($langColumns as $lang) {
            $index = $this->headerIndex($headers, [$lang]);
            if ($index !== null && isset($values[$index]) && trim($values[$index]) !== '') {
                $translations[strtolower($lang)] = trim($values[$index]);
            }
        }
        return $translations ?: null;
    }

    private function looksLikeLanguageHeader(string $description, array $values, array $headers): bool
    {
        $desc = strtoupper(trim($description));
        return in_array($desc, ['LANGUAGE', 'LANG', 'ORIGINAL LANGUAGE', 'SOURCE LANGUAGE'], true);
    }

    private function extractLanguage(array $values, array $headers): ?string
    {
        $index = $this->headerIndex($headers, ['VALUE', 'CODE', 'ISO', 'LANGUAGE CODE']);
        if ($index !== null && isset($values[$index])) {
            return strtolower(trim($values[$index]));
        }
        return null;
    }

    private function buildSummaries(Boq $boq): void
    {
        $vatRate = 0.18;
        $contingencyRate = 0.05;

        $facilities = $boq->facilities()->with('bills')->get();

        foreach ($facilities as $facility) {
            $facilityItems = BoqItem::where('facility_id', $facility->id)->get();
            $facilitySubtotal = $facilityItems->sum('amount');
            $facilityVat = round($facilitySubtotal * $vatRate, 2);
            $facilityContingency = round(($facilitySubtotal + $facilityVat) * $contingencyRate, 2);
            $facilityTotal = round($facilitySubtotal + $facilityVat + $facilityContingency, 2);

            BoqSummary::create([
                'boq_id' => $boq->id,
                'facility_id' => $facility->id,
                'summary_type' => 'facility',
                'name' => $facility->name,
                'subtotal' => $facilitySubtotal,
                'vat' => $facilityVat,
                'contingency' => $facilityContingency,
                'grand_total' => $facilityTotal,
                'metadata' => ['item_count' => $facilityItems->count()],
            ]);

            foreach ($facility->bills as $bill) {
                $billItems = BoqItem::where('bill_id', $bill->id)->get();
                $billSubtotal = $billItems->sum('amount');
                $billVat = round($billSubtotal * $vatRate, 2);
                $billContingency = round(($billSubtotal + $billVat) * $contingencyRate, 2);
                $billTotal = round($billSubtotal + $billVat + $billContingency, 2);

                BoqSummary::create([
                    'boq_id' => $boq->id,
                    'facility_id' => $facility->id,
                    'bill_id' => $bill->id,
                    'summary_type' => 'bill',
                    'name' => $bill->name,
                    'subtotal' => $billSubtotal,
                    'vat' => $billVat,
                    'contingency' => $billContingency,
                    'grand_total' => $billTotal,
                    'metadata' => ['item_count' => $billItems->count()],
                ]);
            }
        }

        $allItems = BoqItem::where('boq_id', $boq->id)->get();
        $grandSubtotal = $allItems->sum('amount');
        $grandVat = round($grandSubtotal * $vatRate, 2);
        $grandContingency = round(($grandSubtotal + $grandVat) * $contingencyRate, 2);
        $grandTotal = round($grandSubtotal + $grandVat + $grandContingency, 2);

        BoqSummary::create([
            'boq_id' => $boq->id,
            'summary_type' => 'grand',
            'name' => 'Grand Total',
            'subtotal' => $grandSubtotal,
            'vat' => $grandVat,
            'contingency' => $grandContingency,
            'grand_total' => $grandTotal,
            'metadata' => ['item_count' => $allItems->count()],
        ]);
    }

    /**
     * Pick the reader from the file's contents, not its extension: uploads are stored
     * under generated names whose extension is guessed (a CSV often becomes .txt,
     * an .xlsx sometimes .zip).
     */
    private function readerFor(string $path): \OpenSpout\Reader\ReaderInterface
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);

        if (str_starts_with($head, "PK\x03\x04")) {
            if (! class_exists(\ZipArchive::class)) {
                throw ValidationException::withMessages(['boq' => 'Excel files cannot be read because the server is missing the PHP "zip" extension. Ask your administrator to enable it, or upload the BOQ as .csv.']);
            }

            $zip = new \ZipArchive();

            if ($zip->open($path) === true) {
                $isOds = $zip->getFromName('mimetype') === 'application/vnd.oasis.opendocument.spreadsheet';
                $zip->close();

                if ($isOds) {
                    return new \OpenSpout\Reader\ODS\Reader();
                }
            }

            return new \OpenSpout\Reader\XLSX\Reader();
        }

        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            throw ValidationException::withMessages(['boq' => $this->unreadableMessage($path)]);
        }

        $options = new \OpenSpout\Reader\CSV\Options();
        $options->FIELD_DELIMITER = $this->csvDelimiter($head);

        if (! mb_check_encoding(preg_replace('/^\xEF\xBB\xBF/', '', $head), 'UTF-8')) {
            $options->ENCODING = 'Windows-1252';
        }

        return new \OpenSpout\Reader\CSV\Reader($options);
    }

    /** Comma, semicolon (European Excel) or tab, whichever splits the first lines most. */
    private function csvDelimiter(string $head): string
    {
        $lines = array_slice(preg_split('/\r\n|\r|\n/', $head) ?: [], 0, 5);
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $count = array_sum(array_map(fn ($line) => substr_count($line, $delimiter), $lines));

            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function unreadableMessage(string $path): string
    {
        $head = (string) @file_get_contents($path, false, null, 0, 8);

        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            return 'This looks like an old Excel 97-2003 (.xls) or password-protected file. Open it in Excel, remove any password and save it as .xlsx or .csv, then upload again.';
        }

        if (str_starts_with($head, '%PDF')) {
            return 'This file is a PDF. Upload it as a PDF so the items can be extracted with "Generate BOQ".';
        }

        return 'The spreadsheet could not be opened. Save it again as .xlsx or .csv (UTF-8) and upload it again.';
    }
}
