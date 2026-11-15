<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Actions\ConfirmWeek;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Leave\Contracts\Absences;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * What «Mi semana» shows a person of one of their weeks (criterio 8.1): their marks day by day (type, local time, whether
 * it falls on the next day and whether RH corrected it), whether they were away that day with approved vacations or a
 * leave (never its type), the minutes of each day and of the week as criterio 2.9 splits
 * them, the limits broken, a day left with no end (unless it is the one still open now), their answer, the version of the
 * week they read (`digest`, sent back with their answer) and why it cannot be confirmed yet (`blocked`: no marks, or its
 * last day still open). Never where a mark was taken, nor the device. Counted from the marks every time.
 */
final class PersonWeek
{
    /**
     * @return array<string, mixed>
     */
    public static function of(string $employeeId, string $name, WeekPeriod $week, string $slug): array
    {
        $timezone = config()->string('app.display_timezone');
        $byDay = EffectiveMarks::byDay($employeeId, $week);
        $schedule = AttendanceSchedule::query()->where('employee_id', $employeeId)->first();
        $latest = EffectiveMarks::of($employeeId)->latest('marked_at')->first(['id'])?->id;
        $yesterday = CarbonImmutable::now($timezone)->subDay()->startOfDay();
        $away = AbsentDays::of(resolve(Absences::class), $week, [$employeeId])[$employeeId] ?? [];
        // The tolerance is read only when the schedule has start times: a week without them costs no query more.
        $tolerance = $schedule?->starts_by_weekday === null ? 0 : CompanyPolicy::current()->late_tolerance_minutes;
        $days = [];
        $hours = [];

        foreach (range(0, 6) as $offset) {
            $date = $week->start()->addDays($offset);
            $marks = $byDay[$date->toDateString()] ?? [];
            $hours[] = $day = WorkedDay::hours($date, $marks, $schedule, $tolerance, isset($away[$offset]));
            // The day the person is still working (its last mark is their last one, from yesterday on) is not missing its end yet.
            $stillOpen = $marks !== [] && end($marks)->id === $latest && $date->greaterThanOrEqualTo($yesterday);

            $days[] = [
                'date' => $date->toDateString(),
                'marks' => array_map(fn (AttendanceMark $mark): array => self::mark($mark, $date, $timezone), $marks),
                'workedMinutes' => $day->workedMinutes,
                'scheduledMinutes' => $day->scheduledMinutes,
                'restDay' => $day->restDay,
                'lateMinutes' => $day->lateMinutes,
                'missingEnd' => WorkedDay::missingEnd($marks) && ! $stillOpen,
                // Approved vacations or a leave: the day is not one they missed. Its marks, if any, still count.
                'absence' => $away[$offset] ?? null,
            ];
        }

        $summary = HoursCalculator::week($hours, $schedule->shift_type ?? ShiftType::Day, $week->start()->year);
        // Of each day's minutes, how many were overtime (for the bar of the week): the calculator's, never a second count.
        $days = array_map(fn (array $day): array => [...$day, 'overtimeMinutes' => $summary->overtimeByDay[$day['date']] ?? 0], $days);
        $answer = WeeklyConfirmation::query()->where('employee_id', $employeeId)->where('week_start', $week->start()->toDateString())->first();
        $status = $answer?->status->value ?? ($week->isOver() ? 'pending' : 'open');
        $start = $week->start()->toDateString();
        $marks = array_merge([], ...array_values($byDay));
        $blocked = $status === 'pending' ? ConfirmWeek::blocker($marks) : null;

        return [
            'name' => $name,
            'week' => ['start' => $start, 'end' => $week->end()->toDateString()],
            'status' => $status,
            'answeredAt' => $answer === null ? null : ($answer->confirmed_at ?? $answer->disputed_at)?->setTimezone($timezone)->format('Y-m-d H:i'),
            'disputeReason' => $answer?->reason,
            'digest' => WeekDigest::ofMarks($marks),
            'blocked' => $blocked,
            'days' => $days,
            'requests' => self::requests($employeeId, $week, $marks, $timezone),
            'summary' => [
                'ordinaryMinutes' => $summary->ordinaryMinutes,
                'overtimeMinutes' => $summary->overtimeMinutes,
                'doubleMinutes' => $summary->doubleMinutes,
                'tripleMinutes' => $summary->tripleMinutes,
                'restDayMinutes' => $summary->restDayMinutes,
                'flags' => array_map(fn (OverLimit $flag): string => $flag->value, $summary->flags),
            ],
            'confirmUrl' => $status === 'pending' && $blocked === null ? route('attendance.device.week.confirm', [$slug, $start]) : null,
            'disputeUrl' => in_array($status, ['pending', 'confirmed'], true) ? route('attendance.device.week.dispute', [$slug, $start]) : null,
            'previousUrl' => route('attendance.device.week.show', [$slug, $week->start()->subWeek()->toDateString()]),
            'nextUrl' => $week->isOver() ? route('attendance.device.week.show', [$slug, $week->start()->addWeek()->toDateString()]) : null,
        ];
    }

    /**
     * @return array{id: string, type: string, time: string, nextDay: bool, corrected: bool, reason: string|null, evidence: string}
     */
    private static function mark(AttendanceMark $mark, CarbonImmutable $date, string $timezone): array
    {
        $at = ($mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time."))->setTimezone($timezone);
        $corrected = $mark->method === MarkMethod::Correction;

        return [
            'id' => $mark->id,
            'type' => $mark->type->value,
            'time' => $at->format('H:i'),
            'nextDay' => ! $at->isSameDay($date),
            'corrected' => $corrected,
            // Why it was corrected (criterio 8.1): the person sees the reason of every correction of their record.
            'reason' => $corrected ? $mark->reason : null,
            // How much the mark proves, to say it in words (criterio 5.3).
            'evidence' => $mark->method->evidence()->value,
        ];
    }

    /**
     * The person's requests to correct the week: of a time in it, or of one of its marks. RH's note with the resolved ones.
     *
     * @param  list<AttendanceMark>  $marks
     * @return list<array{id: string, markId: string|null, type: string, date: string, time: string, status: string, note: string|null}>
     */
    private static function requests(string $employeeId, WeekPeriod $week, array $marks, string $timezone): array
    {
        $ids = array_map(fn (AttendanceMark $mark): string => $mark->id, $marks);

        return array_values(CorrectionRequest::query()->where('employee_id', $employeeId)
            ->where(fn (Builder $query) => $query->whereBetween('proposed_at', [$week->startUtc(), $week->nextStartUtc()->subMicrosecond()])->orWhereIn('mark_id', $ids))
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'mark_id', 'type', 'proposed_at', 'status', 'note'])
            ->map(fn (CorrectionRequest $request): array => [
                'id' => $request->id,
                'markId' => $request->mark_id,
                'type' => $request->type->value,
                'date' => $request->proposed_at->setTimezone($timezone)->toDateString(),
                'time' => $request->proposed_at->setTimezone($timezone)->format('H:i'),
                'status' => $request->status->value,
                'note' => $request->note,
            ])->all());
    }
}
