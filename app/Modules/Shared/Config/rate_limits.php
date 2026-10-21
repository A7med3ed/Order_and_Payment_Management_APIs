<?php

declare(strict_types=1);

return [
    'login_per_minute' => (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 5),
    'register_per_minute' => (int) env('RATE_LIMIT_REGISTER_PER_MINUTE', 10),
    'api_per_minute' => (int) env('RATE_LIMIT_API_PER_MINUTE', 60),
    'documentation_per_minute' => (int) env('RATE_LIMIT_DOCUMENTATION_PER_MINUTE', 60),
    'payments_per_minute' => (int) env('RATE_LIMIT_PAYMENTS_PER_MINUTE', 10),
    'payments_per_order_per_minute' => (int) env('RATE_LIMIT_PAYMENTS_PER_ORDER_PER_MINUTE', 3),
];
