<?php

return [

    'dingconnect' => [
        'base_url' => env('DING_API_URL', 'https://api.dingconnect.com'),
        'api_key' => env('DING_API_KEY'),
        'customer_id' => env('DING_CUSTOMER_ID'),
        'webhook_secret' => env('DING_WEBHOOK_SECRET'),
        'callback_url' => env('DING_CALLBACK_URL'),
        'sandbox' => env('DING_SANDBOX', true),
        'timeout' => 30,
        'retry_attempts' => 2,
        'retry_delay' => 10,
    ],

    'pricing' => [
        'min_recharge' => env('PLATFORM_MIN_RECHARGE', 1),
        'max_recharge' => env('PLATFORM_MAX_RECHARGE', 10000),
        'markup' => env('PLATFORM_MARKUP', 0),
        'low_balance_threshold' => 10,
    ],

    'wallet' => [
        'currency' => env('PLATFORM_CURRENCY', 'GBP'),
        'load_fee_percentage' => env('PLATFORM_WALLET_LOAD_FEE_PERCENTAGE', 0),
        'load_fee_flat' => env('PLATFORM_WALLET_LOAD_FEE_FLAT', 0),
    ],

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'stripe'),
        'stripe' => [
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    'sms' => [
        'gateway' => env('SMS_GATEWAY', 'msg91'),
        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY'),
            'sender_id' => env('MSG91_SENDER_ID', 'MKNETW'),
            'template_id' => env('MSG91_TEMPLATE_ID'),
        ],
    ],

];
