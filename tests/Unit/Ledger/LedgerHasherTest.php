<?php

declare(strict_types=1);

namespace Tests\Unit\Ledger;

use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerHasher;
use App\Modules\Ledger\Enums\{LedgerEntryType, LedgerDirection};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class LedgerHasherTest extends TestCase
{
    public function test_hashing_is_canonical_and_covers_financial_identity_fields(): void
    {
        $entry = new LedgerEntry();
        $entry->forceFill(['id' => '01J00000000000000000000000', 'order_id' => 1, 'user_id' => 2, 'payment_id' => null,
            'chain_sequence' => 1, 'type' => LedgerEntryType::PaymentInitiated, 'direction' => LedgerDirection::Memo,
            'amount' => '1.00', 'currency' => 'USD', 'created_at' => CarbonImmutable::parse('2026-10-21 01:00:00.123456', 'UTC'),
            'previous_hash' => null, 'metadata' => ['b' => 2, 'a' => 1]]);
        $hasher = new LedgerHasher();
        $first = $hasher->hash($entry);
        $entry->metadata = ['a' => 1, 'b' => 2];
        $this->assertSame($first, $hasher->hash($entry));
        $entry->user_id = 3;
        $this->assertNotSame($first, $hasher->hash($entry));
    }
}
