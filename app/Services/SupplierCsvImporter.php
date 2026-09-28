<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Supplier;
use App\Support\Regional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bulk supplier/factory import from the downloadable CSV template.
 *
 * preview() validates every row and classifies it as valid, duplicate or failed
 * without writing anything; import() then creates only the valid rows.
 */
class SupplierCsvImporter
{
    public const HEADERS = [
        'Supplier Name', 'Contact Person', 'Phone', 'Email', 'Website', 'Country',
        'District/City', 'Physical Address', 'Supplier Type', 'Status', 'Notes',
    ];

    private const MAX_ROWS = 2000;

    /** Header aliases (lower-cased) => field. */
    private const FIELD_MAP = [
        'supplier name' => 'name', 'name' => 'name',
        'contact person' => 'contact_name', 'contact' => 'contact_name',
        'phone' => 'phone', 'telephone' => 'phone',
        'email' => 'email',
        'website' => 'website_url', 'website url' => 'website_url',
        'country' => 'country',
        'district/city' => 'location', 'district' => 'location', 'city' => 'location',
        'physical address' => 'address', 'address' => 'address',
        'supplier type' => 'type', 'type' => 'type',
        'status' => 'status',
        'notes' => 'notes',
    ];

    public function template(): string
    {
        $rows = [
            self::HEADERS,
            ['Example Hardware Ltd', 'Jane Doe', '+256 700 000 001', 'sales@example-hardware.com', 'https://example-hardware.com', 'Uganda', 'Kampala', 'Plot 1, Main Street', 'supplier', 'active', 'Cement and steel'],
            ['Example Cement Factory', 'John Smith', '+254 700 000 002', 'orders@example-cement.com', 'https://example-cement.com', 'KE', 'Mombasa', 'Industrial Area', 'factory', 'active', ''],
        ];

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);

        return "\u{FEFF}".stream_get_contents($handle);
    }

    /**
     * @return array{valid: list<array>, duplicates: list<array>, failed: list<array>}
     */
    public function preview(string $path): array
    {
        $handle = @fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('The file could not be opened.');
        }

        $firstLine = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter);

        if (! $header) {
            throw new RuntimeException('The file is empty.');
        }

        $columns = array_map(fn ($h) => self::FIELD_MAP[strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)))] ?? null, $header);

        if (! in_array('name', $columns, true)) {
            throw new RuntimeException('The file must have a "Supplier Name" column. Download the template to see the expected columns.');
        }

        $existing = $this->existingKeys();
        $seen = ['name' => [], 'email' => [], 'phone' => [], 'website' => []];
        $result = ['valid' => [], 'duplicates' => [], 'failed' => []];
        $line = 1;

        while (($raw = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if (count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            if ($line > self::MAX_ROWS + 1) {
                throw new RuntimeException('The file has more than '.self::MAX_ROWS.' rows. Split it into smaller files.');
            }

            $row = ['line' => $line];
            foreach ($columns as $index => $field) {
                if ($field !== null) {
                    $row[$field] = trim((string) ($raw[$index] ?? ''));
                }
            }

            $row = $this->normalise($row);
            $errors = $this->validate($row);

            if ($errors !== []) {
                $result['failed'][] = $row + ['errors' => $errors];

                continue;
            }

            $keys = $this->keysFor($row);
            $duplicateOf = null;

            foreach ($keys as $kind => $key) {
                if ($key === null) {
                    continue;
                }
                if (isset($existing[$kind][$key])) {
                    $duplicateOf = 'Already exists: '.$existing[$kind][$key].' (same '.$kind.')';
                    break;
                }
                if (isset($seen[$kind][$key])) {
                    $duplicateOf = 'Repeats line '.$seen[$kind][$key].' (same '.$kind.')';
                    break;
                }
            }

            if ($duplicateOf) {
                $result['duplicates'][] = $row + ['errors' => [$duplicateOf]];

                continue;
            }

            foreach ($keys as $kind => $key) {
                if ($key !== null) {
                    $seen[$kind][$key] = $line;
                }
            }

            $result['valid'][] = $row;
        }

        fclose($handle);

        return $result;
    }

    /**
     * Create the valid rows from a preview. Returns the number created.
     *
     * @param  list<array>  $rows
     */
    public function import(array $rows, ?int $userId): int
    {
        $existing = $this->existingKeys();

        return DB::transaction(function () use ($rows, $userId, $existing) {
            $created = 0;

            foreach ($rows as $row) {
                // Re-check in case suppliers were added between preview and confirm.
                foreach ($this->keysFor($row) as $kind => $key) {
                    if ($key !== null && isset($existing[$kind][$key])) {
                        continue 2;
                    }
                }

                Supplier::create([
                    'code' => 'SUP-'.Str::upper(Str::random(6)),
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'contact_name' => $row['contact_name'] ?: null,
                    'phone' => $row['phone'] ?: null,
                    'email' => $row['email'] ?: null,
                    'website_url' => $row['website_url'] ?: null,
                    'country' => $row['country'] ?: null,
                    'location' => $row['location'] ?: null,
                    'address' => $row['address'] ?: null,
                    'notes' => $row['notes'] ?: null,
                    'is_active' => $row['status'] === 'active',
                    'currency' => Regional::currency(),
                    'created_by' => $userId,
                ]);

                $created++;
            }

            return $created;
        });
    }

    /**
     * CSV of failed and duplicate rows with the reason, so the user can fix and re-upload.
     *
     * @param  list<array>  $rows
     */
    public function errorsCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Line', ...self::HEADERS, 'Errors']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['line'] ?? '', $row['name'] ?? '', $row['contact_name'] ?? '', $row['phone'] ?? '', $row['email'] ?? '',
                $row['website_url'] ?? '', $row['country'] ?? '', $row['location'] ?? '', $row['address'] ?? '',
                $row['type'] ?? '', $row['status'] ?? '', $row['notes'] ?? '', implode('; ', $row['errors'] ?? []),
            ]);
        }

        rewind($handle);

        return "\u{FEFF}".stream_get_contents($handle);
    }

    private function normalise(array $row): array
    {
        $row += ['name' => '', 'contact_name' => '', 'phone' => '', 'email' => '', 'website_url' => '', 'country' => '',
            'location' => '', 'address' => '', 'type' => '', 'status' => '', 'notes' => ''];

        $row['email'] = strtolower($row['email']);
        $type = strtolower($row['type']);
        $row['type'] = in_array($type, ['factory', 'manufacturer'], true) ? Supplier::TYPE_FACTORY : ($type === '' || in_array($type, ['supplier', 'hardware', 'distributor'], true) ? Supplier::TYPE_SUPPLIER : $type);
        $status = strtolower($row['status']);
        $row['status'] = $status === '' || in_array($status, ['active', 'yes', '1'], true) ? 'active' : (in_array($status, ['inactive', 'no', '0'], true) ? 'inactive' : $status);

        if ($row['website_url'] !== '' && ! preg_match('#^https?://#i', $row['website_url'])) {
            $row['website_url'] = 'https://'.$row['website_url'];
        }

        // Country may be a name or an ISO code.
        if ($row['country'] !== '') {
            $code = strlen($row['country']) === 2
                ? strtoupper($row['country'])
                : Country::whereRaw('LOWER(name) = ?', [strtolower($row['country'])])->value('iso2');
            $row['country'] = $code && Country::where('iso2', $code)->exists() ? $code : '!'.$row['country'];
        }

        return $row;
    }

    /** @return list<string> */
    private function validate(array $row): array
    {
        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:supplier,factory'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Supplier Name is required.',
            'phone.regex' => 'Phone must be a valid number (digits, spaces, + and dashes).',
            'email.email' => 'Email is not a valid address.',
            'website_url.url' => 'Website is not a valid URL.',
            'type.in' => 'Supplier Type must be "supplier" or "factory".',
            'status.in' => 'Status must be "active" or "inactive".',
        ]);

        $errors = $validator->errors()->all();

        if (str_starts_with($row['country'], '!')) {
            $errors[] = 'Country "'.substr($row['country'], 1).'" is not recognised; use the country name or 2-letter code.';
        }

        return $errors;
    }

    /** @return array{name: ?string, email: ?string, phone: ?string, website: ?string} */
    private function keysFor(array $row): array
    {
        $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? ''));
        $host = strtolower((string) parse_url((string) ($row['website_url'] ?? ''), PHP_URL_HOST));

        return [
            'name' => ($name = Str::of((string) ($row['name'] ?? ''))->lower()->squish()->toString()) !== '' ? $name : null,
            'email' => ($row['email'] ?? '') !== '' ? strtolower($row['email']) : null,
            'phone' => strlen($phone) >= 7 ? substr($phone, -9) : null,
            'website' => $host !== '' ? preg_replace('/^www\./', '', $host) : null,
        ];
    }

    /** @return array<string, array<string, string>> kind => key => existing supplier name */
    private function existingKeys(): array
    {
        $keys = ['name' => [], 'email' => [], 'phone' => [], 'website' => []];

        Supplier::query()->select(['name', 'email', 'phone', 'website_url'])->each(function (Supplier $supplier) use (&$keys) {
            foreach ($this->keysFor($supplier->only(['name', 'email', 'phone', 'website_url'])) as $kind => $key) {
                if ($key !== null) {
                    $keys[$kind][$key] = $supplier->name;
                }
            }
        });

        return $keys;
    }
}
