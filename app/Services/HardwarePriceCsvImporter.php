<?php

namespace App\Services;

use App\Models\HardwarePrice;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SplFileObject;
use Throwable;

class HardwarePriceCsvImporter
{
    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    public const MAX_ROWS = 5000;

    public const MAX_COLUMNS = 16;

    public const MAX_CELL_BYTES = 2000;

    private const REQUIRED_COLUMNS = [
        'item_name', 'category', 'unit', 'price', 'currency', 'supplier',
    ];

    private const ACCEPTED_COLUMNS = [
        'item_name', 'brand', 'category', 'specification', 'unit', 'price', 'currency',
        'supplier', 'location', 'source_url', 'source_reference', 'fetched_at', 'is_active',
    ];

    public function __construct(private HardwarePriceManager $manager) {}

    /**
     * Upserts by the tenant-scoped item_name/brand/category/unit/supplier/location natural key.
     * Invalid rows are reported and do not prevent subsequent rows from being imported.
     */
    public function import(string $path, int $organisationId): array
    {
        if (! is_file($path) || filesize($path) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('The CSV file is missing or exceeds the 5 MB limit.');
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $header = $this->readHeader($file);

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! in_array($column, $header, true)) {
                throw new RuntimeException("Missing required CSV column: {$column}.");
            }
        }

        $rowNumber = 1;
        while (! $file->eof()) {
            $values = $file->fgetcsv();
            $rowNumber++;

            if ($values === false || $values === [null] || $this->isBlank($values)) {
                $result['skipped']++;

                continue;
            }

            if ($rowNumber > self::MAX_ROWS + 1) {
                throw new RuntimeException('The CSV exceeds the 5,000 data row limit.');
            }

            if (count($values) > self::MAX_COLUMNS || count($values) !== count($header)) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => 'Column count does not match the header.'];

                continue;
            }

            if (collect($values)->contains(fn ($value) => strlen((string) $value) > self::MAX_CELL_BYTES)) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => 'A cell exceeds the 2,000 character limit.'];

                continue;
            }

            $row = array_combine($header, $values);
            $attributes = array_intersect_key($row, array_flip(self::ACCEPTED_COLUMNS));
            $attributes['fetched_at'] = $attributes['fetched_at'] ?: Carbon::now()->toDateTimeString();
            $attributes['is_active'] = $this->parseBoolean($attributes['is_active'] ?? '1');

            try {
                $existing = $this->findExisting($organisationId, $attributes);

                if ($existing && ! $this->hasChanges($existing, $attributes)) {
                    $result['skipped']++;

                    continue;
                }

                if ($existing) {
                    $this->manager->update($organisationId, $existing, $attributes);
                    $result['updated']++;
                } else {
                    $this->manager->create($organisationId, $attributes);
                    $result['created']++;
                }
            } catch (ValidationException $exception) {
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'message' => collect($exception->errors())->flatten()->first() ?? 'The row is invalid.',
                ];
            } catch (Throwable) {
                $result['errors'][] = ['row' => $rowNumber, 'message' => 'The row could not be imported.'];
            }
        }

        return $result;
    }

    private function readHeader(SplFileObject $file): array
    {
        $header = $file->fgetcsv();
        if ($header === false || $header === [null] || count($header) > self::MAX_COLUMNS) {
            throw new RuntimeException('The CSV header is missing or has too many columns.');
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");

        if (count($header) !== count(array_unique($header))) {
            throw new RuntimeException('The CSV header contains duplicate columns.');
        }

        return $header;
    }

    private function findExisting(int $organisationId, array $attributes): ?HardwarePrice
    {
        $query = HardwarePrice::query()->where('organisation_id', $organisationId);

        $allowedFields = ['item_name', 'brand', 'category', 'unit', 'supplier', 'location'];

        foreach ($allowedFields as $field) {
            $value = trim((string) ($attributes[$field] ?? ''));
            $value === ''
                ? $query->whereNull($field)
                : $query->whereRaw("LOWER({$field}) = ?", [mb_strtolower($value)]);
        }

        return $query->first();
    }

    private function hasChanges(HardwarePrice $price, array $attributes): bool
    {
        foreach (self::ACCEPTED_COLUMNS as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $current = $field === 'fetched_at'
                ? $price->fetched_at?->format('Y-m-d H:i:s')
                : $price->{$field};
            if ($field === 'fetched_at') {
                try {
                    $incoming = Carbon::parse($attributes[$field])->format('Y-m-d H:i:s');
                } catch (Throwable) {
                    return true;
                }
            } elseif ($field === 'price') {
                $current = number_format((float) $current, 2, '.', '');
                $incoming = is_numeric($attributes[$field])
                    ? number_format((float) $attributes[$field], 2, '.', '')
                    : $attributes[$field];
            } else {
                $incoming = $attributes[$field];
            }

            if ((string) $current !== (string) $incoming) {
                return true;
            }
        }

        return false;
    }

    private function parseBoolean(mixed $value): mixed
    {
        return match (strtolower(trim((string) $value))) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            default => $value,
        };
    }

    private function isBlank(array $values): bool
    {
        return collect($values)->every(fn ($value) => trim((string) $value) === '');
    }
}
