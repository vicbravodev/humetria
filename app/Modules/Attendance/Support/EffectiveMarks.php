<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
        // ponytail: a day is looked for up to 24 h beyond the week's edges; a longer day would be cut there.
        $marks = self::of($employeeId)
            ->whereBetween('marked_at', [$week->start()->subDay(), $week->end()->addDay()])
            ->orderBy('marked_at')
            ->get();

        $days = [];
        $openDay = null;
        foreach ($marks as $mark) {
            $at = $mark->marked_at ?? $week->start();
            $day = $mark->type === MarkType::Start || $openDay === null ? $week->localDate($at) : $openDay;
            $openDay = $mark->type === MarkType::End ? null : $day;

            if ($day->between($week->start(), $week->end())) {
                $days[$day->toDateString()][] = $mark;
            }
        }

        return $days;
    }
}
