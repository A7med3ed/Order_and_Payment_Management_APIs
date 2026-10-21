<?php

declare(strict_types=1);

namespace App\Modules\Payments\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';

    public function canTransitionTo(self $target): bool
    {
        return $this === self::Pending && in_array($target, [self::Successful, self::Failed], true);
    }
}
