<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => $target === self::Cancelled,
            self::Cancelled => false,
        };
    }
}
