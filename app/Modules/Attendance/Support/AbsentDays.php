<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Leave\Contracts\Absences;

/**
 * The days of a week each person was away with approved vacations or a leave, so a day with no marks is not read as one
 * they missed. Only vacations or leave: the type of a leave never reaches the registro. One query for any number of people.
 */
final class AbsentDays
{
    /**
     * @param  list<string>  $employeeIds
     * @return array<string, array<int, string>> by employee id: `vacation` or `leave` for each day away, by its place in the week (0 Monday to 6 Sunday)
     */
    public static function of(Absences $absences, WeekPeriod $week, array $employeeIds): array
    {
        $days = [];

        foreach ($absences->between($week->start(), $week->end(), $employeeIds) as $absence) {
            foreach (range(0, 6) as $offset) {
                $date = $week->start()->addDays($offset)->toDateString();

                if ($absence->from->toDateString() <= $date && $date <= $absence->to->toDateString()) {
                    $days[$absence->employeeId][$offset] = $absence->kind;
                }
            }
        }

        return $days;
    }
}
