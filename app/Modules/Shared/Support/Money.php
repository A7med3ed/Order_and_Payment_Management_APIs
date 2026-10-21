<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use InvalidArgumentException;
use OverflowException;

final class Money
{
    public static function format(string $amount): string
    {
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $amount) !== 1) {
            throw new InvalidArgumentException('Money must be a decimal string with at most two decimal places.');
        }

        if (bccomp($amount, '9999999999.99', 2) > 0 || bccomp($amount, '-9999999999.99', 2) < 0) {
            throw new OverflowException('Money exceeds DECIMAL(12,2) capacity.');
        }

        return bcadd($amount, '0', 2);
    }

    public static function add(string $left, string $right): string
    {
        return self::format(bcadd(self::format($left), self::format($right), 2));
    }

    public static function mul(string $amount, int $quantity): string
    {
        return self::format(bcmul(self::format($amount), (string) $quantity, 2));
    }

    public static function compare(string $left, string $right): int
    {
        return bccomp(self::format($left), self::format($right), 2);
    }
}
