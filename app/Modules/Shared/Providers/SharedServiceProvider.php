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
        $this->loadMigrationsFrom(dirname(__DIR__).'/Idempotency/Database/Migrations');
        $this->commands([\App\Modules\Shared\Idempotency\Console\PruneIdempotencyCommand::class]);
        \Illuminate\Support\Facades\Schedule::command('idempotency:prune')->hourly()->withoutOverlapping();
    }
}
