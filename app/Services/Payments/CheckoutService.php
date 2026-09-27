<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Models\Topup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TopupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Starts and confirms payments for the web checkout (plans and top-ups).
 * Uses the same gateways, transaction records and settlement as the mobile API.
 */
class CheckoutService
{
    private const MOBILE_DRIVERS = ['mtn_momo', 'airtel_money'];

    private const AGGREGATOR_DRIVERS = ['iotec_pay', 'generic_aggregator'];

    public function __construct(
        private PaymentManager $payments,
        private PaymentSettlementService $settlements,
        private SubscriptionService $subscriptions,
        private TopupService $topups,
    ) {}

    /**
     * Active gateways that can charge the given currency.
     *
     * @return \Illuminate\Support\Collection<int, PaymentGateway>
     */
    public function gatewaysFor(string $currency)
    {
        $currency = strtoupper($currency);

        return PaymentGateway::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->filter(function (PaymentGateway $gateway) use ($currency) {
                $supported = array_map('strtoupper', $gateway->supported_currencies ?? []);

                return $supported === [] || in_array($currency, $supported, true);
            })
            ->values();
    }

    /**
     * Whether the gateway needs a mobile money number for the chosen method.
     */
    public function needsPhone(PaymentGateway $gateway, ?string $method = null): bool
    {
        $method = strtolower((string) $method);

        return in_array($gateway->driver, self::MOBILE_DRIVERS, true)
            || (in_array($gateway->driver, self::AGGREGATOR_DRIVERS, true) && ! in_array($method, ['card', 'visa', 'mastercard'], true))
            || $gateway->driver === 'mobile_money';
    }

    /**
     * @param  array{gateway_code: string, payment_method?: ?string, phone_number?: ?string, network?: ?string}  $input
     * @return array{transaction: Transaction, checkout_url: ?string}
     */
    public function startPlanPayment(User $user, Subscription $subscription, array $input, string $returnUrl): array
    {
        abort_unless(
            $subscription->user_id === $user->id || ($user->organisation_id && $subscription->organisation_id === $user->organisation_id),
            403
        );

        if (! in_array($subscription->status, ['pending', 'past_due'], true)) {
            throw ValidationException::withMessages(['gateway_code' => 'This subscription is not awaiting payment.']);
        }

        $plan = $subscription->plan;

        return $this->start($user, $input, $returnUrl, [
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'product_type' => 'plan',
            'product_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => $plan->currency,
        ]);
    }

    /**
     * @param  array{gateway_code: string, payment_method?: ?string, phone_number?: ?string, network?: ?string}  $input
     * @return array{transaction: Transaction, checkout_url: ?string}
     */
    public function startTopupPayment(User $user, Topup $topup, array $input, string $returnUrl): array
    {
        $current = $this->subscriptions->currentSubscription($user);

        if (! $topup->is_active || $topup->is_archived || ! $this->topups->purchasableBy($topup, $user, $current?->plan)) {
            throw ValidationException::withMessages(['gateway_code' => 'This top-up is not available for your current plan or purchase limit.']);
        }

        return $this->start($user, $input, $returnUrl, [
            'subscription_id' => $current?->id,
            'product_type' => 'topup',
            'product_id' => $topup->id,
            'amount' => $topup->price,
            'currency' => $topup->currency,
        ]);
    }

    /**
     * Ask the gateway for the payment's current status and settle it.
     */
    public function refresh(Transaction $transaction): Transaction
    {
        if ($transaction->isSuccessful() || in_array($transaction->status, ['failed', 'cancelled'], true)) {
            return $transaction;
        }

        $gateway = $transaction->paymentGateway;

        if (! $gateway || ! $transaction->gateway_transaction_id) {
            return $transaction;
        }

        try {
            $result = $this->payments->resolve($gateway)->verify([
                'gateway_transaction_id' => $transaction->gateway_transaction_id,
                'reference' => $transaction->reference,
            ]);
        } catch (Throwable $e) {
            report($e);

            return $transaction;
        }

        return $this->settlements->settle($transaction, $result, 'web_checkout');
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $product
     * @return array{transaction: Transaction, checkout_url: ?string}
     */
    private function start(User $user, array $input, string $returnUrl, array $product): array
    {
        $gateway = PaymentGateway::query()
            ->where('code', $input['gateway_code'] ?? '')
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            throw ValidationException::withMessages(['gateway_code' => 'Choose an available payment method.']);
        }

        $supported = array_map('strtoupper', $gateway->supported_currencies ?? []);

        if ($supported !== [] && ! in_array(strtoupper((string) $product['currency']), $supported, true)) {
            throw ValidationException::withMessages(['gateway_code' => 'This payment method does not support '.$product['currency'].'.']);
        }

        $method = $input['payment_method'] ?? null;
        $phone = trim((string) ($input['phone_number'] ?? '')) ?: $user->phone;

        if ($this->needsPhone($gateway, $method) && blank($phone)) {
            throw ValidationException::withMessages(['phone_number' => 'Enter the mobile money number to charge, including the country code.']);
        }

        $transaction = DB::transaction(fn () => Transaction::create($product + [
            'reference' => 'BOQ-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'payment_gateway_id' => $gateway->id,
            'payment_method' => $method ?: $gateway->driver,
            'status' => 'initiated',
            'initiated_at' => now(),
            'metadata' => [
                'channel' => 'web',
                'return_url' => $returnUrl,
                'cancel_url' => $returnUrl,
                'customer_input' => [
                    'phone_number' => $phone,
                    'network' => $input['network'] ?? null,
                ],
            ],
        ]));

        // Gateways send the customer back here; include the transaction so the page can confirm it.
        $returnUrl .= (str_contains($returnUrl, '?') ? '&' : '?').'transaction='.$transaction->id;
        $transaction->update(['metadata' => array_merge($transaction->metadata, [
            'return_url' => $returnUrl,
            'cancel_url' => $returnUrl,
        ])]);

        try {
            $result = $this->payments->resolve($gateway)->initiate($transaction->load('user'));
        } catch (Throwable $e) {
            report($e);

            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => 'Payment initiation failed.',
            ]);

            throw ValidationException::withMessages(['gateway_code' => 'Payment could not be started. Please try another payment method.']);
        }

        $transaction->update([
            'status' => 'pending',
            'gateway_transaction_id' => $result['gateway_transaction_id'] ?? $transaction->gateway_transaction_id,
            'metadata' => array_merge($transaction->metadata ?? [], ['initiation' => $result]),
        ]);

        return [
            'transaction' => $transaction->fresh(),
            'checkout_url' => filled($result['checkout_url'] ?? null) ? (string) $result['checkout_url'] : null,
        ];
    }
}
