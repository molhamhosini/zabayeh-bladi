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
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'publishable' => env('STRIPE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
