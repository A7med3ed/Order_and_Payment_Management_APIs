<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'order_id' => $this->order_id, 'payment_id' => $this->payment_id,
            'chain_sequence' => $this->chain_sequence, 'type' => $this->type->value, 'direction' => $this->direction->value,
            'amount' => $this->amount, 'currency' => $this->currency, 'gateway' => $this->gateway,
            'transaction_id' => $this->transaction_id, 'idempotency_key' => $this->idempotency_key,
            'metadata' => (object) $this->metadata, 'previous_hash' => $this->previous_hash, 'entry_hash' => $this->entry_hash,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
