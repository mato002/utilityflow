<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function cents(string|int $amount): int
    {
        $value = (string) $amount;
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Money amounts must be non-negative decimal values with up to two places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function decimalQuantity(int $units): string
    {
        return intdiv($units, 10_000).'.'.str_pad((string) ($units % 10_000), 4, '0', STR_PAD_LEFT);
    }

    public static function quantityRateCents(string|int $quantity, string|int $rate): int
    {
        $quantityUnits = self::scaledFour($quantity);
        $rateUnits = self::scaledFour($rate);
        $product = $quantityUnits * $rateUnits;

        return intdiv($product + 500_000, 1_000_000);
    }

    private static function scaledFour(string|int $number): int
    {
        $value = (string) $number;
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException('Values must be non-negative decimals with up to four places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 10_000) + (int) str_pad($fraction, 4, '0');
    }
}
