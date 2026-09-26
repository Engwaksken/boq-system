<?php

declare(strict_types=1);

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
    public function approve(
        Rate $rate,
        User $verifier
    ): Rate {
        $rate->update([
            'verification_status' => 'approved',
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'effective_from' =>
                $rate->effective_from
                ?? now()->toDateString(),
            'review_required_at' =>
                $rate->review_required_at
                ?? now()
                    ->copy()
                    ->addDays(90)
                    ->toDateString(),
            'is_active' => true,
        ]);

        return $rate->fresh();
    }

    /**
     * Reject a rate so it cannot be used in effective matching.
     */
    public function reject(
        Rate $rate,
        User $verifier,
        ?string $reason = null
    ): Rate {
        $rate->update([
            'verification_status' => 'rejected',
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'is_active' => false,
            'metadata' => array_merge(
                $rate->metadata ?? [],
                [
                    'rejection_reason' => $reason,
                ]
            ),
        ]);

        return $rate->fresh();
    }

    /**
     * Search approved, active and effective rates.
     *
     * Multi-word searches are tokenised so:
     *
     * "shot blasting steel"
     *
     * matches:
     *
     * "Shot blasting of steel (per m2)"
     */
    public function search(
        array $criteria
    ): Builder {
        $query = Rate::query()
            ->with('supplier')
            ->where(
                'verification_status',
                'approved'
            )
            ->where(
                'is_active',
                true
            );

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        $term = trim(
            (string) (
                $criteria['query']
                ?? ''
            )
        );

        if ($term !== '') {
            $tokens = $this->searchTokens(
                $term
            );

            /*
             * Each search token must appear somewhere in the
             * searchable rate fields.
             *
             * Example:
             *
             * shot      -> item contains shot
             * blasting  -> item contains blasting
             * steel     -> item contains steel
             *
             * This allows natural searches even where connector
             * words such as "of", "for", "per" appear in stored text.
             */
            foreach ($tokens as $token) {
                $like =
                    '%'.$token.'%';

                $query->where(
                    function (
                        Builder $search
                    ) use (
                        $like
                    ): void {
                        $search
                            ->where(
                                'item',
                                'like',
                                $like
                            )
                            ->orWhere(
                                'description',
                                'like',
                                $like
                            )
                            ->orWhere(
                                'code',
                                'like',
                                $like
                            )
                            ->orWhere(
                                'category',
                                'like',
                                $like
                            );
                    }
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Unit
        |--------------------------------------------------------------------------
        */

        if (
            ! empty(
                $criteria['unit']
            )
        ) {
            $query->where(
                'unit',
                $criteria['unit']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        if (
            ! empty(
                $criteria['category']
            )
        ) {
            $query->where(
                'category',
                $criteria['category']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Region
        |--------------------------------------------------------------------------
        */

        if (
            ! empty(
                $criteria['region']
            )
        ) {
            $query->where(
                'region',
                $criteria['region']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Currency
        |--------------------------------------------------------------------------
        */

        if (
            ! empty(
                $criteria['currency']
            )
        ) {
            $query->where(
                'currency',
                $criteria['currency']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Supplier
        |--------------------------------------------------------------------------
        */

        if (
            ! empty(
                $criteria['supplier_id']
            )
        ) {
            $query->where(
                'supplier_id',
                $criteria['supplier_id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Effective Date
        |--------------------------------------------------------------------------
        */

        return $query
            ->effectiveAt(
                $criteria['at']
                ?? null
            )
            ->orderByDesc(
                'effective_from'
            )
            ->orderByDesc(
                'verified_at'
            );
    }

    /**
     * Suggest existing effective rates for a BOQ item.
     */
    public function suggestionsForItem(
        BoqItem $item,
        ?string $currency = null
    ): array {
        $tokens =
            $this->tokens(
                (string) $item->description
            );

        if (empty($tokens)) {
            return [];
        }

        $query = Rate::query()
            ->with('supplier')
            ->where(
                'verification_status',
                'approved'
            )
            ->where(
                'is_active',
                true
            )
            ->effectiveAt()
            ->when(
                $currency,
                fn (
                    Builder $query
                ) =>
                    $query->where(
                        'currency',
                        $currency
                    )
            );

        $rates =
            $query->get();

        $scored = [];

        foreach ($rates as $rate) {
            $haystack =
                implode(
                    ' ',
                    array_filter([
                        $rate->item,
                        $rate->description,
                        $rate->category,
                    ])
                );

            $hayTokens =
                $this->tokens(
                    $haystack
                );

            if (empty($hayTokens)) {
                continue;
            }

            $shared =
                count(
                    array_intersect(
                        $tokens,
                        $hayTokens
                    )
                );

            if ($shared === 0) {
                continue;
            }

            /*
             * Base description similarity score.
             */
            $score =
                (int) round(
                    (
                        $shared
                        / max(
                            1,
                            count($tokens)
                        )
                    )
                    * 60
                );

            /*
             * Region match.
             */
            if (
                $rate->region !== null
                && $item->location !== null
                && Str::lower(
                    (string) $rate->region
                ) === Str::lower(
                    (string) $item->location
                )
            ) {
                $score += 20;
            }

            /*
             * Unit match.
             */
            if (
                $rate->unit !== null
                && $item->unit !== null
                && Str::lower(
                    (string) $rate->unit
                ) === Str::lower(
                    (string) $item->unit
                )
            ) {
                $score += 20;
            }

            /*
             * Prefer recent effective rates.
             */
            if ($rate->effective_from) {
                $ageDays =
                    now()->diffInDays(
                        $rate->effective_from
                    );

                if ($ageDays <= 30) {
                    $score += 10;
                } elseif (
                    $ageDays <= 90
                ) {
                    $score += 5;
                }
            }

            $scored[] = [
                'rate' => $rate,

                'score' =>
                    max(
                        0,
                        min(
                            100,
                            $score
                        )
                    ),
            ];
        }

        usort(
            $scored,
            fn (
                array $a,
                array $b
            ) =>
                $b['score']
                <=>
                $a['score']
        );

        return array_slice(
            $scored,
            0,
            10
        );
    }

    /**
     * Promote an approved quotation item into the rate library.
     */
    public function promoteFromQuotation(
        QuotationItem $item,
        User $user
    ): Rate {
        $quotation =
            $item->quotation;

        return Rate::query()
            ->firstOrCreate(
                [
                    'source_type' =>
                        'supplier_quotation',

                    'source_reference' =>
                        $quotation
                            ->quote_number,

                    'item' =>
                        $item->product,
                ],
                [
                    'code' =>
                        'RATE-'
                        .Str::upper(
                            Str::slug(
                                $item->product
                            )
                            .'-'
                            .Str::random(4)
                        ),

                    'description' =>
                        $item->description,

                    'original_language' =>
                        $quotation
                            ->source_language
                        ?? 'en',

                    'category' =>
                        $quotation
                            ->boq
                            ?->category,

                    'unit' =>
                        $item->unit
                        ?? 'NO',

                    'rate' =>
                        $item->unit_price,

                    'currency' =>
                        $quotation
                            ->currency,

                    'region' =>
                        $quotation
                            ->project
                            ?->region,

                    'supplier_id' =>
                        $quotation
                            ->supplier_id,

                    'effective_from' =>
                        now()
                            ->toDateString(),

                    'verification_status' =>
                        'approved',

                    'verified_by' =>
                        $user->id,

                    'verified_at' =>
                        now(),

                    'review_required_at' =>
                        now()
                            ->copy()
                            ->addDays(90)
                            ->toDateString(),

                    'is_active' =>
                        true,

                    'created_by' =>
                        $user->id,

                    'metadata' => [
                        'quotation_id' =>
                            $quotation->id,

                        'quotation_item_id' =>
                            $item->id,
                    ],
                ]
            );
    }

    /**
     * Convert a user search into meaningful search tokens.
     */
    protected function searchTokens(
        string $text
    ): array {
        $tokens =
            $this->tokens(
                $text
            );

        /*
         * Ignore common joining words because they should not make
         * construction rate searches unnecessarily restrictive.
         */
        $stopWords = [
            'a',
            'an',
            'and',
            'at',
            'by',
            'for',
            'from',
            'in',
            'of',
            'on',
            'or',
            'per',
            'the',
            'to',
            'with',
        ];

        $tokens =
            array_values(
                array_filter(
                    $tokens,
                    fn (
                        string $token
                    ) =>
                        ! in_array(
                            $token,
                            $stopWords,
                            true
                        )
                )
            );

        return $tokens;
    }

    /**
     * Tokenise free text for comparison.
     */
    protected function tokens(
        string $text
    ): array {
        $text =
            mb_strtolower(
                trim($text)
            );

        /*
         * Keep letters and numbers.
         *
         * \p{L} allows translated/non-English text to remain usable.
         */
        $text =
            preg_replace(
                '/[^\p{L}\p{N}]+/u',
                ' ',
                $text
            )
            ?? '';

        $words =
            preg_split(
                '/\s+/u',
                trim($text),
                -1,
                PREG_SPLIT_NO_EMPTY
            )
            ?: [];

        return array_values(
            array_unique(
                array_slice(
                    $words,
                    0,
                    20
                )
            )
        );
    }
}