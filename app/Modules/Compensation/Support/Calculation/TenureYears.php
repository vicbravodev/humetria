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
        $hired = $hiredOn->startOfDay();
        $years = $on->year - $hired->year;

        // addYearsNoOverflow keeps Feb 29 from rolling into March 1 in a year without it.
        if ($hired->addYearsNoOverflow($years)->isAfter($on->startOfDay())) {
            $years--;
        }

        return max($years, 0);
    }
}
