<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Enums;

enum LedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Memo = 'memo';
}
