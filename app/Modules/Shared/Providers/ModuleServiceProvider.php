<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract protected function modulePath(): string;

    public function register(): void
    {
        foreach (glob($this->modulePath().'/Config/*.php') ?: [] as $file) {
            $this->mergeConfigFrom($file, basename($file, '.php'));
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath().'/Database/Migrations');

        $routes = $this->modulePath().'/Routes/api.php';
        if (is_file($routes) && ! $this->app->routesAreCached()) {
            Route::prefix('api')->middleware('api')->group($routes);
        }
    }
}
