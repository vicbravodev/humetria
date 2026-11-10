<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

/** Cents written as pesos for people to read: "$4,900", "$149.50". Only for text; every sum is done in cents. */
final class Money
{
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);
        $pesos = number_format(intdiv($absolute, 100));
        $rest = $absolute % 100;

        return $sign.'$'.$pesos.($rest === 0 ? '' : '.'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT));
    }

    /**
     * Pesos typed as "8000" or "149.50" as cents; null for anything else (a comma, a sign, a third decimal, an amount
     * that does not fit). Done on the digits: a float would turn 149.05 into 14904.999...
     */
    public static function parse(string $pesos): ?int
    {
        if (preg_match('/\A(\d{1,12})(?:\.(\d{1,2}))?\z/', trim($pesos), $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    /** Cents as a form field holds them: "8000" or "8149.50", the inverse of parse(). */
    public static function input(int $cents): string
    {
        $rest = $cents % 100;

        return intdiv($cents, 100).($rest === 0 ? '' : '.'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT));
    }
}
