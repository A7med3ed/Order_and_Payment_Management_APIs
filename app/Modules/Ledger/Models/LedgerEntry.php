<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Models;

use App\Modules\Ledger\Enums\{LedgerEntryType, LedgerDirection};
use App\Modules\Ledger\Exceptions\ImmutableLedgerException;
use Illuminate\Database\Eloquent\Model;

final class LedgerEntry extends Model
{
    public const UPDATED_AT = null;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['*'];
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['type' => LedgerEntryType::class, 'direction' => LedgerDirection::class,
            'amount' => 'decimal:2', 'metadata' => 'array', 'created_at' => 'immutable_datetime',
            'order_id' => 'integer', 'payment_id' => 'integer', 'user_id' => 'integer', 'chain_sequence' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $entry): void {
            if ($entry->exists) {
                throw new ImmutableLedgerException();
            }
        });
        static::updating(static function (): never {
            throw new ImmutableLedgerException();
        });
        static::deleting(static function (): never {
            throw new ImmutableLedgerException();
        });
    }
}
