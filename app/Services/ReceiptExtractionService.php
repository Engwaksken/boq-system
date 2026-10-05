<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Reads a receipt image or PDF and returns the expense fields it contains, so a
 * purchaser can review and correct them before the expense is saved.
 *
 * The extraction is stateless: nothing is persisted here. The caller shows the
 * returned fields for review and later creates the expense and attaches the
 * receipt through the normal, authorized endpoints.
 */
class ReceiptExtractionService
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * @return array{supplier: string|null, purchase_date: string|null, description: string, quantity: float, unit: string, rate: float|null, total: float|null, currency: string|null, payment_method: string|null, warnings: list<string>, provider: string}
     */
    public function extract(UploadedFile $file, ?int $organisationId): array
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw ValidationException::withMessages(['file' => 'The receipt could not be read.']);
        }

        $size = filesize($path);

        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages(['file' => 'The receipt must be no larger than 10 MB.']);
        }

        $contents = (string) file_get_contents($path);

        if ($contents === '' || strlen($contents) > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages(['file' => 'The receipt could not be read.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (! is_string($mime) || ! in_array($mime, self::MIME_TYPES, true)) {
            throw ValidationException::withMessages(['file' => 'The receipt must be a PDF, JPEG, PNG, or WebP file.']);
        }

        $candidates = array_values(array_filter(
            $this->providers($organisationId),
            fn (array $candidate) => $mime !== 'application/pdf' || $candidate['type'] === 'gemini',
        ));

        if ($candidates === []) {
            throw ValidationException::withMessages(['file' => $mime === 'application/pdf'
                ? 'Reading PDF receipts needs a vision-capable AI provider. Add and enable one under Admin > AI API Settings.'
                : 'Reading receipt photos needs a vision-capable AI provider. Add and enable one under Admin > AI API Settings.']);
        }

        $fields = null;
        $provider = null;
        $lastError = null;

        foreach ($candidates as $candidate) {
            try {
                $response = $candidate['type'] === 'gemini'
                    ? $this->extractWithGemini($contents, $mime, $candidate)
                    : $this->extractWithOpenAi($contents, $mime, $candidate);
                $fields = $this->validateResponse($response, $candidate['type']);
                $provider = $candidate['type'];
                break;
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                report($exception);
                $lastError = $exception;
            }
        }

        if ($fields === null) {
            throw ValidationException::withMessages(['file' => 'The receipt could not be read by the configured AI provider.'.($lastError instanceof RequestException && in_array($lastError->response->status(), [401, 403], true) ? ' The API key was rejected.' : '')]);
        }

        return $fields + ['provider' => $provider];
    }

    /**
     * Vision-capable providers to try, in order.
     *
     * @return list<array{type: string, key: string, base_url: string, model: string}>
     */
    private function providers(?int $organisationId): array
    {
        $candidates = [];

        $configured = AiProvider::query()
            ->enabled()
            ->forOrganisation($organisationId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get();

        foreach ($configured as $record) {
            if (blank($record->api_key)) {
                continue;
            }
            $isGemini = in_array(strtolower($record->provider_type), ['gemini', 'google_gemini'], true);
            $candidates[] = [
                'type' => $isGemini ? 'gemini' : 'openai',
                'key' => (string) $record->api_key,
                'base_url' => rtrim((string) ($record->api_base_url ?: ($isGemini ? 'https://generativelanguage.googleapis.com' : 'https://api.openai.com')), '/'),
                'model' => (string) ($record->default_model ?: ($isGemini ? 'gemini-2.5-flash' : 'gpt-4o-mini')),
            ];
        }

        $envProvider = strtolower((string) config('services.ai_provider'));
        if (in_array($envProvider, ['gemini', 'openai'], true) && filled(config("services.{$envProvider}.key"))) {
            $candidates[] = [
                'type' => $envProvider,
                'key' => (string) config("services.{$envProvider}.key"),
                'base_url' => rtrim((string) config("services.{$envProvider}.base_url"), '/'),
                'model' => (string) config("services.{$envProvider}.model"),
            ];
        }

        return $candidates;
    }

    private function extractWithGemini(string $contents, string $mime, array $provider): array
    {
        $base = preg_replace('#/v1(beta)?$#', '', $provider['base_url']);

        return Http::timeout(90)->post(
            $base.'/v1/models/'.$provider['model'].':generateContent?key='.$provider['key'],
            [
                'contents' => [[
                    'parts' => [
                        ['text' => $this->prompt()],
                        ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($contents)]],
                    ],
                ]],
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ]
        )->throw()->json();
    }

    private function extractWithOpenAi(string $contents, string $mime, array $provider): array
    {
        $base = preg_replace('#/v1$#', '', $provider['base_url']);

        return Http::timeout(90)->withToken($provider['key'])->post($base.'/v1/chat/completions', [
            'model' => $provider['model'],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $this->prompt()],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,".base64_encode($contents)]],
                ],
            ]],
            'response_format' => ['type' => 'json_object'],
        ])->throw()->json();
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Extract the expense details from this receipt. Return JSON only, with exactly this top-level shape:
{"supplier":string|null,"purchase_date":"YYYY-MM-DD"|null,"description":string,"quantity":number,"unit":string,"rate":number|null,"total":number|null,"currency":string|null,"payment_method":string|null,"warnings":[string]}
Use null when a value is absent or unreadable. purchase_date must be an ISO date (YYYY-MM-DD) or null. quantity must be a positive number (default 1 when the receipt shows a single item and no quantity). rate and total must be non-negative numbers. currency must be a three-letter code. Preserve the supplier name and item wording exactly. Put concise extraction uncertainties in warnings.
PROMPT;
    }

    /**
     * @return array{supplier: string|null, purchase_date: string|null, description: string, quantity: float, unit: string, rate: float|null, total: float|null, currency: string|null, payment_method: string|null, warnings: list<string>}
     */
    private function validateResponse(array $response, string $provider): array
    {
        $text = $provider === 'gemini'
            ? ($response['candidates'][0]['content']['parts'][0]['text'] ?? null)
            : ($response['choices'][0]['message']['content'] ?? null);
        $decoded = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($decoded)
            || ! $this->hasExactKeys($decoded, ['supplier', 'purchase_date', 'description', 'quantity', 'unit', 'rate', 'total', 'currency', 'payment_method', 'warnings'])
            || ! is_array($decoded['warnings'])) {
            throw new RuntimeException('AI extraction response did not contain the expected fields.');
        }

        if (count($decoded['warnings']) > 100) {
            throw new RuntimeException('AI extraction response contained too many warnings.');
        }

        $warnings = [];
        foreach ($decoded['warnings'] as $warning) {
            if (! is_string($warning) || trim($warning) === '' || mb_strlen($warning) > 500) {
                throw new RuntimeException('AI extraction response contained an invalid warning.');
            }
            $warnings[] = trim($warning);
        }

        $description = $this->string($decoded, 'description', 10000, true);
        $quantity = $this->number($decoded, 'quantity', 1000000000, true);
        if ($quantity <= 0) {
            throw new RuntimeException('AI extraction response contained a non-positive quantity.');
        }
        $unit = $this->string($decoded, 'unit', 50, true);

        $rate = $this->number($decoded, 'rate', 1000000000000);
        $total = $this->number($decoded, 'total', 1000000000000);
        $purchaseDate = $this->date($decoded);
        $currency = $this->currency($decoded);
        $supplier = $this->string($decoded, 'supplier', 255);
        $paymentMethod = $this->string($decoded, 'payment_method', 100);

        return [
            'supplier' => $supplier,
            'purchase_date' => $purchaseDate,
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit,
            'rate' => $rate,
            'total' => $total,
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'warnings' => $warnings,
        ];
    }

    private function hasExactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }

    private function string(array $item, string $key, int $max, bool $required = false): ?string
    {
        $value = $item[$key] ?? null;

        if ($value === null && ! $required) {
            return null;
        }

        if (! is_string($value) || ($required && trim($value) === '') || mb_strlen($value) > $max) {
            throw new RuntimeException("AI extraction response contained an invalid {$key}.");
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function number(array $item, string $key, float $max, bool $required = false): ?float
    {
        $value = $item[$key] ?? null;

        if ($value === null && ! $required) {
            return null;
        }

        if (! is_int($value) && ! is_float($value)) {
            throw new RuntimeException("AI extraction response contained an invalid {$key}.");
        }

        $value = (float) $value;

        if (! is_finite($value) || $value < 0 || $value > $max) {
            throw new RuntimeException("AI extraction response contained an out-of-range {$key}.");
        }

        return $value;
    }

    private function date(array $item): ?string
    {
        $value = $item['purchase_date'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new RuntimeException('AI extraction response contained an invalid purchase_date.');
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new RuntimeException('AI extraction response contained an invalid purchase_date.');
        }

        return $value;
    }

    private function currency(array $item): ?string
    {
        $value = $item['currency'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^[A-Za-z]{3}$/', $value)) {
            throw new RuntimeException('AI extraction response contained an invalid currency.');
        }

        return strtoupper($value);
    }
}
