<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | These are configuration placeholders only. No real credentials are
    | stored here. Real API keys must be provided via environment variables
    | and encrypted at rest. Phase 1 does not wire real payment providers.
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'stripe'),

    'stripe' => [
        'mode' => env('STRIPE_MODE', 'test'),
        'public_key' => env('STRIPE_PUBLIC_KEY', ''),
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'checkout_url' => env('STRIPE_CHECKOUT_URL', ''),
    ],

    'bank_transfer' => [
        'details' => [
            'bank_name' => env('BANK_TRANSFER_BANK_NAME', ''),
            'account_name' => env('BANK_TRANSFER_ACCOUNT_NAME', ''),
            'account_number' => env('BANK_TRANSFER_ACCOUNT_NUMBER', ''),
            'swift_code' => env('BANK_TRANSFER_SWIFT_CODE', ''),
            'instructions' => env('BANK_TRANSFER_INSTRUCTIONS', ''),
        ],
    ],

    'mobile_money' => [
        'provider' => env('MOBILE_MONEY_PROVIDER', ''),
        'mode' => env('MOBILE_MONEY_MODE', 'test'),
        'api_key' => env('MOBILE_MONEY_API_KEY', ''),
        'api_secret' => env('MOBILE_MONEY_API_SECRET', ''),
        'merchant_code' => env('MOBILE_MONEY_MERCHANT_CODE', ''),
    ],

];
