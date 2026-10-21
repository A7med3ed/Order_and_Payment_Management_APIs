<?php

declare(strict_types=1);

namespace App\Modules\Payments\Providers;

use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Orders\Contracts\OrderPaymentGuardInterface;
use App\Modules\Payments\Services\OrderPaymentGuard;
use App\Modules\Payments\Gateways\PaymentGatewayManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class PaymentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->bind(\App\Modules\Payments\Contracts\PaymentReaderInterface::class, \App\Modules\Payments\Services\PaymentReader::class);
        $this->app->bind(OrderPaymentGuardInterface::class, OrderPaymentGuard::class);
    }

    public function boot(): void
    {
        parent::boot();
        // Payment-specific keys belong to this module; Shared stays generic.
        RateLimiter::for('payments', fn (Request $request): array => [
            Limit::perMinute(max(1, (int) config('rate_limits.payments_per_minute')))
                ->by('user:'.$request->user()->getAuthIdentifier()),
            Limit::perMinute(max(1, (int) config('rate_limits.payments_per_order_per_minute')))
                ->by('order:'.$request->user()->getAuthIdentifier().':'.$request->route('order')),
        ]);
    }

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
