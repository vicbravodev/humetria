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
 *
 * The lateness is informational (criterios 2.6): the minutes between the schedule's start time for the weekday and the day's
 * first start mark, once the company's tolerance (inclusive) has passed; 0 within it, null with no start time, no start
 * mark, a rest day or an approved absence. It never changes the minutes worked or scheduled.
 */
final class WorkedDay
{
    /**
     * @param  list<AttendanceMark>  $marks  the day's marks, in order of time
     */
    public static function hours(CarbonImmutable $date, array $marks, ?AttendanceSchedule $schedule, int $lateTolerance = 0, bool $away = false): DayHours
    {
        $scheduled = $schedule === null ? LegalLimits::dailyOrdinaryMinutes(ShiftType::Day) : $schedule->minutes_by_weekday[$date->isoWeekday()] ?? 0;
        $rest = $schedule !== null && $scheduled === 0;
        $late = $schedule === null || $rest || $away ? null : self::lateMinutes($date, $marks, $schedule->starts_by_weekday[$date->isoWeekday()] ?? null, $lateTolerance);

        return new DayHours($date, self::minutes($marks), $scheduled, $rest, $late);
    }

    /**
     * @param  list<AttendanceMark>  $marks
     */
    private static function lateMinutes(CarbonImmutable $date, array $marks, ?int $start, int $tolerance): ?int
    {
        $first = array_find($marks, fn (AttendanceMark $mark): bool => $mark->type === MarkType::Start);

        if ($start === null || $first === null) {
            return null;
        }

        $at = $first->marked_at ?? throw new LogicException("Mark [{$first->id}] has no time.");
        // Local midnight plus the minutes: the day's own start, even when the mark falls after midnight (a night shift).
        $due = CarbonImmutable::parse($date->toDateString(), config()->string('app.display_timezone'))->setTime(intdiv($start, 60), $start % 60);
        $minutes = intdiv($at->getTimestamp() - $due->getTimestamp(), 60);

        return $minutes > $tolerance ? $minutes : 0;
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
