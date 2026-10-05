<?php

namespace App\Livewire;

use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Topup;
use App\Models\Transaction;
use App\Services\Payments\CheckoutService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Web checkout for a pending subscription or a top-up.
 *
 * Hosted card gateways redirect away and come back to this page with ?transaction=ID;
 * mobile money gateways push a prompt to the phone and this page polls for the result.
 */
#[Layout('layouts.app')]
class Checkout extends Component
{
    public string $type = 'plan';

    public int $productId = 0;

    public string $gatewayCode = '';

    public string $paymentMethod = '';

    public string $phone = '';

    public string $network = '';

    #[Url(as: 'transaction')]
    public ?int $transactionId = null;

    public function mount(string $type, int $id): void
    {
        abort_unless(in_array($type, ['plan', 'topup'], true), 404);

        $this->type = $type;
        $this->productId = $id;
        $this->phone = (string) (auth()->user()->phone ?? '');

        $this->product(); // authorises and 404s early

        if ($this->transactionId) {
            $this->ownedTransaction(); // must belong to this user and product
            $this->checkStatus();
        }

        $this->gatewayCode = (string) ($this->gateways()->first()?->code ?? '');
    }

    public function pay(CheckoutService $checkout): void
    {
        $this->validate(['gatewayCode' => ['required', 'string']]);

        // The phone number only matters for mobile money; card payments ignore it.
        $gateway = $this->gateways()->firstWhere('code', $this->gatewayCode);
        $needsPhone = $gateway && $checkout->needsPhone($gateway, $this->paymentMethod);

        if ($needsPhone) {
            $this->validate([
                'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
                'network' => ['nullable', 'string', 'max:30'],
            ], ['phone.regex' => 'Enter a valid phone number including the country code, e.g. +44 7700 900123.']);
        }

        $input = [
            'gateway_code' => $this->gatewayCode,
            'payment_method' => $this->paymentMethod ?: null,
            'phone_number' => $needsPhone ? $this->phone : null,
            'network' => $needsPhone ? ($this->network ?: null) : null,
        ];
        $returnUrl = route('checkout', ['type' => $this->type, 'id' => $this->productId]);

        try {
            $result = $this->type === 'plan'
                ? $checkout->startPlanPayment(auth()->user(), $this->product(), $input, $returnUrl)
                : $checkout->startTopupPayment(auth()->user(), $this->product(), $input, $returnUrl);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'phone_number' ? 'phone' : ($field === 'gateway_code' ? 'gatewayCode' : $field), $messages[0]);
            }

            return;
        }

        $this->transactionId = $result['transaction']->id;

        // Hosted card pages: send the customer to the gateway; they return here with ?transaction=ID.
        if ($result['checkout_url']) {
            $this->redirect($result['checkout_url']);
        }
    }

    /**
     * Polled while a payment is pending.
     */
    public function checkStatus(?CheckoutService $checkout = null): void
    {
        $checkout ??= app(CheckoutService::class);
        $transaction = $checkout->refresh($this->ownedTransaction());

        if ($transaction->isSuccessful()) {
            session()->flash('message', 'Payment received. Thank you!');

            $this->redirectRoute($this->type === 'plan' ? 'subscriptions.index' : 'topups.index');
        }
    }

    public function startOver(): void
    {
        $this->transactionId = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        $transaction = $this->transactionId ? $this->ownedTransaction() : null;
        $gateways = $this->gateways();
        $selected = $gateways->firstWhere('code', $this->gatewayCode);

        return view('livewire.checkout', [
            'product' => $this->product(),
            'summary' => $this->summary(),
            'gateways' => $gateways,
            'needsPhone' => $selected ? app(CheckoutService::class)->needsPhone($selected, $this->paymentMethod) : false,
            'methods' => $selected?->supported_methods ?? [],
            'transaction' => $transaction,
        ]);
    }

    private function product(): Subscription|Topup
    {
        $user = auth()->user();

        if ($this->type === 'plan') {
            $subscription = Subscription::with('plan')->findOrFail($this->productId);

            abort_unless(
                $subscription->user_id === $user->id || ($user->organisation_id && $subscription->organisation_id === $user->organisation_id),
                403
            );

            return $subscription;
        }

        $topup = Topup::findOrFail($this->productId);
        abort_unless($topup->is_active && ! $topup->is_archived, 404);

        return $topup;
    }

    /** @return array{name: string, amount: float, currency: string, detail: string} */
    private function summary(): array
    {
        $product = $this->product();

        if ($product instanceof Subscription) {
            return [
                'name' => $product->plan?->name ?? 'Subscription',
                'amount' => (float) ($product->plan?->price ?? 0),
                'currency' => (string) ($product->plan?->currency ?? ''),
                'detail' => $this->planDurationDetail($product->plan),
            ];
        }

        return [
            'name' => $product->name,
            'amount' => (float) $product->price,
            'currency' => (string) $product->currency,
            'detail' => str_replace('_', ' ', (string) $product->type),
        ];
    }

    private function planDurationDetail(?Plan $plan): string
    {
        if ($plan?->duration_hours) {
            return $plan->duration_hours.' hours';
        }

        if ($plan?->duration_days) {
            return $plan->duration_days.' days';
        }

        return ucfirst((string) $plan?->type);
    }

    /** @return \Illuminate\Support\Collection<int, PaymentGateway> */
    private function gateways()
    {
        return app(CheckoutService::class)->gatewaysFor($this->summary()['currency']);
    }

    private function ownedTransaction(): Transaction
    {
        $transaction = Transaction::with('paymentGateway')->findOrFail($this->transactionId);

        abort_unless($transaction->user_id === auth()->id(), 403);

        $matchesProduct = $this->type === 'plan'
            ? (int) $transaction->subscription_id === $this->productId && $transaction->product_type === 'plan'
            : (int) $transaction->product_id === $this->productId && $transaction->product_type === 'topup';

        abort_unless($matchesProduct, 404);

        return $transaction;
    }
}
