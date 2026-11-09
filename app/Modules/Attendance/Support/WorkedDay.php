<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\DayHours;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * One day of a person from its marks (EffectiveMarks::byDay): the minutes worked net of the breaks, only between marks
 * (a stretch still open counts nothing, nobody closes it for the person), against the minutes their schedule set for its
 * weekday. A weekday the schedule leaves at 0 is a rest day. A person with no schedule yet is held to the legal daily
 * maximum of a day shift every day (art. 61), with no rest day: only the law's limits split their hours.
 */
final class WorkedDay
{
    /**
     * @param  list<AttendanceMark>  $marks  the day's marks, in order of time
     */
    public static function hours(CarbonImmutable $date, array $marks, ?AttendanceSchedule $schedule): DayHours
    {
        $scheduled = $schedule === null ? LegalLimits::dailyOrdinaryMinutes(ShiftType::Day) : $schedule->minutes_by_weekday[$date->isoWeekday()] ?? 0;

        return new DayHours($date, self::minutes($marks), $scheduled, $schedule !== null && $scheduled === 0);
    }

    /**
     * A day whose last mark is not its end: flagged for RH and the person, never closed by itself.
     *
     * @param  list<AttendanceMark>  $marks
     */
    public static function missingEnd(array $marks): bool
    {
        return $marks !== [] && end($marks)->type !== MarkType::End;
    }

    /**
     * The minutes of the day's breaks: from each break's start to the next mark (its end, or the day's end when the person
     * closed the day during it). What minutes() leaves out between marks.
     *
     * @param  list<AttendanceMark>  $marks  the day's marks, in order of time
     */
    public static function breakMinutes(array $marks): int
    {
        $seconds = 0;
        $since = null;

        foreach ($marks as $mark) {
            $at = $mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time.");
            $seconds += $since === null ? 0 : $at->getTimestamp() - $since->getTimestamp();
            $since = $mark->type === MarkType::BreakStart ? $at : null;
        }

        return intdiv($seconds, 60);
    }

    /**
     * @param  list<AttendanceMark>  $marks
     */
    private static function minutes(array $marks): int
    {
        $seconds = 0;
        $since = null;

        foreach ($marks as $mark) {
            $at = $mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time.");
            $seconds += $since === null ? 0 : $at->getTimestamp() - $since->getTimestamp();
            $since = in_array($mark->type, [MarkType::Start, MarkType::BreakEnd], true) ? $at : null;
        }

        return intdiv($seconds, 60);
    }
}
