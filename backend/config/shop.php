<?php

return [
    'currency' => 'aed',
    // UAE VAT. Prices entered in the dashboard are treated as VAT-exclusive; VAT is added at checkout.
    'vat_rate' => (float) env('SHOP_VAT_RATE', 5),
    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
    ],
    // Buy now, pay later. Use the sandbox URLs until the merchant accounts are approved.
    'tabby' => [
        'base_url' => env('TABBY_BASE_URL', 'https://api.tabby.ai'),
        'public_key' => env('TABBY_PUBLIC_KEY'),
        'secret_key' => env('TABBY_SECRET_KEY'),
        'merchant_code' => env('TABBY_MERCHANT_CODE'),
    ],
    'tamara' => [
        'base_url' => env('TAMARA_BASE_URL', 'https://api-sandbox.tamara.co'),
        'api_token' => env('TAMARA_API_TOKEN'),
        'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
        'instalments' => (int) env('TAMARA_INSTALMENTS', 4),
    ],
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'publishable' => env('STRIPE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
