<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Providers;

use App\Modules\Shared\Providers\ModuleServiceProvider;
use App\Modules\Ledger\Contracts\{LedgerRecorderInterface, LedgerReaderInterface};
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Ledger\Console\VerifyLedgerCommand;

final class LedgerServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    public function register(): void
    {
        parent::register();
        $this->app->bind(LedgerRecorderInterface::class, LedgerService::class);
        $this->app->bind(LedgerReaderInterface::class, LedgerService::class);
    }

    public function boot(): void
    {
        parent::boot();
        $this->commands([VerifyLedgerCommand::class]);
    }
}
