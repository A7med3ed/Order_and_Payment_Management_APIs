<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Exceptions;

use LogicException;

final class ImmutableLedgerException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Ledger entries are immutable; append a new entry instead.');
    }
}
