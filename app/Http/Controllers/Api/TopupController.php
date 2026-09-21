<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Models\Topup;
use App\Models\TopupPurchase;
use App\Models\Transaction;
use App\Services\Payments\PaymentManager;
use App\Services\SubscriptionService;
use App\Services\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TopupController extends Controller
{
    public function __construct(
        private readonly TopupService $topups,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    /**
     * List active top-ups visible to the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $this->subscriptions->currentSubscription($user);

        $topups = Topup::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(function (Topup $topup) use ($user, $subscription) {
                return $this->present($topup, $user, $subscription);
            });

        return response()->json(['success' => true, 'data' => $topups]);
    }

    /**
     * Show a single top-up with ownership state.
     */
    public function show(Request $request, Topup $topup): JsonResponse
    {
        $user = $request->user();
        $subscription = $this->subscriptions->currentSubscription($user);

        if (! $topup->is_active || $topup->is_archived) {
            return response()->json([
                'success' => false,
                'error_code' => 'TOPUP_UNAVAILABLE',
                'message' => 'This top-up is no longer available.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->present($topup, $user, $subscription, true),
        ]);
    }

    /**
     * Initiate payment for a top-up.
     */
    public function initiate(Request $request, Topup $topup, PaymentManager $payments): JsonResponse
    {
        $user = $request->user();

        if (! $topup->is_active || $topup->is_archived) {
            return response()->json([
                'success' => false,
                'error_code' => 'TOPUP_UNAVAILABLE',
                'message' => 'This top-up is no longer available.',
            ], 422);
        }

        $subscription = $this->subscriptions->currentSubscription($user);

        if (! $this->topups->purchasableBy($topup, $user, $subscription?->plan)) {
            return response()->json([
                'success' => false,
                'error_code' => 'TOPUP_NOT_PURCHASABLE',
                'message' => 'This top-up is not available for your current plan or purchase limit.',
            ], 422);
        }

        $validated = $request->validate([
            'gateway_code' => ['required', 'string', 'exists:payment_gateways,code'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'network' => ['nullable', 'string', 'max:30'],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
        ]);

        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key', '')));
        if (strlen($idempotencyKey) > 120) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_IDEMPOTENCY_KEY',
                'message' => 'The payment request identifier is invalid.',
            ], 422);
        }

        $gateway = PaymentGateway::query()
            ->where('code', $validated['gateway_code'])
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            return response()->json([
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_UNAVAILABLE',
                'message' => 'The selected payment method is currently unavailable.',
            ], 422);
        }

        $supportedCurrencies = array_map('strtoupper', $gateway->supported_currencies ?? []);
        $topupCurrency = strtoupper((string) $topup->currency);
        if ($supportedCurrencies && ! in_array($topupCurrency, $supportedCurrencies, true)) {
            return response()->json([
                'success' => false,
                'error_code' => 'CURRENCY_NOT_SUPPORTED',
                'message' => 'The selected payment method does not support this top-up currency.',
            ], 422);
        }

        $supportedMethods = array_map('strtolower', $gateway->supported_methods ?? []);
        $requestedMethod = strtolower((string) ($validated['payment_method'] ?? ''));
        if ($requestedMethod !== '' && $supportedMethods && ! in_array($requestedMethod, $supportedMethods, true)) {
            return response()->json([
                'success' => false,
                'error_code' => 'PAYMENT_METHOD_NOT_SUPPORTED',
                'message' => 'The selected payment option is not available for this gateway.',
            ], 422);
        }

        $mobileDrivers = ['mtn_momo', 'airtel_money'];
        $aggregatorMobile = in_array($gateway->driver, ['iotec_pay', 'generic_aggregator'], true)
            && ! in_array($requestedMethod, ['card', 'visa', 'mastercard'], true);
        if ((in_array($gateway->driver, $mobileDrivers, true) || $aggregatorMobile) && blank($validated['phone_number'] ?? $user->phone)) {
            return response()->json([
                'success' => false,
                'error_code' => 'PHONE_NUMBER_REQUIRED',
                'message' => 'Enter the mobile money phone number to continue.',
            ], 422);
        }

        $targetSubscription = null;
        if (! empty($validated['subscription_id'])) {
            $targetSubscription = $user->subscriptions()->findOrFail($validated['subscription_id']);
        } else {
            $targetSubscription = $subscription;
        }

        if ($idempotencyKey !== '') {
            $existing = Transaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('product_type', 'topup')
                ->where('product_id', $topup->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Existing payment attempt returned.',
                    'data' => [
                        'transaction' => $existing->load('invoice', 'topupPurchase'),
                        'gateway' => $existing->metadata['initiation'] ?? [],
                    ],
                ], 201);
            }
        }

        $transaction = DB::transaction(function () use ($topup, $gateway, $validated, $user, $idempotencyKey, $targetSubscription) {
            $purchase = $this->topups->preparePurchase($topup, $user, $targetSubscription);

            return Transaction::create([
                'reference' => 'BOQ-TP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'plan_id' => $targetSubscription?->plan_id,
                'subscription_id' => $targetSubscription?->id,
                'payment_gateway_id' => $gateway->id,
                'product_type' => 'topup',
                'product_id' => $topup->id,
                'amount' => $topup->price,
                'currency' => $topup->currency,
                'payment_method' => $validated['payment_method'] ?? $gateway->driver,
                'status' => 'initiated',
                'initiated_at' => now(),
                'metadata' => [
                    'topup_code' => $topup->code,
                    'topup_purchase_id' => $purchase->id,
                    'customer_input' => [
                        'phone_number' => $validated['phone_number'] ?? $user->phone,
                        'network' => $validated['network'] ?? null,
                    ],
                ],
            ]);
        });

        try {
            $result = $payments->resolve($gateway)->initiate($transaction->load('user'));
            $transaction->update([
                'status' => 'pending',
                'gateway_transaction_id' => $result['gateway_transaction_id'] ?? $transaction->gateway_transaction_id,
                'metadata' => array_merge($transaction->metadata ?? [], ['initiation' => $result]),
            ]);

            TopupPurchase::where('id', $transaction->metadata['topup_purchase_id'])
                ->update(['transaction_id' => $transaction->id]);

            $this->audit($request, 'topup.initiated', $transaction, null, $transaction->fresh()->toArray(), $transaction->reference);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully.',
                'data' => [
                    'transaction' => $transaction->fresh(['invoice', 'topupPurchase']),
                    'gateway' => $result,
                ],
            ], 201);
        } catch (Throwable $e) {
            report($e);
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Payment initiation failed.',
            ]);

            if ($purchaseId = $transaction->metadata['topup_purchase_id'] ?? null) {
                TopupPurchase::where('id', $purchaseId)
                    ->update(['status' => 'revoked']);
            }

            return response()->json([
                'success' => false,
                'error_code' => 'PAYMENT_INITIATION_FAILED',
                'message' => 'Payment could not be started. Please try another payment method.',
            ], 422);
        }
    }

    /**
     * Present a top-up for the API response.
     */
    protected function present(Topup $topup, $user, ?Subscription $subscription, bool $withHistory = false): array
    {
        $activeCount = $this->topups->purchasedCount($topup, $user);

        $data = [
            'id' => $topup->id,
            'name' => $topup->name,
            'code' => $topup->code,
            'description' => $topup->description,
            'type' => $topup->type,
            'price' => (float) $topup->price,
            'currency' => $topup->currency,
            'duration_days' => $topup->duration_days,
            'is_permanent' => $topup->is_permanent,
            'release_version' => $topup->release_version,
            'included_features' => $topup->included_features ?? [],
            'usage_credits' => $topup->usage_credits ?? [],
            'display_order' => $topup->display_order,
            'owned' => $activeCount > 0,
            'active_purchases' => $activeCount,
            'applicable' => $topup->purchasable($subscription?->plan->code),
        ];

        if ($withHistory) {
            $data['purchases'] = $topup->purchases()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(25)
                ->get()
                ->map(fn (TopupPurchase $purchase) => [
                    'id' => $purchase->id,
                    'status' => $purchase->status,
                    'purchased_at' => $purchase->purchased_at?->toIso8601String(),
                    'activated_at' => $purchase->activated_at?->toIso8601String(),
                    'expires_at' => $purchase->expires_at?->toIso8601String(),
                    'is_permanent' => $purchase->is_permanent,
                    'version' => $purchase->version,
                    'transaction_reference' => $purchase->transaction?->reference,
                ]);
        }

        return $data;
    }

    private function audit(Request $request, string $action, object $entity, ?array $previous, ?array $new, ?string $reference = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'organisation_id' => $request->user()?->organisation_id,
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->id ?? null,
            'previous_value' => $previous,
            'new_value' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'locale' => $request->user()?->locale,
            'reference' => $reference,
        ]);
    }
}