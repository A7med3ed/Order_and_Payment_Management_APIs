<?php

declare(strict_types=1);

namespace App\Modules\Shared\Idempotency\Console;

use App\Modules\Shared\Idempotency\Services\IdempotencyService;
use Illuminate\Console\Command;

final class PruneIdempotencyCommand extends Command
{
    protected $signature = 'idempotency:prune';
    protected $description = 'Remove expired idempotency reservations and responses';
    public function handle(IdempotencyService $keys): int
    {
        $this->info('Pruned '.$keys->prune().' expired idempotency keys.');
        return self::SUCCESS;
    }
}
