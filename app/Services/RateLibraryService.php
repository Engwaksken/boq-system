<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\QuotationItem;
use App\Models\Rate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RateLibraryService
{
    /**
     * Approve a rate so it becomes effective.
     */
    public function approve(Rate $rate, User $verifier): Rate
    {
        $rate->update([
            'verification_status' => 'approved',
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'effective_from' => $rate->effective_from ?? now()->toDateString(),
            'review_required_at' => $rate->review_required_at ?? now()->copy()->addDays(90)->toDateString(),
            'is_active' => true,
        ]);

        return $rate->fresh();
    }

    /**
     * Reject a rate so it is hidden from effective matching.
     */
    public function reject(Rate $rate, User $verifier, ?string $reason = null): Rate
    {
        $rate->update([
            'verification_status' => 'rejected',
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'is_active' => false,
            'metadata' => array_merge($rate->metadata ?? [], ['rejection_reason' => $reason]),
        ]);

        return $rate->fresh();
    }

    /**
     * Search the library for effective, approved rates.
     */
    public function search(array $criteria): Builder
    {
        $query = Rate::query()
            ->with('supplier')
            ->where('verification_status', 'approved')
            ->where('is_active', true);

        $term = trim((string) ($criteria['query'] ?? ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like) {
                foreach (['item', 'description', 'code', 'category'] as $column) {
                    $q->orWhere($column, 'like', $like);
                }
                $q->orWhereJsonContains('name_translations', $term)
                    ->orWhereJsonContains('description_translations', $term);
            });
        }

        if (! empty($criteria['unit'])) {
            $query->where('unit', $criteria['unit']);
        }
        if (! empty($criteria['category'])) {
            $query->where('category', $criteria['category']);
        }
        if (! empty($criteria['region'])) {
            $query->where('region', $criteria['region']);
        }
        if (! empty($criteria['currency'])) {
            $query->where('currency', $criteria['currency']);
        }
        if (! empty($criteria['supplier_id'])) {
            $query->where('supplier_id', $criteria['supplier_id']);
        }

        return $query
            ->effectiveAt($criteria['at'] ?? null)
            ->orderBy('effective_from', 'desc')
            ->orderByDesc('verified_at');
    }

    /**
     * Suggest existing rates for a BOQ item, ranked by match strength.
     */
    public function suggestionsForItem(BoqItem $item, ?string $currency = null): array
    {
        $tokens = $this->tokens((string) $item->description);
        $query = Rate::query()
            ->with('supplier')
            ->where('verification_status', 'approved')
            ->where('is_active', true)
            ->effectiveAt()
            ->when($currency, fn ($q) => $q->where('currency', $currency));

        $needle = strtolower((string) $item->description);
        $rates = $query->get();

        $scored = [];

        foreach ($rates as $rate) {
            $haystack = strtolower(implode(' ', array_filter([
                $rate->item,
                $rate->description,
                $rate->category,
            ])));

            $hayTokens = $this->tokens($haystack);

            if (empty($hayTokens)) {
                continue;
            }

            $shared = count(array_intersect($tokens, $hayTokens));
            if ($shared === 0) {
                continue;
            }

            $score = (int) round(($shared / max(1, count($tokens))) * 60);

            if ($rate->region !== null && $item->location !== null
                && Str::lower((string) $rate->region) === Str::lower((string) $item->location)) {
                $score += 20;
            }
            if ($rate->unit !== null && $item->unit !== null
                && Str::lower((string) $rate->unit) === Str::lower((string) $item->unit)) {
                $score += 20;
            }

            // Prefer more recent effective rates.
            $score += (int) min(0, now()->diffInDays($rate->effective_from, false) / 30);

            $scored[] = [
                'rate' => $rate,
                'score' => max(0, min(100, $score)),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 10);
    }

    /**
     * Promote an approved quotation line into the rate library.
     */
    public function promoteFromQuotation(QuotationItem $item, User $user): Rate
    {
        $quotation = $item->quotation;

        return Rate::firstOrCreate(
            [
                'source_type' => 'supplier_quotation',
                'source_reference' => $quotation->quote_number,
                'item' => $item->product,
            ],
            [
                'code' => 'RATE-'.Str::upper(Str::slug($item->product).'-'.Str::random(4)),
                'description' => $item->description,
                'original_language' => $quotation->source_language ?? 'en',
                'category' => $quotation->boq?->category ?? null,
                'unit' => $item->unit ?? 'NO', // safest unit placeholder (number)
                'rate' => $item->unit_price,
                'currency' => $quotation->currency,
                'region' => $quotation->project?->region ?? null,
                'supplier_id' => $quotation->supplier_id,
                'effective_from' => now()->toDateString(),
                'verification_status' => 'approved',
                'verified_by' => $user->id,
                'verified_at' => now(),
                'review_required_at' => now()->copy()->addDays(90)->toDateString(),
                'is_active' => true,
                'created_by' => $user->id,
                'metadata' => [
                    'quotation_id' => $quotation->id,
                    'quotation_item_id' => $item->id,
                ],
            ]
        );
    }

    /**
     * Tokenise a free-text description for similarity matching.
     */
    protected function tokens(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? '';
        $words = array_values(array_filter(explode(' ', trim($text))));

        return array_values(array_unique(array_slice($words, 0, 12)));
    }
}