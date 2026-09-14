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
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use Throwable;

class BoqSpreadsheetImporter
{
    private const MAX_COLUMNS = 100;
    private const MAX_ROWS = 10000;
    private const MAX_SCANNED_ROWS = 20000;

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
            $reader = ReaderFactory::createFromFile($path);
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheetIndex => $sheet) {
                $headers = null;
                $sheetRows = [];
                $foundHeaders = false;
                $currentFacility = null;
                $currentBill = null;
                $currentElement = null;
                $currentSubElement = null;
                $facilityOrder = 0;
                $billOrder = 0;
                $elementOrder = 0;
                $subElementOrder = 0;

                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    $scannedRows++;
                    if ($scannedRows > self::MAX_SCANNED_ROWS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet is too large to process safely.']);
                    }

                    $values = array_map(
                        fn($cell) => $this->getCellValue($cell),
                        $row->getCells()
                    );

                    if (count($values) > self::MAX_COLUMNS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet contains too many columns.']);
                    }

                    if ($headers === null) {
                        $candidate = array_map(fn($value) => strtoupper(trim((string)$value)), $values);
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

                    if ($this->looksLikeHierarchyRow($description, $values, $headers)) {
                        [$newFacility, $newBill, $newElement, $newSubElement] = $this->parseHierarchyRow(
                            $description, $values, $headers,
                            $currentFacility, $currentBill, $currentElement, $currentSubElement,
                            $facilityOrder, $billOrder, $elementOrder, $subElementOrder
                        );
                        $currentFacility = $newFacility;
                        $currentBill = $newBill;
                        $currentElement = $newElement;
                        $currentSubElement = $newSubElement;
                        continue;
                    }

                    if (mb_strlen($description) > 2000 || count($allRows) >= self::MAX_ROWS) {
                        throw ValidationException::withMessages(['boq' => 'The spreadsheet is too large to process safely.']);
                    }

                    if ($detectedLanguage === null && $this->looksLikeLanguageHeader($description, $values, $headers)) {
                        $detectedLanguage = $this->extractLanguage($values, $headers);
                        continue;
                    }

                    $quantity = $this->number($this->value($values, $headers, ['QUANTITY', 'QTY']), 'quantity', true);
                    $rate = $this->number($this->value($values, $headers, ['RATE']), 'rate');
                    $amount = $this->number($this->value($values, $headers, ['AMOUNT']), 'amount');

                    if ($amount === null && $rate !== null) {
                        $amount = round($quantity * $rate, 2);
                    } elseif ($rate === null && $amount !== null && $quantity > 0) {
                        $rate = round($amount / $quantity, 2);
                        $amount = round($amount, 2);
                    } elseif ($amount === null && $rate === null) {
                        $amount = 0;
                    }

                    $rowData = [
                        'boq_id' => $boq->id,
                        'item_code' => $this->value($values, $headers, ['ITEM', 'ITEM CODE']) ?: null,
                        'description' => $description,
                        'unit' => $this->value($values, $headers, ['UNIT']) ?: null,
                        'quantity' => $quantity,
                        'original_rate' => $rate,
                        'approved_rate' => null,
                        'amount' => $amount,
                        'currency' => $boq->currency,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($currentFacility) $rowData['facility_id'] = $currentFacility;
                    if ($currentBill) $rowData['bill_id'] = $currentBill;
                    if ($currentElement) $rowData['element_id'] = $currentElement;
                    if ($currentSubElement) $rowData['sub_element_id'] = $currentSubElement;

                    $allRows[] = $rowData;
                }

                $sheetMetadata[] = [
                    'sheet_index' => $sheetIndex,
                    'sheet_name' => $sheet->getName(),
                    'rows_processed' => count($sheetRows),
                ];
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
                }
            }
        }

        if ($allRows === []) {
            throw ValidationException::withMessages(['boq' => 'No BOQ items were found in the spreadsheet.']);
        }

        DB::transaction(function () use ($boq, $allRows, $sheetMetadata, $detectedLanguage, $translationMetadata): void {
            $boq->items()->delete();
            $boq->facilities()->delete();
            $boq->summaries()->delete();

            $facilityCache = [];
            $billCache = [];
            $elementCache = [];
            $subElementCache = [];

            foreach ($allRows as $row) {
                $facilityId = null;
                $billId = null;
                $elementId = null;
                $subElementId = null;

                if (isset($row['facility_name'])) {
                    $key = 'facility_' . $row['facility_name'];
                    if (!isset($facilityCache[$key])) {
                        $facility = Facility::create([
                            'boq_id' => $boq->id,
                            'name' => $row['facility_name'],
                            'name_translations' => $row['facility_translations'] ?? null,
                            'description' => $row['facility_description'] ?? null,
                            'display_order' => $row['facility_order'] ?? 0,
                        ]);
                        $facilityCache[$key] = $facility->id;
                    }
                    $facilityId = $facilityCache[$key];
                    unset($row['facility_name'], $row['facility_translations'], $row['facility_description'], $row['facility_order']);
                }

                if (isset($row['bill_name'])) {
                    $key = 'bill_' . $facilityId . '_' . $row['bill_name'];
                    if (!isset($billCache[$key])) {
                        $bill = Bill::create([
                            'facility_id' => $facilityId,
                            'name' => $row['bill_name'],
                            'name_translations' => $row['bill_translations'] ?? null,
                            'description' => $row['bill_description'] ?? null,
                            'display_order' => $row['bill_order'] ?? 0,
                        ]);
                        $billCache[$key] = $bill->id;
                    }
                    $billId = $billCache[$key];
                    unset($row['bill_name'], $row['bill_translations'], $row['bill_description'], $row['bill_order']);
                }

                if (isset($row['element_name'])) {
                    $key = 'element_' . $billId . '_' . $row['element_name'];
                    if (!isset($elementCache[$key])) {
                        $element = Element::create([
                            'bill_id' => $billId,
                            'name' => $row['element_name'],
                            'name_translations' => $row['element_translations'] ?? null,
                            'description' => $row['element_description'] ?? null,
                            'display_order' => $row['element_order'] ?? 0,
                        ]);
                        $elementCache[$key] = $element->id;
                    }
                    $elementId = $elementCache[$key];
                    unset($row['element_name'], $row['element_translations'], $row['element_description'], $row['element_order']);
                }

                if (isset($row['sub_element_name'])) {
                    $key = 'sub_element_' . $elementId . '_' . $row['sub_element_name'];
                    if (!isset($subElementCache[$key])) {
                        $subElement = SubElement::create([
                            'element_id' => $elementId,
                            'name' => $row['sub_element_name'],
                            'name_translations' => $row['sub_element_translations'] ?? null,
                            'description' => $row['sub_element_description'] ?? null,
                            'display_order' => $row['sub_element_order'] ?? 0,
                        ]);
                        $subElementCache[$key] = $subElement->id;
                    }
                    $subElementId = $subElementCache[$key];
                    unset($row['sub_element_name'], $row['sub_element_translations'], $row['sub_element_description'], $row['sub_element_order']);
                }

                $row['facility_id'] = $facilityId;
                $row['bill_id'] = $billId;
                $row['element_id'] = $elementId;
                $row['sub_element_id'] = $subElementId;
            }

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

    private function looksLikeHierarchyRow(string $description, array $values, array $headers): bool
    {
        $desc = strtoupper(trim($description));
        $hierarchyKeywords = ['FACILITY', 'BUILDING', 'BLOCK', 'BILL', 'ELEMENT', 'SUB-ELEMENT', 'SUBELEMENT', 'SECTION', 'CHAPTER'];
        foreach ($hierarchyKeywords as $keyword) {
            if (str_starts_with($desc, $keyword . ' ') || str_starts_with($desc, $keyword . ':')) {
                return true;
            }
        }
        return false;
    }

    private function parseHierarchyRow(
        string $description,
        array $values,
        array $headers,
        ?int $currentFacility,
        ?int $currentBill,
        ?int $currentElement,
        ?int $currentSubElement,
        int &$facilityOrder,
        int &$billOrder,
        int &$elementOrder,
        int &$subElementOrder
    ): array {
        $desc = trim($description);
        $upperDesc = strtoupper($desc);

        $translations = $this->extractTranslations($values, $headers);

        if (preg_match('/^(FACILITY|BUILDING|BLOCK)\s*[:\-]?\s*(.+)$/i', $desc, $matches)) {
            $facilityOrder++;
            return [
                'facility_name' => trim($matches[2]),
                'facility_translations' => $translations,
                'facility_description' => $this->value($values, $headers, ['DESCRIPTION']) ?: null,
                'facility_order' => $facilityOrder,
            ];
        }

        if (preg_match('/^BILL\s*[:\-]?\s*(.+)$/i', $desc, $matches)) {
            $billOrder++;
            $currentElement = null;
            $currentSubElement = null;
            $elementOrder = 0;
            $subElementOrder = 0;
            return [
                $currentFacility,
                'bill_name' => trim($matches[1]),
                'bill_translations' => $translations,
                'bill_description' => $this->value($values, $headers, ['DESCRIPTION']) ?: null,
                'bill_order' => $billOrder,
            ];
        }

        if (preg_match('/^ELEMENT\s*[:\-]?\s*(.+)$/i', $desc, $matches)) {
            $elementOrder++;
            $currentSubElement = null;
            $subElementOrder = 0;
            return [
                $currentFacility,
                $currentBill,
                'element_name' => trim($matches[1]),
                'element_translations' => $translations,
                'element_description' => $this->value($values, $headers, ['DESCRIPTION']) ?: null,
                'element_order' => $elementOrder,
            ];
        }

        if (preg_match('/^SUB[-\s]?ELEMENT\s*[:\-]?\s*(.+)$/i', $desc, $matches)) {
            $subElementOrder++;
            return [
                $currentFacility,
                $currentBill,
                $currentElement,
                'sub_element_name' => trim($matches[1]),
                'sub_element_translations' => $translations,
                'sub_element_description' => $this->value($values, $headers, ['DESCRIPTION']) ?: null,
                'sub_element_order' => $subElementOrder,
            ];
        }

        return [$currentFacility, $currentBill, $currentElement, $currentSubElement];
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
        if ($normalised === '' && !$required) {
            return null;
        }
        if ($normalised === '' || !is_numeric($normalised)) {
            throw ValidationException::withMessages([
                'boq' => "The {$column} column contains a non-numeric value.",
            ]);
        }
        return (float)$normalised;
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
}