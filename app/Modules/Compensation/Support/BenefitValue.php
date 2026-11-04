<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Support\Calculation\Money;

/**
 * A benefit's value between what a person types ("12,5") and what is stored: a whole number of hundredths (15 days are
 * 1500, $500.00 are 50000 cents; see BenefitKind). It never goes through a float.
 */
final class BenefitValue
{
    /** Nine digits before the mark keep the value far below what a bigint holds. */
    private const string PATTERN = '/\A(\d{1,9})(?:[.,](\d{1,2}))?\z/';

    /** The hundredths of what was typed, with up to two decimals and either mark; null when it is not a number like that. */
    public static function parse(string $text): ?int
    {
        if (preg_match(self::PATTERN, trim($text), $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    /** The number as a person writes it, with only the decimals it needs: 1250 is "12.5" and 1500 is "15". */
    public static function decimal(int $hundredths): string
    {
        $fraction = rtrim(sprintf('%02d', $hundredths % 100), '0');

        return intdiv($hundredths, 100).($fraction === '' ? '' : ".{$fraction}");
    }

    /** The value with its unit: "15 días", "25 %", "$500.00", "1.5 UMA". */
    public static function format(BenefitKind $kind, int $hundredths): string
    {
        $number = self::decimal($hundredths);

        return match ($kind) {
            BenefitKind::SalaryDays => $number === '1' ? '1 día' : "{$number} días",
            BenefitKind::SalaryPercent => "{$number} %",
            BenefitKind::FixedAmount => Money::format($hundredths),
            BenefitKind::UmaMultiple => "{$number} UMA",
        };
    }
}
