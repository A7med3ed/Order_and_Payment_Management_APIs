<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Services;

use App\Modules\Ledger\DTOs\LedgerVerificationData;
use App\Modules\Ledger\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class LedgerVerifier
{
    public function __construct(private LedgerHasher $hasher)
    {
    }

    public function verify(?int $orderId = null): LedgerVerificationData
    {
        return DB::transaction(function () use ($orderId): LedgerVerificationData {
            $checked = 0;
            $tails = [];
            foreach (LedgerEntry::query()->when($orderId !== null, fn ($query) => $query->where('order_id', $orderId))
                ->orderBy('order_id')->orderBy('chain_sequence')->cursor() as $entry) {
                [$sequence, $hash] = $tails[$entry->order_id] ?? [0, null];
                try {
                    $validHash = hash_equals($entry->entry_hash, $this->hasher->hash($entry));
                } catch (Throwable) {
                    $validHash = false;
                }
                if ($entry->chain_sequence !== $sequence + 1 || $entry->previous_hash !== $hash || ! $validHash) {
                    return new LedgerVerificationData(false, $checked, 'Invalid chain at entry '.$entry->id.'.');
                }
                $tails[$entry->order_id] = [$entry->chain_sequence, $entry->entry_hash];
                $checked++;
            }
            foreach (DB::table('ledger_chain_heads')->when($orderId !== null, fn ($query) => $query->where('order_id', $orderId))->get() as $head) {
                if (($tails[$head->order_id] ?? [0, null]) !== [(int) $head->last_sequence, $head->last_hash]) {
                    return new LedgerVerificationData(false, $checked, 'Chain checkpoint mismatch for order '.$head->order_id.'.');
                }
                unset($tails[$head->order_id]);
            }
            return $tails === [] ? new LedgerVerificationData(true, $checked)
                : new LedgerVerificationData(false, $checked, 'A chain checkpoint is missing.');
        });
    }
}
