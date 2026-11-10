<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The part of the year in course a person is owed when the relationship ends, in days only (LFT art. 79; criterio C-07):
 * the days that year would give × the days elapsed / 365, never more than the year gives. No amount of money.
 */
final class ProportionalDays
{
    private const int YEAR = 365;

    /**
     * @param  CarbonImmutable  $periodStart  the last anniversary, or the start of the seniority when there is none yet
     */
    public static function for(CarbonImmutable $periodStart, CarbonImmutable $terminatedOn, int $daysOfYearInCourse): float
    {
        $elapsed = $periodStart->startOfDay()->diffInDays($terminatedOn->startOfDay());

        if ($elapsed < 0 || $daysOfYearInCourse < 0) {
            throw new InvalidArgumentException('The relationship cannot end before the period starts, nor a year give days below zero.');
        }

        return round($daysOfYearInCourse * min($elapsed, self::YEAR) / self::YEAR, 2);
    }
}
