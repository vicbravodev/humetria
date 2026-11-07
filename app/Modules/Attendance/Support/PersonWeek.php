<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * What «Mi semana» shows a person of one of their weeks (criterio 8.1): their marks day by day (type, local time, whether
 * it falls on the next day and whether RH corrected it), the minutes of each day and of the week as criterio 2.9 splits
 * them, the limits broken, a day left with no end (unless it is the one still open now), and their answer. Never where a
 * mark was taken, nor the device. Counted from the marks every time.
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
        $days = [];
        $hours = [];

        foreach (range(0, 6) as $offset) {
            $date = $week->start()->addDays($offset);
            $marks = $byDay[$date->toDateString()] ?? [];
            $hours[] = $day = WorkedDay::hours($date, $marks, $schedule);
            // The day the person is still working (its last mark is their last one, from yesterday on) is not missing its end yet.
            $stillOpen = $marks !== [] && end($marks)->id === $latest && $date->greaterThanOrEqualTo($yesterday);

            $days[] = [
                'date' => $date->toDateString(),
                'marks' => array_map(fn (AttendanceMark $mark): array => self::mark($mark, $date, $timezone), $marks),
                'workedMinutes' => $day->workedMinutes,
                'scheduledMinutes' => $day->scheduledMinutes,
                'restDay' => $day->restDay,
                'missingEnd' => WorkedDay::missingEnd($marks) && ! $stillOpen,
            ];
        }

        $summary = HoursCalculator::week($hours, $schedule->shift_type ?? ShiftType::Day, $week->start()->year);
        $answer = WeeklyConfirmation::query()->where('employee_id', $employeeId)->where('week_start', $week->start()->toDateString())->first();
        $status = $answer?->status->value ?? ($week->isOver() ? 'pending' : 'open');
        $start = $week->start()->toDateString();

        return [
            'name' => $name,
            'week' => ['start' => $start, 'end' => $week->end()->toDateString()],
            'status' => $status,
            'answeredAt' => $answer === null ? null : ($answer->confirmed_at ?? $answer->disputed_at)?->setTimezone($timezone)->format('Y-m-d H:i'),
            'disputeReason' => $answer?->reason,
            'days' => $days,
            'summary' => [
                'ordinaryMinutes' => $summary->ordinaryMinutes,
                'overtimeMinutes' => $summary->overtimeMinutes,
                'doubleMinutes' => $summary->doubleMinutes,
                'tripleMinutes' => $summary->tripleMinutes,
                'restDayMinutes' => $summary->restDayMinutes,
                'flags' => array_map(fn (OverLimit $flag): string => $flag->value, $summary->flags),
            ],
            'confirmUrl' => $status === 'pending' ? route('attendance.device.week.confirm', [$slug, $start]) : null,
            'disputeUrl' => in_array($status, ['pending', 'confirmed'], true) ? route('attendance.device.week.dispute', [$slug, $start]) : null,
            'previousUrl' => route('attendance.device.week.show', [$slug, $week->start()->subWeek()->toDateString()]),
            'nextUrl' => $week->isOver() ? route('attendance.device.week.show', [$slug, $week->start()->addWeek()->toDateString()]) : null,
        ];
    }

    /**
     * @return array{type: string, time: string, nextDay: bool, corrected: bool}
     */
    private static function mark(AttendanceMark $mark, CarbonImmutable $date, string $timezone): array
    {
        $at = ($mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time."))->setTimezone($timezone);

        return [
            'type' => $mark->type->value,
            'time' => $at->format('H:i'),
            'nextDay' => ! $at->isSameDay($date),
            'corrected' => $mark->method === MarkMethod::Correction,
        ];
    }
}
