<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gateways = [
            [
                'name' => 'Stripe',
                'code' => 'stripe',
                'driver' => 'stripe',
                'description' => 'Card and online payment gateway.',
                'config' => [
                    'mode' => config('payments.stripe.mode', 'test'),
                    'public_key' => config('payments.stripe.public_key', ''),
                    'secret_key' => config('payments.stripe.secret_key', ''),
                    'webhook_secret' => config('payments.stripe.webhook_secret', ''),
                ],
                'supported_currencies' => ['UGX', 'USD', 'EUR', 'GBP', 'KES', 'TZS', 'RWF'],
                'supported_countries' => ['UG', 'US', 'GB', 'KE', 'TZ', 'RW'],
                'supported_methods' => ['card'],
                'is_active' => true,
                'is_test_mode' => true,
                'payment_timeout_seconds' => 900,
                'refund_settings' => ['allow_refunds' => true, 'refund_window_days' => 30],
            ],
            [
                'name' => 'Bank Transfer',
                'code' => 'bank_transfer',
                'driver' => 'bank_transfer',
                'description' => 'Manual bank transfer with verification.',
                'config' => [
                    'details' => config('payments.bank_transfer.details', ''),
                ],
                'supported_currencies' => ['UGX', 'USD', 'EUR', 'GBP', 'KES', 'TZS', 'RWF'],
                'supported_countries' => ['UG', 'US', 'GB', 'KE', 'TZ', 'RW'],
                'supported_methods' => ['bank_transfer'],
                'is_active' => true,
                'is_test_mode' => true,
                'payment_timeout_seconds' => 172800,
                'refund_settings' => ['allow_refunds' => true, 'refund_window_days' => 30],
            ],
            [
                'name' => 'Mobile Money',
                'code' => 'mobile_money',
                'driver' => 'mobile_money',
                'description' => 'Mobile money payment gateway.',
                'config' => [
                    'provider' => config('payments.mobile_money.provider', 'mtn'),
                    'mode' => config('payments.mobile_money.mode', 'test'),
                    'merchant_code' => config('payments.mobile_money.merchant_code', ''),
                    'api_key' => config('payments.mobile_money.api_key', ''),
                    'api_secret' => config('payments.mobile_money.api_secret', ''),
                ],
                'supported_currencies' => ['UGX', 'KES', 'TZS', 'RWF'],
                'supported_countries' => ['UG', 'KE', 'TZ', 'RW'],
                'supported_methods' => ['mobile_money'],
                'is_active' => true,
                'is_test_mode' => true,
                'payment_timeout_seconds' => 900,
                'refund_settings' => ['allow_refunds' => true, 'refund_window_days' => 30],
            ],
            [
                'name' => 'Flutterwave',
                'code' => 'flutterwave',
                'driver' => 'stripe', // fallback or driver
                'description' => 'Flutterwave payment gateway.',
                'config' => [
                    'public_key' => '',
                    'secret_key' => '',
                    'encryption_key' => '',
                    'webhook_secret' => '',
                ],
                'supported_currencies' => ['UGX', 'USD', 'KES', 'NGN', 'GHS'],
                'supported_countries' => ['UG', 'KE', 'NG', 'GH'],
                'supported_methods' => ['card', 'mobile_money'],
                'is_active' => true,
                'is_test_mode' => true,
                'payment_timeout_seconds' => 900,
                'refund_settings' => ['allow_refunds' => true, 'refund_window_days' => 30],
            ],
            [
                'name' => 'Pesapal',
                'code' => 'pesapal',
                'driver' => 'stripe',
                'description' => 'Pesapal payment gateway.',
                'config' => [
                    'consumer_key' => '',
                    'consumer_secret' => '',
                    'webhook_secret' => '',
                ],
                'supported_currencies' => ['UGX', 'KES', 'TZS', 'USD'],
                'supported_countries' => ['UG', 'KE', 'TZ'],
                'supported_methods' => ['card', 'mobile_money'],
                'is_active' => true,
                'is_test_mode' => true,
                'payment_timeout_seconds' => 900,
                'refund_settings' => ['allow_refunds' => true, 'refund_window_days' => 30],
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(['code' => $gateway['code']], $gateway);
        }
    }
}
