<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class ProxySubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->resource;

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'access_type' => $subscription->access_type,
            'payment_status' => $subscription->payment_status,
            'start_date' => $subscription->start_date?->toIso8601String(),
            'end_date' => $subscription->end_date?->toIso8601String(),
            'renewal_date' => $subscription->renewal_date?->toIso8601String(),
            'grace_period_end_date' => $subscription->grace_period_end_date?->toIso8601String(),
            'cancellation_date' => $subscription->cancellation_date?->toIso8601String(),
            'auto_renewal' => $subscription->auto_renewal,
            'product_version' => $subscription->product_version,
            'metadata' => $subscription->metadata,
            'is_proxy' => $subscription->isProxy(),
            'plan' => $subscription->whenLoaded('plan', fn () => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'code' => $subscription->plan->code,
                'type' => $subscription->plan->type,
                'duration_days' => $subscription->plan->duration_days,
                'price' => $subscription->plan->price,
                'currency' => $subscription->plan->currency,
                'has_trial' => $subscription->plan->has_trial,
                'trial_days' => $subscription->plan->trial_days,
            ]),
            'payer' => $subscription->whenLoaded('payer', fn () => [
                'id' => $subscription->payer->id,
                'name' => $subscription->payer->name,
                'email' => $subscription->payer->email,
            ]),
            'beneficiary' => $subscription->whenLoaded('beneficiary', fn () => [
                'id' => $subscription->beneficiary->id,
                'name' => $subscription->beneficiary->name,
                'email' => $subscription->beneficiary->email,
            ]),
            'organisation' => $subscription->whenLoaded('organisation', fn () => [
                'id' => $subscription->organisation->id,
                'name' => $subscription->organisation->name,
            ]),
            'transactions' => $subscription->whenLoaded('transactions', fn () =>
                $subscription->transactions->map(fn ($t) => [
                    'id' => $t->id,
                    'reference' => $t->reference,
                    'amount' => $t->amount,
                    'currency' => $t->currency,
                    'payment_method' => $t->payment_method,
                    'status' => $t->status,
                    'initiated_at' => $t->initiated_at?->toIso8601String(),
                    'completed_at' => $t->completed_at?->toIso8601String(),
                    'gateway_transaction_id' => $t->gateway_transaction_id,
                    'payment_gateway' => $t->whenLoaded('paymentGateway', fn () => [
                        'id' => $t->paymentGateway->id,
                        'name' => $t->paymentGateway->name,
                        'code' => $t->paymentGateway->code,
                    ]),
                ])
            ),
            'entitlements' => $subscription->whenLoaded('entitlements', fn () =>
                $subscription->entitlements->map(fn ($e) => [
                    'id' => $e->id,
                    'feature' => $e->whenLoaded('feature', fn () => [
                        'id' => $e->feature->id,
                        'code' => $e->feature->code,
                        'name' => $e->feature->name,
                    ]),
                    'status' => $e->status,
                    'granted_at' => $e->granted_at?->toIso8601String(),
                    'expires_at' => $e->expires_at?->toIso8601String(),
                    'is_permanent' => $e->is_permanent,
                    'limits' => $e->limits,
                ])
            ),
            'created_at' => $subscription->created_at?->toIso8601String(),
            'updated_at' => $subscription->updated_at?->toIso8601String(),
        ];
    }
}