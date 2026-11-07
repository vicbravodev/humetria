<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\DayHours;
use App\Modules\Attendance\Data\WeekSummary;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Enums\ShiftType;

/**
 * Splits a week's minutes as criterio 2.9 says: a day's overtime is what it worked over its schedule; a worked rest day
 * goes apart; the ordinary minutes past the year's weekly maximum become overtime of the days that cross it; overtime
 * is double up to the year's cap and triple beyond it.
 */
final class HoursCalculator
{
    /**
     * A schedule may put more than the daily maximum of art. 61 on a day by agreement (criterio 2.6): only the weekly
     * maximum turns ordinary minutes into overtime.
     *
     * @param  list<DayHours>  $days  one week, in any order
     * @param  ShiftType  $shift  accepted for forward compatibility; it does not change the result today (criterio 2.9)
     */
    public static function week(array $days, ShiftType $shift, int $year): WeekSummary
    {
        usort($days, fn (DayHours $a, DayHours $b): int => $a->date <=> $b->date);

        $weeklyOrdinaryMax = LegalLimits::weeklyOrdinaryMinutes($year);
        $ordinary = 0;
        $restDay = 0;
        $overtimeByDay = [];
        $dayOver12Hours = false;

        foreach ($days as $day) {
            $dayOver12Hours = $dayOver12Hours || $day->workedMinutes > LegalLimits::DAY_TOTAL_MAX;

            if ($day->restDay) {
                $restDay += $day->workedMinutes;

                continue;
            }

            $dayOrdinary = min($day->workedMinutes, $day->scheduledMinutes);
            $pastWeeklyMax = max(0, $ordinary + $dayOrdinary - $weeklyOrdinaryMax);
            $ordinary += $dayOrdinary - $pastWeeklyMax;
            $overtime = $day->workedMinutes - $dayOrdinary + $pastWeeklyMax;

            if ($overtime > 0) {
                $date = $day->date->toDateString();
                $overtimeByDay[$date] = ($overtimeByDay[$date] ?? 0) + $overtime;
            }
        }

        $overtime = array_sum($overtimeByDay);
        $double = min($overtime, LegalLimits::weeklyOvertimeCapMinutes($year));
        $triple = $overtime - $double;

        $flags = array_keys(array_filter([
            OverLimit::DayOver12Hours->value => $dayOver12Hours,
            OverLimit::DailyOvertimeOver4->value => $overtimeByDay !== [] && max($overtimeByDay) > LegalLimits::DAILY_OVERTIME_MAX,
            OverLimit::OvertimeDaysOver4->value => count($overtimeByDay) > LegalLimits::OVERTIME_DAYS_MAX,
            OverLimit::TripleCapExceeded->value => $triple > LegalLimits::TRIPLE_WEEKLY_MAX,
        ]));

        return new WeekSummary(
            ordinaryMinutes: $ordinary,
            overtimeMinutes: $overtime,
            doubleMinutes: $double,
            tripleMinutes: $triple,
            restDayMinutes: $restDay,
            flags: array_map(OverLimit::from(...), $flags),
            overtimeByDay: $overtimeByDay,
        );
    }
}
