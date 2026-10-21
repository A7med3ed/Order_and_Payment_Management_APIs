<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Shared\Support\CanonicalJson;

final class LedgerHasher
{
    public function hash(LedgerEntry $entry): string
    {
        return hash('sha256', CanonicalJson::encode([
            'id' => $entry->id, 'order_id' => $entry->order_id, 'payment_id' => $entry->payment_id,
            'user_id' => $entry->user_id, 'chain_sequence' => $entry->chain_sequence,
            'type' => $entry->type->value, 'direction' => $entry->direction->value,
            'amount' => $entry->amount, 'currency' => $entry->currency, 'gateway' => $entry->gateway,
            'transaction_id' => $entry->transaction_id, 'idempotency_key' => $entry->idempotency_key,
            'previous_hash' => $entry->previous_hash,
            'created_at' => $entry->created_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'metadata' => $entry->metadata,
        ]));
    }
}
