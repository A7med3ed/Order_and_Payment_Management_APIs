<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\RateLimiting\RateLimiters;

final class SharedServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    public function boot(): void
    {
        parent::boot();
        $this->app->make(RateLimiters::class)->register();
    }
}
