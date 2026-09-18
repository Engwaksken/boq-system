<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProxySubscriptionRequest;
use App\Http\Resources\ProxySubscriptionResource;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentSettlementService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProxySubscriptionController extends Controller
{
    /**
     * Search users by name/email for beneficiary selection (admin only).
     */
    public function searchBeneficiaries(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $query = $validated['query'];
        $perPage = $validated['per_page'] ?? 20;

        $users = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->when($user->organisation_id !== null, function ($q) use ($user) {
                $q->where('organisation_id', $user->organisation_id);
            })
            ->select('id', 'name', 'email', 'phone', 'organisation_id')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Create a proxy subscription for a beneficiary.
     */
    public function store(StoreProxySubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $plan = Plan::find($validated['plan_id']);
        $beneficiary = User::find($validated['beneficiary_id']);

        // Authorize using policy
        $this->authorize('createProxy', [$plan, $beneficiary]);

        // Double-check plan availability
        if (! $plan || ! $plan->is_active || $plan->is_archived) {
            return response()->json([
                'success' => false,
                'error_code' => 'PLAN_NOT_AVAILABLE',
                'message' => __('subscriptions.plan_not_available'),
            ], 422);
        }

        // Check if beneficiary already has an active subscription
        $hasActiveSubscription = Subscription::query()
            ->where(function ($q) use ($beneficiary) {
                $q->where('beneficiary_id', $beneficiary->id)
                    ->orWhere(function ($q2) use ($beneficiary) {
                        $q2->whereNull('beneficiary_id')->where('user_id', $beneficiary->id);
                    });
            })
            ->where('status', 'active')
            ->exists();

        if ($hasActiveSubscription) {
            return response()->json([
                'success' => false,
                'error_code' => 'BENEFICIARY_HAS_ACTIVE_SUBSCRIPTION',
                'message' => 'The selected beneficiary already has an active subscription. Please cancel or wait for it to expire before creating a new one.',
            ], 422);
        }

        // Verify payment gateway
        $gateway = PaymentGateway::query()
            ->where('id', $validated['payment_gateway_id'])
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            return response()->json([
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_UNAVAILABLE',
                'message' => 'The selected payment gateway is not available.',
            ], 422);
        }

        // Check currency compatibility
        $supportedCurrencies = array_map('strtoupper', $gateway->supported_currencies ?? []);
        $planCurrency = strtoupper((string) $plan->currency);
        if ($supportedCurrencies && ! in_array($planCurrency, $supportedCurrencies, true)) {
            return response()->json([
                'success' => false,
                'error_code' => 'CURRENCY_NOT_SUPPORTED',
                'message' => 'The selected payment gateway does not support this plan currency.',
            ], 422);
        }

        // Create the proxy subscription
        $subscription = DB::transaction(function () use ($plan, $beneficiary, $user, $gateway, $validated) {
            $subscription = new Subscription([
                'plan_id' => $plan->id,
                'auto_renewal' => $plan->auto_renewal,
            ]);

            // Set beneficiary and payer
            $subscription->beneficiary_id = $beneficiary->id;
            $subscription->payer_id = $user->id;
            $subscription->user_id = $beneficiary->id; // For backward compatibility
            $subscription->organisation_id = $beneficiary->organisation_id ?? $user->organisation_id;
            $subscription->status = 'pending';
            $subscription->access_type = $plan->type;
            $subscription->payment_status = 'pending';

            $subscription->save();

            return $subscription;
        });

        Log::info('Proxy subscription created', [
            'subscription_id' => $subscription->id,
            'payer_id' => $user->id,
            'beneficiary_id' => $beneficiary->id,
            'plan_id' => $plan->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Proxy subscription created successfully. Proceed to payment.',
            'data' => new ProxySubscriptionResource($subscription->load('plan', 'payer', 'beneficiary')),
        ], 201);
    }

    /**
     * Initiate payment for a proxy subscription.
     */
    public function initiatePayment(Request $request, Subscription $subscription, PaymentManager $payments): JsonResponse
    {
        $this->authorize('manageProxy', $subscription);

        $user = $request->user();

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

        // Only pending or past_due subscriptions can be paid
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
        $phoneNumber = $validated['phone_number'] ?? $user->phone;
        if ((in_array($gateway->driver, $mobileDrivers, true) || $aggregatorMobile) && blank($phoneNumber)) {
            return response()->json([
                'success' => false,
                'error_code' => 'PHONE_NUMBER_REQUIRED',
                'message' => 'Enter the mobile money phone number to continue.',
            ], 422);
        }

        // Check for existing transaction with same idempotency key
        if ($idempotencyKey !== '') {
            $existing = Transaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('subscription_id', $subscription->id)
                ->where('payer_id', $user->id)
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

        $transaction = DB::transaction(function () use ($subscription, $gateway, $validated, $user, $idempotencyKey, $phoneNumber) {
            return Transaction::create([
                'reference' => 'BOQ-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'user_id' => $subscription->beneficiary_id, // Beneficiary is the "user" for the transaction
                'payer_id' => $user->id, // Admin who is paying
                'organisation_id' => $subscription->organisation_id,
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
                        'phone_number' => $phoneNumber,
                        'network' => $validated['network'] ?? null,
                    ],
                    'proxy_payment' => true,
                ],
            ]);
        });

        try {
            // Load the beneficiary user for payment initiation
            $transaction->load('user');
            $result = $payments->resolve($gateway)->initiate($transaction);

            $transaction->update([
                'status' => 'pending',
                'gateway_transaction_id' => $result['gateway_transaction_id'] ?? $transaction->gateway_transaction_id,
                'metadata' => array_merge($transaction->metadata ?? [], ['initiation' => $result]),
            ]);

            $this->audit($request, 'proxy.payment.initiated', $transaction, null, $transaction->fresh()->toArray(), $transaction->reference);

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

    /**
     * Verify payment and activate subscription for beneficiary.
     */
    public function verifyPayment(Request $request, Transaction $transaction, PaymentManager $payments, PaymentSettlementService $settlements): JsonResponse
    {
        $this->authorize('manageProxy', $transaction->subscription);

        $user = $request->user();

        // Ensure this is a proxy transaction
        if (! $transaction->subscription->isProxy()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_PROXY_TRANSACTION',
                'message' => 'This transaction is not a proxy payment.',
            ], 422);
        }

        if ($transaction->isSuccessful()) {
            return response()->json([
                'success' => true,
                'data' => $transaction->load('subscription.plan', 'invoice'),
            ]);
        }

        $gateway = $transaction->paymentGateway;
        if (! $gateway || ! $gateway->is_active) {
            return response()->json([
                'success' => false,
                'error_code' => 'PAYMENT_GATEWAY_UNAVAILABLE',
                'message' => 'Payment gateway unavailable.',
            ], 422);
        }

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

        // If payment successful, activate the subscription for the beneficiary
        if ($status === 'successful') {
            $subscriptionService = app(SubscriptionService::class);
            $subscription = $subscriptionService->activate($settled->subscription);

            // Send notification to beneficiary
            $this->notifyBeneficiary($subscription);

            $this->audit($request, 'proxy.payment.verified', $transaction, null, $transaction->fresh()->toArray(), $transaction->reference);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed and subscription activated for beneficiary.',
                'data' => $settled->load('subscription.plan', 'invoice'),
            ]);
        }

        $this->audit($request, 'proxy.payment.verified', $transaction, null, $transaction->fresh()->toArray(), $transaction->reference);

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated.',
            'data' => $settled->load('subscription.plan', 'invoice'),
        ]);
    }

    /**
     * List proxy subscriptions for current admin (payer).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $user = $request->user();

        $subscriptions = Subscription::query()
            ->where('payer_id', $user->id)
            ->whereNotNull('payer_id')
            ->whereRaw('payer_id != COALESCE(beneficiary_id, user_id)')
            ->with(['plan', 'payer', 'beneficiary', 'organisation', 'transactions.paymentGateway'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => ProxySubscriptionResource::collection($subscriptions->items()),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    /**
     * Show proxy subscription details.
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('viewProxy', $subscription);

        return response()->json([
            'success' => true,
            'data' => new ProxySubscriptionResource($subscription->load('plan', 'payer', 'beneficiary', 'organisation', 'transactions.paymentGateway', 'entitlements.feature')),
        ]);
    }

    /**
     * Cancel proxy subscription.
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('cancelProxy', $subscription);

        if (! $subscription->isActive()) {
            return response()->json([
                'success' => false,
                'error_code' => 'SUBSCRIPTION_NOT_ACTIVE',
                'message' => 'Only active subscriptions can be cancelled.',
            ], 422);
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancellation_date' => now(),
            'auto_renewal' => false,
        ]);

        $this->audit($request, 'proxy.subscription.cancelled', $subscription, null, $subscription->fresh()->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Proxy subscription cancelled successfully.',
            'data' => new ProxySubscriptionResource($subscription->load('plan', 'payer', 'beneficiary')),
        ]);
    }

    /**
     * Send notification to beneficiary about subscription activation.
     */
    private function notifyBeneficiary(Subscription $subscription): void
    {
        try {
            $beneficiary = $subscription->getBeneficiary();
            if (! $beneficiary) {
                Log::warning('Cannot notify beneficiary: beneficiary not found', [
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }

            // TODO: Implement actual notification (email, in-app, etc.)
            // For now, log the notification event
            Log::info('Beneficiary notified of proxy subscription activation', [
                'subscription_id' => $subscription->id,
                'beneficiary_id' => $beneficiary->id,
                'beneficiary_email' => $beneficiary->email,
                'plan_name' => $subscription->plan->name,
            ]);

            // Example of how to send email notification when notification system is ready:
            // $beneficiary->notify(new ProxySubscriptionActivated($subscription));
        } catch (Throwable $e) {
            report($e);
            Log::error('Failed to notify beneficiary of proxy subscription activation', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Audit log helper.
     */
    private function audit(Request $request, string $action, object $entity, ?array $previous, ?array $new, ?string $reference = null): void
    {
        $sanitizedNew = $this->sanitizePii($new);
        $sanitizedPrevious = $this->sanitizePii($previous);

        \App\Models\AuditLog::create([
            'user_id' => $request->user()?->id,
            'organisation_id' => $request->user()?->organisation_id,
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->id ?? null,
            'previous_value' => $sanitizedPrevious,
            'new_value' => $sanitizedNew,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'locale' => $request->user()?->locale,
            'reference' => $reference,
        ]);
    }

    /**
     * Sanitize PII fields from audit log data.
     */
    private function sanitizePii(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sanitized = $data;

        // Sanitize phone number in customer_input
        if (isset($sanitized['metadata']['customer_input']['phone_number'])) {
            $sanitized['metadata']['customer_input']['phone_number'] = '***';
        }

        // Sanitize network in customer_input
        if (isset($sanitized['metadata']['customer_input']['network'])) {
            $sanitized['metadata']['customer_input']['network'] = '***';
        }

        // Sanitize any other sensitive fields that might be present
        $sensitiveFields = ['password', 'token', 'secret', 'api_key', 'access_token', 'refresh_token'];
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '***';
            }
            if (isset($sanitized['metadata'][$field])) {
                $sanitized['metadata'][$field] = '***';
            }
        }

        return $sanitized;
    }
}