<?php

namespace App\Services;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use Throwable;

/**
 * Converts an uploaded BOQ into one of the standard formats the importers read
 * (.xlsx, UTF-8 comma .csv, .pdf, .jpg/.png) and stores it.
 *
 * The real type is detected from the file's contents, not its name or the MIME
 * type the server guesses (a CSV is usually guessed as text/plain):
 *  - .xlsx / .xlsm are kept as .xlsx; OpenDocument (.ods) is converted to .xlsx;
 *  - CSV, TSV, semicolon or pipe separated text (any encoding) becomes UTF-8 .csv;
 *  - WebP, GIF and BMP images become .jpg; JPEG and PNG are kept;
 *  - PDFs are kept.
 */
class BoqUploadNormalizer
{
    public function __construct(private ?FileCompressor $compressor = null)
    {
        $this->compressor ??= new FileCompressor();
    }

    /** Extensions accepted by the upload forms (contents are checked afterwards). */
    public const EXTENSIONS = ['xlsx', 'xlsm', 'ods', 'xls', 'csv', 'tsv', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'gz'];

    public const MAX_KILOBYTES = 20480;

    /**
     * Validation rules for the uploaded file field.
     *
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['required', 'file', 'max:'.self::MAX_KILOBYTES, 'extensions:'.implode(',', self::EXTENSIONS)];
    }

    /**
     * @return array{path: string, source_type: string, extension: string}
     *
     * @throws ValidationException when the file cannot be turned into a supported format
     */
    public function store(UploadedFile $file, string $directory, string $attribute = 'file'): array
    {
        $source = $file->getRealPath();

        if ($source === false || ! is_file($source) || filesize($source) === 0) {
            $this->fail($attribute, 'The uploaded file is empty. Choose the BOQ file again.');
        }

        $temporary = null;
        $unpacked = null;

        try {
            // The mobile app gzips text files to upload them faster.
            if ($this->compressor->isGzip($source)) {
                $unpacked = $this->compressor->gunzip($source, $attribute);
                $source = $unpacked;
            }

            [$extension, $converted] = $this->normalize($source, $attribute);
            $temporary = $converted;

            $name = Str::random(40).'.'.$extension;
            $path = Storage::putFileAs($directory, new File($converted ?? $source), $name);

            if ($path === false) {
                $this->fail($attribute, 'The BOQ file could not be saved. Please try again.');
            }

            return [
                'path' => $path,
                'source_type' => match ($extension) {
                    'xlsx', 'csv' => 'excel',
                    'pdf' => 'pdf',
                    default => 'scan',
                },
                'extension' => $extension,
            ];
        } finally {
            foreach ([$temporary, $unpacked] as $file) {
                if ($file !== null && is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * @return array{0: string, 1: string|null} standard extension and converted file (null = store as-is)
     */
    private function normalize(string $path, string $attribute): array
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);

        if (str_starts_with($head, "PK\x03\x04")) {
            return $this->normalizeZip($path, $attribute);
        }

        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            $this->fail($attribute, 'Old Excel 97-2003 (.xls) and password-protected files cannot be converted automatically. Open the file in Excel, remove any password and save it as .xlsx or .csv, then upload it again.');
        }

        if (str_starts_with($head, '%PDF')) {
            return ['pdf', null];
        }

        if (substr($head, 4, 4) === 'ftyp' && preg_match('/^ftyp(heic|heix|hevc|mif1|msf1|avif)/', substr($head, 4, 8))) {
            $this->fail($attribute, 'HEIC/AVIF photos are not supported. Take the photo with the in-app scanner, or save it as JPEG or PNG and upload it again.');
        }

        $image = @getimagesize($path);

        if ($image !== false) {
            if (! in_array($image[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF, IMAGETYPE_BMP], true)) {
                $this->fail($attribute, 'This image type is not supported. Save the photo as JPEG or PNG and upload it again.');
            }

            // Resize and re-encode photos/scans to save upload time and storage.
            $compressed = $this->compressor->image($path, FileCompressor::SCAN_MAX_SIDE);
            if ($compressed !== null) {
                return [$compressed['extension'], $compressed['path']];
            }

            return match ($image[2]) {
                IMAGETYPE_JPEG => ['jpg', null],
                IMAGETYPE_PNG => ['png', null],
                default => ['jpg', $this->imageToJpeg($path, $attribute)],
            };
        }

        if (str_contains($head, "\0") && ! $this->looksLikeUtf16($head)) {
            $this->fail($attribute, 'This file type is not supported. Upload the BOQ as Excel (.xlsx), CSV, PDF or a photo (JPEG/PNG).');
        }

        return ['csv', $this->textToCsv($path, $attribute)];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function normalizeZip(string $path, string $attribute): array
    {
        if (! class_exists(\ZipArchive::class)) {
            // Cannot look inside: keep it as a workbook and let the importer report problems.
            return ['xlsx', null];
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            $this->fail($attribute, 'The file appears to be damaged. Save it again as .xlsx or .csv and upload it again.');
        }

        $mimetype = $zip->getFromName('mimetype');
        $isWorkbook = $zip->locateName('xl/workbook.xml') !== false;
        $isWord = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        if ($isWorkbook) {
            return ['xlsx', null];
        }

        if ($mimetype === 'application/vnd.oasis.opendocument.spreadsheet') {
            return ['xlsx', $this->odsToXlsx($path, $attribute)];
        }

        if ($isWord || $mimetype === 'application/vnd.oasis.opendocument.text') {
            $this->fail($attribute, 'Word documents cannot be imported. Save the BOQ as PDF or Excel (.xlsx) and upload it again.');
        }

        $this->fail($attribute, 'This file type is not supported. Upload the BOQ as Excel (.xlsx), CSV, PDF or a photo (JPEG/PNG).');
    }

    private function odsToXlsx(string $path, string $attribute): string
    {
        $target = $this->temporaryFile('xlsx');
        $reader = new \OpenSpout\Reader\ODS\Reader();
        $writer = new \OpenSpout\Writer\XLSX\Writer();

        try {
            $reader->open($path);
            $writer->openToFile($target);
            $first = true;
            $names = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                if (! $first) {
                    $writer->addNewSheetAndMakeItCurrent();
                }
                $first = false;

                $name = $this->sheetName($sheet->getName(), $names);
                try {
                    $writer->getCurrentSheet()->setName($name);
                } catch (Throwable) {
                    // Keep OpenSpout's default name when this one is not allowed.
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $writer->addRow(Row::fromValues($row->toArray()));
                }
            }
        } catch (Throwable $e) {
            report($e);
            @unlink($target);
            $this->fail($attribute, 'The OpenDocument spreadsheet could not be converted. Save it as .xlsx or .csv and upload it again.');
        } finally {
            $reader->close();
            $writer->close();
        }

        return $target;
    }

    /** Excel sheet names: at most 31 characters, no []:*?/\ and unique. */
    private function sheetName(string $name, array &$used): string
    {
        $clean = trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name) ?? '') ?: 'Sheet';
        $candidate = mb_substr($clean, 0, 31);
        $suffix = 2;

        while (in_array(mb_strtolower($candidate), $used, true)) {
            $candidate = mb_substr($clean, 0, 28).' '.$suffix++;
        }

        $used[] = mb_strtolower($candidate);

        return $candidate;
    }

    private function imageToJpeg(string $path, string $attribute): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            $this->fail($attribute, 'The image could not be read. Save the photo as JPEG or PNG and upload it again.');
        }

        // Flatten transparency onto white so text stays readable.
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        $target = $this->temporaryFile('jpg');
        $saved = imagejpeg($canvas, $target, 90);
        imagedestroy($image);
        imagedestroy($canvas);

        if (! $saved) {
            $this->fail($attribute, 'The image could not be converted. Save the photo as JPEG or PNG and upload it again.');
        }

        return $target;
    }

    /** Re-writes delimited text of any common encoding as a UTF-8, comma-separated CSV. */
    private function textToCsv(string $path, string $attribute): string
    {
        $text = (string) file_get_contents($path);
        $text = $this->toUtf8($text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (trim($text) === '') {
            $this->fail($attribute, 'The uploaded file is empty. Choose the BOQ file again.');
        }

        $delimiter = $this->delimiter($text);
        $input = fopen('php://temp', 'r+');
        fwrite($input, $text);
        rewind($input);

        $target = $this->temporaryFile('csv');
        $output = fopen($target, 'w');
        $rows = 0;

        while (($row = fgetcsv($input, null, $delimiter, '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            fputcsv($output, array_map(fn ($cell) => trim((string) $cell), $row), ',', '"', '');
            $rows++;
        }

        fclose($input);
        fclose($output);

        if ($rows === 0) {
            @unlink($target);
            $this->fail($attribute, 'No rows were found in the file. Upload the BOQ as Excel (.xlsx) or CSV.');
        }

        return $target;
    }

    private function toUtf8(string $text): string
    {
        if (str_starts_with($text, "\xFF\xFE") || str_starts_with($text, "\xFE\xFF")) {
            $encoding = str_starts_with($text, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE';

            return mb_convert_encoding(substr($text, 2), 'UTF-8', $encoding);
        }

        if ($this->looksLikeUtf16($text)) {
            return mb_convert_encoding($text, 'UTF-8', 'UTF-16LE');
        }

        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    /** Unicode text saved by Excel ("Unicode Text") is UTF-16 without letters' high bytes. */
    private function looksLikeUtf16(string $head): bool
    {
        if (str_starts_with($head, "\xFF\xFE") || str_starts_with($head, "\xFE\xFF")) {
            return true;
        }

        $sample = substr($head, 0, 400);

        return strlen($sample) >= 8 && substr_count($sample, "\0") >= strlen($sample) / 3;
    }

    /** Comma, semicolon, tab or pipe, whichever splits the first lines most consistently. */
    private function delimiter(string $text): string
    {
        $lines = array_slice(array_filter(explode("\n", substr($text, 0, 8192)), fn ($l) => trim($l) !== ''), 0, 10);
        $best = ',';
        $bestScore = 0;

        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $score = array_sum(array_map(fn ($line) => substr_count($line, $delimiter), $lines));

            if ($score > $bestScore) {
                $best = $delimiter;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function temporaryFile(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'boq');
        $target = $base.'.'.$extension;
        @unlink($base);

        return $target;
    }

    private function fail(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}
