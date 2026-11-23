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
    // Login codes by SMS. 'log' writes codes to the log (development); 'msg91' sends through MSG91.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'msg91' => [
            'base_url' => env('MSG91_BASE_URL', 'https://control.msg91.com'),
            'auth_key' => env('MSG91_AUTH_KEY'),
            'template_id' => env('MSG91_TEMPLATE_ID'),
            'otp_variable' => env('MSG91_OTP_VARIABLE', 'otp'),
        ],
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
