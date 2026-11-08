<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
     * The days of several people at once, in two queries whatever their number, each person read from their own window:
     * from their last start before the week (its marks in the week are that day's, not the week's) to their first start
     * after it (whatever comes before is the end of the week's last day, however late). The windows come first, each bound
     * found by one step on the (tenant_id, employee_id, marked_at) index; then only the marks inside them are read.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array<string, list<AttendanceMark>>> by employee id, every one asked for
     */
    public static function byDayOf(array $employeeIds, WeekPeriod $week): array
    {
        return self::byDayBetween($employeeIds, $week->start(), $week->end());
    }

    /**
     * The same for any run of local dates, `$first` to `$last` both included (their dates only): a report's period, read
     * as byDayOf() reads a week, in two queries whatever its length or the number of people.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array<string, list<AttendanceMark>>> by employee id, every one asked for
     */
    public static function byDayBetween(array $employeeIds, CarbonImmutable $first, CarbonImmutable $last): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $timezone = config()->string('app.display_timezone');
        $start = CarbonImmutable::parse($first->toDateString(), $timezone);
        $next = CarbonImmutable::parse($last->toDateString(), $timezone)->addDay();
        $windows = self::windows($employeeIds, $start, $next);
        $marks = AttendanceMark::query()
            ->select('attendance_marks.*')
            ->joinSub(
                DB::query()->fromRaw('unnest(?::uuid[], ?::timestamptz[], ?::timestamptz[]) as windows(employee_id, since, until)', [
                    self::pgArray(array_keys($windows)),
                    self::pgArray(array_column($windows, 0)),
                    self::pgArray(array_column($windows, 1)),
                ]),
                'windows',
                fn (JoinClause $join) => $join->on('windows.employee_id', '=', 'attendance_marks.employee_id')
                    ->on('attendance_marks.marked_at', '>=', 'windows.since')
                    ->on('attendance_marks.marked_at', '<', 'windows.until'),
            )
            ->whereNotExists(fn (QueryBuilder $query) => $query->from('attendance_marks', 'corrections')
                ->whereColumn('corrections.tenant_id', 'attendance_marks.tenant_id')
                ->whereColumn('corrections.corrects_mark_id', 'attendance_marks.id'))
            ->orderBy('attendance_marks.employee_id')
            ->orderBy('attendance_marks.marked_at')
            ->get()
            ->groupBy('employee_id');

        $days = [];
        foreach ($employeeIds as $employeeId) {
            $days[$employeeId] = self::group(array_values($marks->get($employeeId)?->all() ?? []), $start->toDateString(), $last->toDateString(), $timezone);
        }

        return $days;
    }

    /**
     * Each person's window in UTC, by employee id: from their last effective start before the period (or the period's start)
     * to their first effective start after it (or 'infinity'). A start a correction replaced or voided does not count; the
     * correction does, as any start. Only this module's table is read: a person of another company has no start here and
     * gets the week's start to 'infinity', and the marks' own company bound gives them none.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array{string, string}>
     */
    private static function windows(array $employeeIds, CarbonImmutable $periodStart, CarbonImmutable $nextStart): array
    {
        $tenant = resolve(CurrentTenant::class)->id();
        $start = self::utc($periodStart);
        $next = self::utc($nextStart);
        $rows = DB::select(
            'select people.id,
                (select starts.marked_at from "attendance_marks" as starts where starts.tenant_id = ? and starts.employee_id = people.id
                    and starts.type = ? and starts.marked_at < ?
                    and not exists (select 1 from "attendance_marks" as corrections where corrections.tenant_id = ? and corrections.corrects_mark_id = starts.id)
                    order by starts.marked_at desc limit 1) as since,
                (select starts.marked_at from "attendance_marks" as starts where starts.tenant_id = ? and starts.employee_id = people.id
                    and starts.type = ? and starts.marked_at >= ?
                    and not exists (select 1 from "attendance_marks" as corrections where corrections.tenant_id = ? and corrections.corrects_mark_id = starts.id)
                    order by starts.marked_at limit 1) as until
            from unnest(?::uuid[]) as people(id)',
            [
                $tenant, MarkType::Start->value, $start, $tenant,
                $tenant, MarkType::Start->value, $next, $tenant,
                self::pgArray(array_values(array_filter($employeeIds, Str::isUuid(...)))),
            ],
        );

        $windows = [];
        foreach ($rows as $row) {
            if (is_object($row) && property_exists($row, 'id') && is_string($row->id)) {
                $windows[$row->id] = [
                    property_exists($row, 'since') && is_string($row->since) ? $row->since : $start,
                    property_exists($row, 'until') && is_string($row->until) ? $row->until : 'infinity',
                ];
            }
        }

        return $windows;
    }

    private static function utc(CarbonImmutable $instant): string
    {
        return $instant->utc()->format('Y-m-d H:i:s.uP');
    }

    /**
     * A Postgres array literal of `$values`, each quoted.
     *
     * @param  array<array-key, string>  $values
     */
    private static function pgArray(array $values): string
    {
        return '{'.implode(',', array_map(fn (string $value): string => '"'.$value.'"', $values)).'}';
    }

    /**
     * @param  list<AttendanceMark>  $marks  one person's, in order of time
     * @param  string  $first  the period's first local date, Y-m-d
     * @param  string  $last  its last, Y-m-d
     * @return array<string, list<AttendanceMark>>
     */
    private static function group(array $marks, string $first, string $last, string $timezone): array
    {
        $days = [];
        $openDay = null;
        foreach ($marks as $mark) {
            // of() leaves the voids out; one here is a query that forgot it.
            $at = $mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time.");
            $day = $mark->type === MarkType::Start || $openDay === null ? $at->setTimezone($timezone)->toDateString() : $openDay;
            $openDay = $mark->type === MarkType::End ? null : $day;

            if ($day >= $first && $day <= $last) {
                $days[$day][] = $mark;
            }
        }

        return $days;
    }
}
