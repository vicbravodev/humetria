<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
        // From the last start before the week (its marks in the week are that day's, not the week's) to the first start
        // after it (whatever comes before is the end of the week's last day, however late).
        $before = self::of($employeeId)->where('type', MarkType::Start)->where('marked_at', '<', $week->startUtc())->latest('marked_at')->first(['marked_at'])?->marked_at;
        $after = self::of($employeeId)->where('type', MarkType::Start)->where('marked_at', '>=', $week->nextStartUtc())->oldest('marked_at')->first(['marked_at'])?->marked_at;
        $marks = self::of($employeeId)
            ->where('marked_at', '>=', $before?->utc() ?? $week->startUtc())
            ->when($after, fn (Builder $query, CarbonImmutable $at) => $query->where('marked_at', '<', $at->utc()))
            ->orderBy('marked_at')
            ->get();

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
