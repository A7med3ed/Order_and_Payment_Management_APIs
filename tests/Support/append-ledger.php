<?php

declare(strict_types=1);
require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script, $orderId, $userId, $barrier, $worker] = $argv;
file_put_contents($barrier.'.ready.'.$worker, 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Ledger test barrier timed out.');
    }
    usleep(10000);
    clearstatcache(true, $barrier);
}
$app->make(\App\Modules\Ledger\Contracts\LedgerRecorderInterface::class)->record(
    new \App\Modules\Ledger\DTOs\LedgerEntryData(
        (int) $orderId,
        (int) $userId,
        null,
        \App\Modules\Ledger\Enums\LedgerEntryType::PaymentInitiated,
        \App\Modules\Ledger\Enums\LedgerDirection::Memo,
        '1.00',
        'USD'
    )
);
