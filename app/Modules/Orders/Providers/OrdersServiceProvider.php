<?php

declare(strict_types=1);

namespace App\Modules\Orders\Providers;

use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Orders\Contracts\{OrderReaderInterface, OrderPaymentGuardInterface};
use App\Modules\Orders\Services\{OrderReader, NullOrderPaymentGuard};
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;

final class OrdersServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    public function register(): void
    {
        parent::register();
        $this->app->bind(OrderReaderInterface::class, OrderReader::class);
        $this->app->bind(OrderPaymentGuardInterface::class, NullOrderPaymentGuard::class);
    }

    public function boot(): void
    {
        parent::boot();
        Gate::policy(Order::class, OrderPolicy::class);
    }
}
