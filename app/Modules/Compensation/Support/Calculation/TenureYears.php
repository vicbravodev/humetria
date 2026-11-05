<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use Carbon\CarbonImmutable;

/**
 * Whole years of service on an exact day: the year is completed on the day of the anniversary, not the day before or
 * after, because that day is when the vacation days and the vacation premium step up (LFT art. 76).
 *
 * Someone hired on Feb 29 completes their years on Feb 29 in a leap year and on Feb 28 in any other. The LFT does not
 * say which day; Feb 28 is the earlier one, so it is the one that favours the worker (LFT art. 18). The time of day is
 * ignored. Someone hired on the day itself, or later, has 0 years.
 */
final class TenureYears
{
    public static function at(CarbonImmutable $hiredOn, CarbonImmutable $on): int
    {
        $years = $on->year - $hiredOn->year;

        // The anniversary of this year, with Feb 29 kept in February (Feb 28 in a year without it), compared as dates.
        if (self::after($hiredOn->month, self::clamped($hiredOn, $on->year, $hiredOn->month), $on->month, $on->day)) {
            $years--;
        }

        return max($years, 0);
    }

    /** Whole months of service on an exact day; the same clamping of the day of the month as the anniversary. */
    public static function monthsAt(CarbonImmutable $hiredOn, CarbonImmutable $on): int
    {
        $months = ($on->year - $hiredOn->year) * 12 + $on->month - $hiredOn->month;

        if (self::clamped($hiredOn, $on->year, $on->month) > $on->day) {
            $months--;
        }

        return max($months, 0);
    }

    /** The day of hire in that month of that year, or the month's last day when it has fewer days. */
    private static function clamped(CarbonImmutable $hiredOn, int $year, int $month): int
    {
        $leap = $year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0);
        $days = [31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][$month - 1];

        return min($hiredOn->day, $days);
    }

    private static function after(int $month, int $day, int $otherMonth, int $otherDay): bool
    {
        return $month > $otherMonth || ($month === $otherMonth && $day > $otherDay);
    }
}
