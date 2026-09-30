<?php

namespace App\Services;

use App\Models\Supplier;
use App\Support\Regional;
use RuntimeException;

/**
 * Finds hardware shops and factories operating in a location with the AI
 * provider, and marks the ones already in the supplier list as duplicates, so
 * the admin only picks new ones to add.
 */
class SupplierDiscovery
{
    public const MAX_RESULTS = 30;

    public function __construct(private AiProviderService $ai, private SupplierCsvImporter $importer) {}

    /**
     * @param  string  $type  '', 'supplier' or 'factory'
     * @return list<array{key: string, name: string, type: string, contact_name: string, phone: string, email: string, website_url: string, location: string, address: string, country: string, materials: string, source_url: string, duplicate: ?string, errors: list<string>}>
     */
    public function discover(string $location, string $type = '', int $limit = 15, ?int $organisationId = null): array
    {
        $location = trim($location);
        if ($location === '') {
            throw new RuntimeException(__('Enter a location to search.'));
        }

        $limit = max(1, min(self::MAX_RESULTS, $limit));
        $kind = match ($type) {
            Supplier::TYPE_SUPPLIER => 'hardware shops and building material suppliers',
            Supplier::TYPE_FACTORY => 'factories and manufacturers of construction materials',
            default => 'hardware shops, building material suppliers and construction material factories',
        };
        $known = Supplier::query()->orderByDesc('id')->limit(80)->pluck('name')->implode(', ');

        $prompt = implode("\n", array_filter([
            'You are a researcher of construction material suppliers.',
            "List real, currently operating {$kind} located in or serving {$location}.",
            'Use their official websites, business directories and maps listings. Only include businesses you are confident exist; never invent names, phone numbers, emails or websites. Leave a field empty when unknown.',
            "Return at most {$limit} businesses.",
            $known !== '' ? "These are already known, leave them out: {$known}." : null,
            'type is "factory" for manufacturers and "supplier" for shops and distributors. country is the 2-letter ISO code.',
            'Return JSON only: {"suppliers":[{"name":string,"type":"supplier"|"factory","contact_name":string|null,"phone":string|null,"email":string|null,"website_url":string|null,"location":string|null,"address":string|null,"country":string|null,"materials":string|null,"source_url":string|null}]}',
        ]));

        $answer = $this->ai->json($prompt, 'supplier_discovery', $organisationId, context: ['metadata' => ['location' => $location, 'type' => $type]]);
        $found = is_array($answer['suppliers'] ?? null) ? array_slice($answer['suppliers'], 0, $limit) : [];

        $rows = [];
        foreach (array_values($found) as $index => $item) {
            if (! is_array($item) || trim((string) ($item['name'] ?? '')) === '') {
                continue;
            }
            $text = fn (string $field, int $max = 255) => mb_substr(trim((string) ($item[$field] ?? '')), 0, $max);
            $itemType = strtolower($text('type'));
            $rows[] = [
                'line' => $index + 1,
                'name' => $text('name'),
                'type' => in_array($itemType, ['factory', 'manufacturer'], true) || $type === Supplier::TYPE_FACTORY ? Supplier::TYPE_FACTORY : Supplier::TYPE_SUPPLIER,
                'contact_name' => $text('contact_name'),
                'phone' => $text('phone', 30),
                'email' => $text('email'),
                'website_url' => $text('website_url', 500),
                'location' => $text('location') ?: $location,
                'address' => $text('address', 500),
                'country' => strlen($text('country')) === 2 ? strtoupper($text('country')) : '',
                'materials' => $text('materials', 2000),
                'source_url' => $text('source_url', 500),
            ];
        }

        // Same duplicate rules as the CSV import: name, email, phone or website.
        // A bad phone, email or website from the AI should not hide the business:
        // those fields are cleared, then every row is checked for duplicates.
        $check = fn (array $rows) => $this->importer->previewRows(array_map(fn ($row) => array_diff_key($row, ['source_url' => 1]) + ['status' => 'active'], $rows));
        $invalid = [];
        foreach ($check($rows)['failed'] as $failed) {
            $invalid[$failed['line']] = $failed['errors'];
            foreach ($rows as &$row) {
                if ($row['line'] === $failed['line']) {
                    $row['phone'] = $row['email'] = $row['website_url'] = $row['country'] = '';
                }
            }
            unset($row);
        }

        $checked = $check($rows);
        $state = [];
        foreach ($checked['valid'] as $row) {
            $state[$row['line']] = ['row' => $row, 'duplicate' => null, 'errors' => $invalid[$row['line']] ?? []];
        }
        foreach ($checked['duplicates'] as $row) {
            $state[$row['line']] = ['row' => $row, 'duplicate' => implode(' ', $row['errors']), 'errors' => []];
        }

        $results = [];
        foreach ($rows as $row) {
            $entry = $state[$row['line']] ?? null;
            if (! $entry) {
                continue;
            }
            $results[] = [
                'key' => 'r'.$row['line'],
                'name' => $row['name'],
                'type' => $row['type'],
                'contact_name' => $row['contact_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'website_url' => (string) ($entry['row']['website_url'] ?? $row['website_url']),
                'location' => $row['location'],
                'address' => $row['address'],
                'country' => $row['country'],
                'materials' => $row['materials'],
                'source_url' => $row['source_url'],
                'duplicate' => $entry['duplicate'],
                'errors' => $entry['errors'],
            ];
        }

        return $results;
    }

    /**
     * Add the chosen results as suppliers. Invalid fields (a malformed phone,
     * email or website) are dropped instead of losing the business; duplicates
     * are re-checked and skipped.
     *
     * @param  list<array>  $results
     * @return array{created: list<Supplier>, skipped: int}
     */
    public function add(array $results, ?int $userId): array
    {
        $rows = [];
        foreach (array_values($results) as $index => $result) {
            $rows[] = [
                'line' => $index + 1,
                'status' => 'active',
                'notes' => filled($result['source_url'] ?? null) ? 'Found online: '.$result['source_url'] : '',
            ] + array_intersect_key($result, array_flip(['name', 'type', 'contact_name', 'phone', 'email', 'website_url', 'location', 'address', 'country', 'materials']));
        }

        // Rows that fail validation lose the optional contact fields, then all
        // rows are checked together again so duplicates among them are caught.
        foreach ($this->importer->previewRows($rows)['failed'] as $row) {
            foreach (['phone', 'email', 'website_url', 'country'] as $field) {
                $rows[$row['line'] - 1][$field] = '';
            }
        }

        $created = $this->importer->importRows($this->importer->previewRows($rows)['valid'], $userId);

        return ['created' => $created, 'skipped' => count($rows) - count($created)];
    }
}
