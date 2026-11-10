<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use Carbon\CarbonImmutable;

/**
 * What approving a pending request of the queue would do, said before RH decides (board A3), from the rules that approving
 * runs: that day of the person in the record and the time asked; whether the approval would be refused for leaving the day
 * out of order (CorrectedSequence), whether the day would still miss its end (WorkedDay::missingEnd), the confirmed weeks it
 * would reopen (TouchedWeeks, ReopenWeeks), and how the day counts (no schedule, or a rest day of it). Marks as local times
 * and types only: never a position or a device. A request already resolved has nothing to preview.
 *
 * The day is the one the mark belongs to: its own date for a start; for the rest, the date of the last start within the
 * 24 h before it (a night that ends after midnight belongs to the day it began, art. 68 puts no day above 12 h).
 */
final class CorrectionPreview
{
    private const int NIGHT_REACH_HOURS = 24;

    /**
     * @return array{day: array{date: string, marks: list<array{type: string, time: string, nextDay: bool}>, asked: array{type: string, time: string, nextDay: bool}}, effect: array{sequenceOk: bool, afterApproval: string, reopensWeeks: list<string>, noSchedule: bool, restDay: bool}}|null
     */
    public static function of(CorrectionRequest $request): ?array
    {
        if ($request->status !== CorrectionStatus::Pending) {
            return null;
        }

        $employee = $request->employee_id;
        $asked = $request->proposed_at;
        $original = $request->mark_id === null ? null : EffectiveMarks::of($employee)->whereKey($request->mark_id)->first();
        $date = self::dayOf($employee, $original, $request->type, $asked);
        $marks = EffectiveMarks::byDayBetween([$employee], $date, $date)[$employee][$date->toDateString()] ?? [];
        $schedule = AttendanceSchedule::query()->where('employee_id', $employee)->first(['minutes_by_weekday']);

        return [
            'day' => [
                'date' => $date->toDateString(),
                'marks' => array_map(fn (AttendanceMark $mark): array => self::point($mark->type, $mark->marked_at ?? $asked, $date), $marks),
                'asked' => self::point($request->type, $asked, $date),
            ],
            'effect' => [
                'sequenceOk' => CorrectedSequence::follows($employee, $original, $request->type, $asked),
                'afterApproval' => self::afterApproval($marks, $original, $request, $asked),
                'reopensWeeks' => self::reopensWeeks($employee, $original, $asked),
                'noSchedule' => $schedule === null,
                'restDay' => WorkedDay::hours($date, [], $schedule)->restDay,
            ],
        ];
    }

    /** The local date the mark belongs to. */
    private static function dayOf(string $employeeId, ?AttendanceMark $original, MarkType $type, CarbonImmutable $asked): CarbonImmutable
    {
        $zone = config()->string('app.display_timezone');
        $start = $type === MarkType::Start ? null : EffectiveMarks::of($employeeId)
            ->when($original, fn ($query, AttendanceMark $mark) => $query->whereKeyNot($mark->id))
            ->where('type', MarkType::Start)->where('marked_at', '<=', $asked->utc())->where('marked_at', '>', $asked->subHours(self::NIGHT_REACH_HOURS)->utc())
            ->latest('marked_at')->first(['marked_at'])?->marked_at;

        return CarbonImmutable::parse(($start ?? $asked)->setTimezone($zone)->toDateString(), $zone);
    }

    /**
     * @return array{type: string, time: string, nextDay: bool}
     */
    private static function point(MarkType $type, CarbonImmutable $at, CarbonImmutable $date): array
    {
        $local = $at->setTimezone(config()->string('app.display_timezone'));

        return ['type' => $type->value, 'time' => $local->format('H:i'), 'nextDay' => $local->toDateString() !== $date->toDateString()];
    }

    /**
     * 'complete' when the day, once approved, ends with its end; 'missing_end' when its last mark is not an end.
     *
     * @param  list<AttendanceMark>  $marks
     */
    private static function afterApproval(array $marks, ?AttendanceMark $original, CorrectionRequest $request, CarbonImmutable $asked): string
    {
        $after = array_values(array_filter($marks, fn (AttendanceMark $mark): bool => $mark->id !== $original?->id));
        $after[] = (new AttendanceMark)->forceFill(['type' => $request->type, 'marked_at' => $asked]);
        usort($after, fn (AttendanceMark $a, AttendanceMark $b): int => $a->marked_at <=> $b->marked_at);

        return WorkedDay::missingEnd($after) ? 'missing_end' : 'complete';
    }

    /**
     * The Mondays of the confirmed weeks the approval would reopen.
     *
     * @return list<string>
     */
    private static function reopensWeeks(string $employeeId, ?AttendanceMark $original, CarbonImmutable $asked): array
    {
        $weeks = TouchedWeeks::of($employeeId, array_values(array_filter([$original?->marked_at, $asked])));

        $confirmed = WeeklyConfirmation::query()->where('employee_id', $employeeId)->whereIn('week_start', $weeks)
            ->where('status', WeekStatus::Confirmed)->orderBy('week_start')->get(['week_start']);

        return array_values($confirmed->map(fn (WeeklyConfirmation $week): string => $week->week_start->toDateString())->all());
    }
}
