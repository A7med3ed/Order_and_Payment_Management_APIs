<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Providers;

use App\Modules\Shared\Providers\ModuleServiceProvider;

final class LedgerServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
