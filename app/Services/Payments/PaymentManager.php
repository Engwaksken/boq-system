<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use InvalidArgumentException;

class PaymentManager
{
    /**
     * Resolve a payment gateway driver instance by code.
     */
    public function driver(string $code): PaymentGatewayInterface
    {
        $gateway = PaymentGateway::where('code', $code)->where('is_active', true)->first();

        if (! $gateway) {
            throw new InvalidArgumentException("Payment gateway [{$code}] is not configured or inactive.");
        }

        return $this->resolve($gateway);
    }

    /**
     * Resolve a payment gateway driver instance from a model.
     */
    public function resolve(PaymentGateway $gateway): PaymentGatewayInterface
    {
        $config = $gateway->config ?? [];

        return match ($gateway->driver) {
            'stripe' => new StripeGateway($config),
            'bank_transfer' => new BankTransferGateway($config),
            'mobile_money' => new MobileMoneyGateway($config),
            default => throw new InvalidArgumentException("Unsupported payment gateway driver [{$gateway->driver}]."),
        };
    }
}
