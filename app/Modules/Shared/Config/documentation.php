<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('API_DOCUMENTATION_ENABLED', env('APP_ENV', 'production') !== 'production'),
];
