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
        $config = array_merge($gateway->config ?? [], [
            '_gateway_code' => $gateway->code,
            '_webhook_url' => $gateway->webhook_url,
            '_is_test_mode' => $gateway->is_test_mode,
        ]);

        return match ($gateway->driver) {
            'stripe' => new StripeGateway($config),
            'bank_transfer' => new BankTransferGateway($config),
            'mobile_money' => new MobileMoneyGateway($config),
            'mtn_momo' => new MtnMomoGateway($config),
            'airtel_money' => new AirtelMoneyGateway($config),
            'flutterwave' => new FlutterwaveGateway($config),
            'pesapal' => new PesapalGateway($config),
            'iotec_pay' => new IoTecPayGateway($config),
            'generic_aggregator' => new GenericAggregatorGateway($config),
            default => throw new InvalidArgumentException("Unsupported payment gateway driver [{$gateway->driver}]."),
        };
    }
}
