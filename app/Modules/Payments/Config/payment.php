<?php

declare(strict_types=1);

use App\Modules\Payments\Gateways\Implementations\{CreditCardGateway, PayPalGateway};

return [
    'currency' => env('PAYMENT_CURRENCY', 'USD'),
    'gateways' => [
        'credit_card' => [
            'driver' => CreditCardGateway::class, 'enabled' => env('PAYMENT_CREDIT_CARD_ENABLED', true),
            'credentials' => ['api_key' => env('CREDIT_CARD_API_KEY'), 'secret' => env('CREDIT_CARD_SECRET')],
        ],
        'paypal' => [
            'driver' => PayPalGateway::class, 'enabled' => env('PAYMENT_PAYPAL_ENABLED', true),
            'credentials' => ['client_id' => env('PAYPAL_CLIENT_ID'), 'secret' => env('PAYPAL_SECRET')],
        ],
    ],
];
