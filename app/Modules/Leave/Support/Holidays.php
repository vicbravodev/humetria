<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveHoliday;
use Carbon\CarbonImmutable;

/**
 * The days nobody of the current company works (criterio C-04): those of LFT art. 74 and the ones the company added.
 */
final class Holidays
{
    /**
     * @return list<string> Y-m-d, in order and once each, both ends included
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$first, $last] = [$from->toDateString(), $to->toDateString()];
        $days = LeaveHoliday::query()->whereBetween('date', [$first, $last])->get(['date'])->map(fn (LeaveHoliday $holiday): string => $holiday->date->toDateString())->all();

        for ($year = $from->year; $year <= $to->year; $year++) {
            foreach (StatutoryHolidays::for($year) as $day) {
                if ($first <= $day && $day <= $last) {
                    $days[] = $day;
                }
            }
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }
}
