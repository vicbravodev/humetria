<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\WorkWeek;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Working days as vacations count them (criterio C-04): the days of the policy's week that are not a holiday, whether of
 * LFT art. 74 or of the company.
 */
final class WorkingDays
{
    /**
     * The working days from one day to another, both included.
     *
     * @param  list<string>  $holidays  Y-m-d
     */
    public static function count(CarbonImmutable $from, CarbonImmutable $to, WorkWeek $week, array $holidays): int
    {
        $day = $from->startOfDay();
        $last = $to->startOfDay();

        if ($last < $day) {
            throw new InvalidArgumentException('The range ends before it starts.');
        }

        $count = 0;

        // ponytail: walks day by day; a request spans at most a year.
        for (; $day <= $last; $day = $day->addDay()) {
            $count += self::works($day, $week, $holidays) ? 1 : 0;
        }

        return $count;
    }

    /**
     * The working day that comes `$days` working days after `$from`, not counting `$from`.
     *
     * @param  list<string>  $holidays  Y-m-d
     */
    public static function add(CarbonImmutable $from, int $days, WorkWeek $week, array $holidays): CarbonImmutable
    {
        if ($days < 0) {
            throw new InvalidArgumentException('Working days are only counted forward.');
        }

        $day = $from->startOfDay();

        while ($days > 0) {
            $day = $day->addDay();
            $days -= self::works($day, $week, $holidays) ? 1 : 0;
        }

        return $day;
    }

    /**
     * @param  list<string>  $holidays
     */
    private static function works(CarbonImmutable $day, WorkWeek $week, array $holidays): bool
    {
        return $week->includes($day) && ! in_array($day->toDateString(), $holidays, true);
    }
}
