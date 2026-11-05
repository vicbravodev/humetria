<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

/** A percent between what a person types ("5.75") and what is stored (basis points: 575). It never goes through a float. */
final class PercentBasisPoints
{
    /** Up to three digits before the mark (100 % and a sign), up to two decimals, either mark. */
    private const string PATTERN = '/\A(-?)(\d{1,3})(?:[.,](\d{1,2}))?\z/';

    public static function parse(string $text): ?int
    {
        if (preg_match(self::PATTERN, trim($text), $parts) !== 1) {
            return null;
        }

        $basisPoints = (int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0');

        return $parts[1] === '-' ? -$basisPoints : $basisPoints;
    }

    /** 575 is "5.75" and 500 is "5": only the decimals it needs. */
    public static function format(int $basisPoints): string
    {
        $absolute = abs($basisPoints);
        $fraction = rtrim(sprintf('%02d', $absolute % 100), '0');

        return ($basisPoints < 0 ? '-' : '').intdiv($absolute, 100).($fraction === '' ? '' : ".{$fraction}");
    }
}
