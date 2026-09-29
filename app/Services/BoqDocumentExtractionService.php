<?php

namespace App\Services;

use App\Models\Boq;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BoqDocumentExtractionService
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_ITEMS = 5000;

    private const MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * @return array{count: int, warnings: list<string>, provider: string}
     */
    public function extract(Boq $boq): array
    {
        if (! in_array($boq->source_type, ['pdf', 'scan'], true) || ! $boq->source_file_path) {
            throw ValidationException::withMessages(['boq' => 'Only PDF and image BOQs can be extracted as documents.']);
        }

        $path = str_replace('\\', '/', $boq->source_file_path);

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) || in_array('..', explode('/', $path), true)) {
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file path is invalid.']);
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($path)) {
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file could not be found.']);
        }

        try {
            $size = $disk->size($path);

            if ($size <= 0 || $size > self::MAX_FILE_BYTES) {
                throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file must be no larger than 10 MB.']);
            }

            $contents = $disk->get($path);

            if (strlen($contents) > self::MAX_FILE_BYTES) {
                throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file must be no larger than 10 MB.']);
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ file could not be read.']);
        }

        if (! is_string($mime) || ! in_array($mime, self::MIME_TYPES, true)) {
            throw ValidationException::withMessages(['boq' => 'The uploaded BOQ must be a PDF, JPEG, PNG, or WebP file.']);
        }

        if ($boq->source_type === 'pdf' && $mime !== 'application/pdf') {
            throw ValidationException::withMessages(['boq' => 'The stored BOQ file is not a valid PDF.']);
        }

        if ($boq->source_type === 'scan' && ! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages(['boq' => 'The stored BOQ scan is not a valid image.']);
        }

        // Reading PDFs and photos needs a vision model: Gemini (PDFs and images) or
        // OpenAI (images). Providers from Admin > AI API Settings come first, then .env.
        $candidates = array_values(array_filter(
            $this->documentProviders($boq->organisation_id),
            fn (array $candidate) => $mime !== 'application/pdf' || $candidate['type'] === 'gemini',
        ));

        if ($candidates === []) {
            throw ValidationException::withMessages(['boq' => $mime === 'application/pdf'
                ? 'Reading PDF BOQs needs a Gemini AI provider. Add and enable one under Admin > AI API Settings, or upload the BOQ as Excel/CSV.'
                : 'Reading BOQ photos needs a Gemini or OpenAI AI provider. Add and enable one under Admin > AI API Settings, or upload the BOQ as Excel/CSV.']);
        }

        $items = null;
        $warnings = [];
        $provider = null;
        $lastError = null;

        foreach ($candidates as $candidate) {
            try {
                $response = $candidate['type'] === 'gemini'
                    ? $this->extractWithGemini($contents, $mime, $candidate)
                    : $this->extractWithOpenAi($contents, $mime, $candidate);
                [$items, $warnings] = $this->validateResponse($response, $boq, $candidate['type']);
                $provider = $candidate['type'];
                break;
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                report($exception);
                $lastError = $exception;
            }
        }

        if ($items === null) {
            throw ValidationException::withMessages(['boq' => 'The BOQ document could not be extracted by the configured AI provider.'.($lastError instanceof \Illuminate\Http\Client\RequestException && in_array($lastError->response->status(), [401, 403], true) ? ' The API key was rejected.' : '')]);
        }

        try {
            DB::transaction(function () use ($boq, $items): void {
                $lockedBoq = Boq::query()->lockForUpdate()->findOrFail($boq->id);
                $lockedBoq->items()->delete();

                foreach (array_chunk($items, 500) as $chunk) {
                    DB::table('boq_items')->insert($chunk);
                }

                $lockedBoq->update(['status' => 'under_review']);
            });
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['boq' => 'The extracted BOQ items could not be saved.']);
        }

        return ['count' => count($items), 'warnings' => $warnings, 'provider' => $provider];
    }

    /**
     * Vision-capable providers to try, in order.
     *
     * @return list<array{type: string, key: string, base_url: string, model: string}>
     */
    private function documentProviders(?int $organisationId): array
    {
        $candidates = [];

        $configured = \App\Models\AiProvider::query()
            ->enabled()
            ->forOrganisation($organisationId)
            ->whereIn('provider_type', ['gemini', 'google_gemini', 'openai'])
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
        // Accept base URLs saved with or without the API version.
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
Extract every bill-of-quantities item from this document. Return JSON only, with exactly this top-level shape:
{"items":[{"item_code":string|null,"description":string,"unit":string|null,"quantity":number,"original_rate":number|null,"amount":number|null}],"warnings":[string]}
Do not include headings, totals, subtotals, prose, markdown, or invented values as items. Preserve item wording and codes. Use null when a rate or amount is absent. Quantities, rates, and amounts must be non-negative JSON numbers. Put concise extraction uncertainties in warnings.
PROMPT;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function validateResponse(array $response, Boq $boq, string $provider): array
    {
        $text = $provider === 'gemini'
            ? ($response['candidates'][0]['content']['parts'][0]['text'] ?? null)
            : ($response['choices'][0]['message']['content'] ?? null);
        $decoded = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($decoded)
            || ! $this->hasExactKeys($decoded, ['items', 'warnings'])
            || ! is_array($decoded['items'])) {
            throw new RuntimeException('AI extraction response did not contain an items array.');
        }

        if ($decoded['items'] === [] || count($decoded['items']) > self::MAX_ITEMS) {
            throw ValidationException::withMessages(['boq' => 'The document must contain between 1 and 5,000 valid BOQ items.']);
        }

        $warnings = $decoded['warnings'];

        if (! is_array($warnings) || count($warnings) > 100) {
            throw new RuntimeException('AI extraction response contained invalid warnings.');
        }

        foreach ($warnings as $warning) {
            if (! is_string($warning) || trim($warning) === '' || mb_strlen($warning) > 500) {
                throw new RuntimeException('AI extraction response contained an invalid warning.');
            }
        }

        $now = now();
        $items = [];

        foreach ($decoded['items'] as $index => $item) {
            if (! is_array($item) || ! $this->hasExactKeys($item, [
                'item_code',
                'description',
                'unit',
                'quantity',
                'original_rate',
                'amount',
            ])) {
                throw new RuntimeException("AI extraction item {$index} was not an object.");
            }

            $description = $this->string($item, 'description', 2000, true);
            $quantity = $this->number($item, 'quantity', 1000000000, true);
            $rate = $this->number($item, 'original_rate', 1000000000000);
            $explicitAmount = $this->number($item, 'amount', 1000000000000);
            $amount = $explicitAmount ?? $quantity * ($rate ?? 0);

            if (! is_finite($amount) || $amount > 1000000000000) {
                throw new RuntimeException("AI extraction item {$index} contained an out-of-range amount.");
            }

            $items[] = [
                'boq_id' => $boq->id,
                'item_code' => $this->string($item, 'item_code', 255),
                'description' => $description,
                'unit' => $this->string($item, 'unit', 255),
                'quantity' => $quantity,
                'original_rate' => $rate,
                'approved_rate' => null,
                'amount' => round($amount, 2),
                'currency' => $boq->currency,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return [$items, array_values(array_map('trim', $warnings))];
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
            throw new RuntimeException("AI extraction item contained an invalid {$key}.");
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
            throw new RuntimeException("AI extraction item contained an invalid {$key}.");
        }

        $value = (float) $value;

        if (! is_finite($value) || $value < 0 || $value > $max) {
            throw new RuntimeException("AI extraction item contained an out-of-range {$key}.");
        }

        return $value;
    }
}
