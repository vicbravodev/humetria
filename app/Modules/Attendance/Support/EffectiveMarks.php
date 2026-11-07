<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The marks that count (criterio 5.4 and the design's Correcciones): a correction replaces its original, and one with no
 * time voids it. A day belongs to the local date of its start, even when it ends after midnight.
 */
final class EffectiveMarks
{
    /**
     * The person's marks no correction replaced.
     *
     * @return Builder<AttendanceMark>
     */
    public static function of(string $employeeId): Builder
    {
        return AttendanceMark::query()
            ->where('employee_id', $employeeId)
            ->whereNotNull('marked_at')
            ->whereNotExists(fn (QueryBuilder $query) => $query->from('attendance_marks', 'corrections')->whereColumn('corrections.corrects_mark_id', 'attendance_marks.id'));
    }

    /**
     * The marks of the days that start in the week, in order of time.
     *
     * @return list<AttendanceMark>
     */
    public static function forWeek(string $employeeId, WeekPeriod $week): array
    {
        return array_merge([], ...array_values(self::byDay($employeeId, $week)));
    }

    /**
     * The same marks by the local date of their day ('Y-m-d').
     *
     * @return array<string, list<AttendanceMark>>
     */
    public static function byDay(string $employeeId, WeekPeriod $week): array
    {
        return self::byDayOf([$employeeId], $week)[$employeeId];
    }

    /**
     * The days of several people at once, in one query, each person read from their own window: from their last start
     * before the week (its marks in the week are that day's, not the week's) to their first start after it (whatever comes
     * before is the end of the week's last day, however late).
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array<string, list<AttendanceMark>>> by employee id, every one asked for
     */
    public static function byDayOf(array $employeeIds, WeekPeriod $week): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $marks = AttendanceMark::query()
            ->among('attendance_marks.employee_id', $employeeIds)
            ->whereNotNull('marked_at')
            ->whereNotExists(fn (QueryBuilder $query) => $query->from('attendance_marks', 'corrections')->whereColumn('corrections.corrects_mark_id', 'attendance_marks.id'))
            // From the week, or from the person's last start before it, when there is one.
            ->where(fn (Builder $query) => $query->where('attendance_marks.marked_at', '>=', $week->startUtc())
                ->orWhere('attendance_marks.marked_at', '>=', self::starts()->selectRaw('max(starts.marked_at)')->where('starts.marked_at', '<', $week->startUtc())))
            // Up to the person's first start after the week, that one left out.
            ->whereNotExists(self::starts()->where('starts.marked_at', '>=', $week->nextStartUtc())->whereColumn('starts.marked_at', '<=', 'attendance_marks.marked_at'))
            ->orderBy('employee_id')
            ->orderBy('marked_at')
            ->get()
            ->groupBy('employee_id');

        $days = [];
        foreach ($employeeIds as $employeeId) {
            $days[$employeeId] = self::group(array_values($marks->get($employeeId)?->all() ?? []), $week);
        }

        return $days;
    }

    /** The effective starts of the outer query's person. */
    private static function starts(): QueryBuilder
    {
        return DB::table('attendance_marks', 'starts')
            ->whereColumn('starts.employee_id', 'attendance_marks.employee_id')
            ->where('starts.type', MarkType::Start->value)
            ->whereNotNull('starts.marked_at')
            ->whereNotExists(fn (QueryBuilder $query) => $query->from('attendance_marks', 'start_corrections')->whereColumn('start_corrections.corrects_mark_id', 'starts.id'));
    }

    /**
     * @param  list<AttendanceMark>  $marks  one person's, in order of time
     * @return array<string, list<AttendanceMark>>
     */
    private static function group(array $marks, WeekPeriod $week): array
    {
        $days = [];
        $openDay = null;
        foreach ($marks as $mark) {
            // of() leaves the voids out; one here is a query that forgot it.
            $at = $mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time.");
            $day = $mark->type === MarkType::Start || $openDay === null ? $week->localDate($at) : $openDay;
            $openDay = $mark->type === MarkType::End ? null : $day;

            if ($day->between($week->start(), $week->end())) {
                $days[$day->toDateString()][] = $mark;
            }
        }

        return $days;
    }
}
