<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\DayHours;
use App\Modules\Attendance\Data\WeekSummary;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Models\AttendanceSchedule;

/**
 * The weeks of many people for RH's panel: each split as criterio 2.9 says, read in two queries whatever their number
 * (their marks and their schedules), and whether each is near the year's limits.
 */
final class TeamWeek
{
    /** A week is at risk from this share of the year's weekly overtime cap (art. 66), before it is exceeded. */
    private const int RISK_PERCENT = 80;

    /**
     * @param  list<string>  $employeeIds
     * @return array<string, WeekSummary> by employee id, in the order asked
     */
    public static function summaries(array $employeeIds, WeekPeriod $week): array
    {
        return self::withDays($employeeIds, $week)[0];
    }

    /**
     * The same summaries and, for each person, the minutes worked each day of the week (seven, Monday to Sunday: what the
     * week ribbon draws).
     *
     * @param  list<string>  $employeeIds
     * @return array{0: array<string, WeekSummary>, 1: array<string, list<int>>} both by employee id, in the order asked
     */
    public static function withDays(array $employeeIds, WeekPeriod $week): array
    {
        if ($employeeIds === []) {
            return [[], []];
        }

        // ponytail: hydrates every scoped person's marks of the week (one query, each inside its index-bounded window): fine
        // for hundreds of people; past a few thousand, aggregate the minutes in SQL or chunk the ids.
        $byPerson = EffectiveMarks::byDayOf($employeeIds, $week);
        $schedules = AttendanceSchedule::query()->among('employee_id', $employeeIds)->get()->keyBy('employee_id');
        $summaries = [];
        $worked = [];

        foreach ($employeeIds as $employeeId) {
            $schedule = $schedules->get($employeeId);
            $days = array_map(
                fn (int $offset) => WorkedDay::hours($date = $week->start()->addDays($offset), $byPerson[$employeeId][$date->toDateString()] ?? [], $schedule),
                range(0, 6),
            );
            $summaries[$employeeId] = HoursCalculator::week($days, $schedule->shift_type ?? ShiftType::Day, $week->start()->year);
            $worked[$employeeId] = array_map(fn (DayHours $day): int => $day->workedMinutes, $days);
        }

        return [$summaries, $worked];
    }

    /**
     * A week that broke a limit of arts. 66 or 68, or whose overtime reached 80 % of the year's weekly cap: RH still has
     * days to spread the work before it goes over.
     */
    public static function atRisk(WeekSummary $summary, int $year): bool
    {
        return $summary->flags !== [] || $summary->overtimeMinutes * 100 >= LegalLimits::weeklyOvertimeCapMinutes($year) * self::RISK_PERCENT;
    }
}
