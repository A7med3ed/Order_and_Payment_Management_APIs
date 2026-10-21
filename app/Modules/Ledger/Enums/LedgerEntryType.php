<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Enums;

enum LedgerEntryType: string
{
    case PaymentInitiated = 'payment_initiated';
    case PaymentSucceeded = 'payment_succeeded';
    case PaymentFailed = 'payment_failed';
    case PaymentReversed = 'payment_reversed';
    public function direction(): LedgerDirection
    {
        return match ($this) {
            self::PaymentSucceeded => LedgerDirection::Credit,
            self::PaymentReversed => LedgerDirection::Debit,
            default => LedgerDirection::Memo,
        };
    }
}
