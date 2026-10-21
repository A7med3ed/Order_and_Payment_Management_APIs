<?php

declare(strict_types=1);

namespace App\Modules\Auth\Providers;

use App\Modules\Shared\Providers\ModuleServiceProvider;

final class AuthServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

}
