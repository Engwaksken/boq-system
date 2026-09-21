<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(private readonly RateLibraryService $rates)
    {
    }

    /**
     * Recalculate subtotal, tax and total for a quotation from its lines.
     */
    public function recalculate(Quotation $quotation): Quotation
    {
        $subtotal = (float) $quotation->items()->sum('line_total');
        $taxRate = (float) $quotation->tax_rate;
        $discount = (float) $quotation->discount_amount;

        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = max(0, round($subtotal - $discount + $taxAmount, 2));

        $quotation->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
        ]);

        return $quotation->fresh();
    }

    /**
     * Persist a quotation with its lines and reconcile all totals.
     */
    public function store(Quotation $quotation, array $items, User $user): Quotation
    {
        $quotation->created_by = $user->id;
        $quotation->save();

        $quotation->items()->delete();

        foreach (array_values($items) as $index => $itemData) {
            $line = new QuotationItem();
            $line->quotation_id = $quotation->id;
            $line->sort_order = $index;
            $line->product = $itemData['product'] ?? 'Item';
            $line->description = $itemData['description'] ?? null;
            $line->unit = $itemData['unit'] ?? null;
            $line->quantity = $itemData['quantity'] ?? 1;
            $line->unit_price = $itemData['unit_price'] ?? 0;
            $line->vat_rate = $itemData['vat_rate'] ?? 0;
            $line->boq_item_id = $itemData['boq_item_id'] ?? null;
            $line->source_item_code = $itemData['source_item_code'] ?? null;
            $line->matched = (bool) ($itemData['matched'] ?? false);
            $line->matched_rate_id = $itemData['matched_rate_id'] ?? null;
            $line->approved = (bool) ($itemData['approved'] ?? false);
            $line->metadata = $itemData['metadata'] ?? null;
            $line->line_total = round((float) $line->quantity * (float) $line->unit_price, 2);
            $line->save();
        }

        return $this->recalculate($quotation->fresh());
    }

    /**
     * Mark a quotation as reviewed.
     */
    public function review(Quotation $quotation, User $user): Quotation
    {
        $quotation->update([
            'status' => 'reviewed',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return $quotation->fresh();
    }

    /**
     * Accept a quotation; approved lines are promoted into the rate library.
     */
    public function accept(Quotation $quotation, User $user): Quotation
    {
        DB::transaction(function () use ($quotation, $user): void {
            $quotation->update([
                'status' => 'accepted',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'accepted_at' => now(),
            ]);

            $approvedItems = $quotation->items()->where('approved', true)->get();

            foreach ($approvedItems as $item) {
                $item->matched_rate_id = $this->rates->promoteFromQuotation($item, $user)->id;
                $item->save();
            }
        });

        return $quotation->fresh(['items', 'supplier']);
    }

    /**
     * Reject a quotation and update its lines to rejected state.
     */
    public function reject(Quotation $quotation, User $user, ?string $reason = null): Quotation
    {
        $quotation->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'metadata' => array_merge($quotation->metadata ?? [], ['rejection_reason' => $reason]),
        ]);

        return $quotation->fresh();
    }

    /**
     * Approve or un-approve a single quotation line.
     */
    public function setLineApproved(QuotationItem $item, bool $approved): QuotationItem
    {
        $item->update(['approved' => $approved]);

        return $item->fresh();
    }
}