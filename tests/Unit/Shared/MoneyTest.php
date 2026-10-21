<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\Money;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('decimalValues')]
    public function test_decimal_strings_are_normalized_exactly(string $input, string $expected): void
    {
        $this->assertSame($expected, Money::format($input));
    }

    public static function decimalValues(): array
    {
        return [
            ['0', '0.00'], ['19.9', '19.90'], ['00019.90', '19.90'],
            ['-0.00', '0.00'], ['9999999999.99', '9999999999.99'],
        ];
    }

    public function test_calculations_preserve_decimal_precision(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('59.97', Money::mul('19.99', 3));
        $this->assertSame('9999999999.99', Money::mul('3333333333.33', 3));
        $this->assertSame('-1.00', Money::add('1.00', '-2.00'));
        $this->assertSame(0, Money::compare('01.2', '1.20'));
        $this->assertSame(-1, Money::compare('-0.01', '0'));
        $this->assertSame(1, Money::compare('0.01', '0'));
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_precision_or_format_is_rejected(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::format($amount);
    }

    public static function invalidValues(): array
    {
        return [['1.001'], ['1e2'], [''], ['1,000.00'], ['NaN'], [' 1.00']];
    }

    public function test_a_total_exceeding_database_capacity_is_rejected(): void
    {
        $this->expectException(OverflowException::class);
        Money::add('9999999999.99', '0.01');
    }
}
