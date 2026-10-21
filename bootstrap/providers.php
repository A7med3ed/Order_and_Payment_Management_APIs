<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Modules\Shared\Providers\SharedServiceProvider::class,
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    App\Modules\Orders\Providers\OrdersServiceProvider::class,
    App\Modules\Ledger\Providers\LedgerServiceProvider::class,
    App\Modules\Payments\Providers\PaymentsServiceProvider::class,
];
