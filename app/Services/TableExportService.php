<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeInterface;

class TableExportService
{
    public function download(array $sections, string $format)
    {
        foreach ($sections as &$section) {
            $section['rows'] = array_map(fn ($row) => array_map([$this, 'display'], $row), $section['rows']);
        }
        unset($section);
        $filename = 'export-'.now()->format('Y-m-d-His');
        if ($format === 'pdf') {
            return response()->streamDownload(fn () => print(Pdf::loadView('exports.tables', [
                'sections' => $sections, 'generatedAt' => now(),
            ])->setPaper('a4', 'landscape')->output()), $filename.'.pdf', ['Content-Type' => 'application/pdf']);
        }

        return response()->streamDownload(function () use ($sections) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ($sections as $section) {
                if (count($sections) > 1) {
                    fputcsv($stream, [$this->csvCell($section['title'])]);
                }
                fputcsv($stream, array_map(fn ($label) => $this->csvCell(__($label)), array_values($section['columns'])));
                foreach ($section['rows'] as $row) {
                    fputcsv($stream, array_map([$this, 'csvCell'], $row));
                }
                if (count($sections) > 1) {
                    fputcsv($stream, []);
                }
            }
            fclose($stream);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function display(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'display'], $value));
        }

        return is_scalar($value) ? strip_tags((string) $value) : '';
    }

    private function csvCell(string $value): string
    {
        // Prevent text cells being interpreted as spreadsheet formulas.
        return preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $value) ? "'".$value : $value;
    }
}
