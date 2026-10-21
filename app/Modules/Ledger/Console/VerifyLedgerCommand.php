<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Console;

use App\Modules\Ledger\Services\LedgerVerifier;
use Illuminate\Console\Command;

final class VerifyLedgerCommand extends Command
{
    protected $signature = 'ledger:verify {--order= : Verify one order chain}';
    protected $description = 'Verify ledger sequences, hashes, and chain checkpoints';

    public function handle(LedgerVerifier $verifier): int
    {
        $orderId = $this->option('order');
        if ($orderId !== null && (! ctype_digit((string) $orderId) || (int) $orderId < 1)) {
            $this->error('The order option must be a positive integer.');
            return self::FAILURE;
        }
        $result = $verifier->verify($orderId !== null ? (int) $orderId : null);
        if (! $result->valid) {
            $this->error($result->error);
            return self::FAILURE;
        }
        $this->info('Ledger verified: '.$result->checked.' entries.');
        return self::SUCCESS;
    }
}
