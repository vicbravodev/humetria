<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceMark;

/**
 * The version of a person's week: the sha256 of the sorted ids of its effective marks (EffectiveMarks::forWeek). A mark
 * added, corrected or voided gives another digest, since every correction is a new mark. The person's answer carries the
 * digest of the week they read, so an answer is about that week and not one RH changed meanwhile. Ids only: no times.
 */
final class WeekDigest
{
    public static function of(string $employeeId, WeekPeriod $week): string
    {
        return self::ofMarks(EffectiveMarks::forWeek($employeeId, $week));
    }

    /**
     * @param  list<AttendanceMark>  $marks
     */
    public static function ofMarks(array $marks): string
    {
        $ids = array_map(fn (AttendanceMark $mark): string => $mark->id, $marks);
        sort($ids);

        return hash('sha256', implode(',', $ids));
    }
}
