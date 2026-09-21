<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentSettlementService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PaymentController extends Controller
{
    public function gateways(): JsonResponse
    {
        $gateways = PaymentGateway::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentGateway $gateway) => [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'code' => $gateway->code,
                'driver' => $gateway->driver,
                'description' => $gateway->description,
                'supported_currencies' => $gateway->supported_currencies,
                'supported_countries' => $gateway->supported_countries,
                'supported_methods' => $gateway->supported_methods,
                'is_test_mode' => $gateway->is_test_mode,
                'is_aggregator' => in_array($gateway->driver, ['iotec_pay', 'generic_aggregator'], true),
            ]);

        return response()->json(['success' => true, 'data' => $gateways]);
    }

    public function initiate(Request $request, Subscription $subscription, PaymentManager $payments): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $subscription->user_id === $user->id || ($user->organisation_id && $subscription->organisation_id === $user->organisation_id),
            403
        );

        $validated = $request->validate([
            'gateway_code' => ['required', 'string', 'exists:payment_gateways,code'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'network' => ['nullable', 'string', 'max:30'],
        ]);

        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key', '')));
        if (strlen($idempotencyKey) > 120) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_IDEMPOTENCY_KEY',
                'message' => 'The payment request identifier is invalid.',
            ], 422);
        }

        if (! in_array($subscription->status, ['pending', 'past_due'], true)) {
            return response()->json([
                'success' => false,
                'error_code' => 'SUBSCRIPTION_NOT_PAYABLE',
                'message' => 'This subscription is not awaiting payment.',
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
        $planCurrency = strtoupper((string) $subscription->plan->currency);
        if ($supportedCurrencies && ! in_array($planCurrency, $supportedCurrencies, true)) {
            return response()->json([
                'success' => false,
                'error_code' => 'CURRENCY_NOT_SUPPORTED',
                'message' => 'The selected payment method does not support this plan currency.',
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

        if ($idempotencyKey !== '') {
            $existing = Transaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('subscription_id', $subscription->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Existing payment attempt returned.',
                    'data' => [
                        'transaction' => $existing->load('invoice'),
                        'gateway' => $existing->metadata['initiation'] ?? [],
                    ],
                ], 201);
            }
        }

        $transaction = DB::transaction(function () use ($subscription, $gateway, $validated, $user, $idempotencyKey) {
            return Transaction::create([
                'reference' => 'BOQ-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'plan_id' => $subscription->plan_id,
                'subscription_id' => $subscription->id,
                'payment_gateway_id' => $gateway->id,
                'product_type' => 'plan',
                'product_id' => $subscription->plan_id,
                'amount' => $subscription->plan->price,
                'currency' => $subscription->plan->currency,
                'payment_method' => $validated['payment_method'] ?? $gateway->driver,
                'status' => 'initiated',
                'initiated_at' => now(),
                'metadata' => [
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

            $this->audit($request, 'payment.initiated', $transaction, null, $transaction->fresh()->toArray(), $transaction->reference);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully.',
                'data' => [
                    'transaction' => $transaction->fresh(),
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

            return response()->json([
                'success' => false,
                'error_code' => 'PAYMENT_INITIATION_FAILED',
                'message' => 'Payment could not be started. Please try another payment method.',
            ], 422);
        }
    }

    public function verify(
        Request $request,
        Transaction $transaction,
        PaymentManager $payments,
        PaymentSettlementService $settlements,
    ): JsonResponse {
        $user = $request->user();
        abort_unless(
            $transaction->user_id === $user->id || ($user->organisation_id && $transaction->organisation_id === $user->organisation_id),
            403
        );

        if ($transaction->isSuccessful()) {
            return response()->json(['success' => true, 'data' => $transaction->load('subscription.plan', 'invoice')]);
        }

        $gateway = $transaction->paymentGateway;
        abort_unless($gateway && $gateway->is_active, 422, 'Payment gateway unavailable.');

        $payload = array_merge($request->all(), [
            'gateway_transaction_id' => $request->input('gateway_transaction_id', $transaction->gateway_transaction_id),
            'reference' => $transaction->reference,
        ]);

        try {
            $result = $payments->resolve($gateway)->verify($payload);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error_code' => 'PAYMENT_VERIFICATION_FAILED',
                'message' => 'We could not confirm this payment yet.',
            ], 422);
        }

        $settled = $settlements->settle($transaction, $result, 'verification');
        $status = $settled->status;

        $this->audit($request, 'payment.verified', $transaction, null, $transaction->fresh()->toArray(), $transaction->reference);

        return response()->json([
            'success' => true,
            'message' => $status === 'successful' ? 'Payment confirmed and subscription activated.' : 'Payment status updated.',
            'data' => $settled->load('subscription.plan', 'invoice'),
        ]);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $transaction->user_id === $user->id || ($user->organisation_id && $transaction->organisation_id === $user->organisation_id),
            403
        );

        return response()->json([
            'success' => true,
            'data' => $transaction->load('subscription.plan', 'paymentGateway', 'invoice'),
        ]);
    }

    public function receipt(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $transaction->user_id === $user->id || ($user->organisation_id && $transaction->organisation_id === $user->organisation_id),
            403
        );

        if (! $transaction->isSuccessful()) {
            return response()->json([
                'success' => false,
                'error_code' => 'RECEIPT_NOT_AVAILABLE',
                'message' => 'A receipt is available after payment is confirmed.',
            ], 422);
        }

        $invoice = $transaction->invoice;
        if (! $invoice) {
            return response()->json([
                'success' => false,
                'error_code' => 'RECEIPT_NOT_READY',
                'message' => 'The receipt is being prepared. Refresh payment status and try again.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_number' => $invoice->invoice_number,
                'transaction_reference' => $transaction->reference,
                'gateway_reference' => $transaction->gateway_transaction_id,
                'payment_method' => $transaction->payment_method,
                'amount' => $invoice->amount,
                'tax_amount' => $invoice->tax_amount,
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'payment_date' => $invoice->payment_date?->toIso8601String(),
                'plan' => $transaction->plan?->name,
                'topup' => $transaction->topup?->name,
                'subscription_id' => $transaction->subscription_id,
            ],
        ]);
    }

    public function webhook(
        Request $request,
        string $gatewayCode,
        PaymentManager $payments,
        PaymentSettlementService $settlements,
    ): JsonResponse {
        $gateway = PaymentGateway::query()->where('code', $gatewayCode)->where('is_active', true)->first();
        if (! $gateway) {
            return response()->json(['success' => true], 202);
        }

        $reference = (string) (
            $request->input('externalId')
            ?? $request->input('data.tx_ref')
            ?? $request->input('OrderMerchantReference')
            ?? $request->input('order_merchant_reference')
            ?? $request->input('reference')
            ?? ''
        );
        $incomingGatewayId = (string) (
            $request->input('data.id')
            ?? $request->input('order_tracking_id')
            ?? $request->input('OrderTrackingId')
            ?? $request->input('transaction.id')
            ?? $request->input('id')
            ?? ''
        );

        if ($reference === '' && $incomingGatewayId === '') {
            return response()->json(['success' => true], 202);
        }

        $transaction = Transaction::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where(function ($query) use ($reference, $incomingGatewayId) {
                if ($reference !== '') {
                    $query->orWhere('reference', $reference);
                }
                if ($incomingGatewayId !== '') {
                    $query->orWhere('gateway_transaction_id', $incomingGatewayId);
                }
            })
            ->latest('id')
            ->first();

        if (! $transaction) {
            return response()->json(['success' => true], 202);
        }

        try {
            $result = $payments->resolve($gateway)->verify([
                'gateway_transaction_id' => $transaction->gateway_transaction_id ?: $incomingGatewayId,
                'reference' => $transaction->reference,
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => true], 202);
        }

        $settlements->settle($transaction, $result, 'webhook_verification');

        return response()->json(['success' => true]);
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
