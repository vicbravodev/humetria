<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

/** The work risk premium between what a person types ("7.58875" %) and what is stored (the rate times 10^7: 758875). */
final class PremiumRate
{
    /** The IMSS notifies the rate with up to five decimals of a percent; it never goes through a float. */
    private const string PATTERN = '/\A(\d{1,2})(?:[.,](\d{1,5}))?\z/';

    private const int PER_PERCENT = 100_000;

    /** The stored rate of what was typed, or null when it is not a percent like that. */
    public static function parse(string $text): ?int
    {
        if (preg_match(self::PATTERN, trim($text), $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * self::PER_PERCENT + (int) str_pad($parts[2] ?? '', 5, '0');
    }

    /** The percent as the person writes it, with only the decimals it needs: 758875 is "7.58875" and 500000 is "5". */
    public static function format(int $scaled): string
    {
        $fraction = rtrim(sprintf('%05d', $scaled % self::PER_PERCENT), '0');

        return intdiv($scaled, self::PER_PERCENT).($fraction === '' ? '' : ".{$fraction}");
    }
}
