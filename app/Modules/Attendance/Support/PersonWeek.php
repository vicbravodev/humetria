<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Actions\ConfirmWeek;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\MarkReview;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Leave\Contracts\Absences;
use App\Modules\Organization\Contracts\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * What «Mi semana» shows a person of one of their weeks (criterio 8.1): their marks day by day (type, local time, whether
 * it falls on the next day and whether RH corrected it), whether they were away that day with approved vacations or a
 * leave (never its type), the minutes of each day and of the week as criterio 2.9 splits
 * them, the limits broken, a day left with no end (unless it is the one still open now), their answer, the version of the
 * week they read (`digest`, sent back with their answer) and why it cannot be confirmed yet (`blocked`: no marks, its
 * last day still open, or days without cover; those days are also listed in `uncoveredDays`, and each day says whether a
 * mark is `required` of it and `uncovered`: WeekCompleteness). Never where a mark was taken, nor the device. Counted from the marks every time. The week is
 * read in the zone of the person's center (the week's own); each mark shows the time of the place it was taken, with
 * that zone when it is another, and whether it was taken at another center (a trip: no lateness that day). Each day is
 * read with the schedule in effect that day (ScheduleTimeline), so a change in the middle of the week splits it.
 */
final class PersonWeek
{
    /**
     * @return array<string, mixed>
     */
    public static function of(string $employeeId, string $name, WeekPeriod $week, string $slug): array
    {
        $timezone = $week->timezone();
        $byDay = EffectiveMarks::byDay($employeeId, $week);
        $schedules = ScheduleTimeline::of([$employeeId]);
        $latest = EffectiveMarks::of($employeeId)->latest('marked_at')->first(['id'])?->id;
        $yesterday = CarbonImmutable::now($timezone)->subDay()->startOfDay();
        $absences = WeekDigest::absences($employeeId, $week);
        $away = AbsentDays::of($absences, $week)[$employeeId] ?? [];
        $completeness = WeekCompleteness::of(
            $week, $byDay, $schedules, $employeeId, $away,
            resolve(Absences::class)->holidaysBetween($week->start(), $week->end()), resolve(Roster::class)->find($employeeId)?->hiredOn,
        );
        // The tolerance is read only when a schedule has start times: a week without them costs no query more.
        $tolerance = $schedules->hasStartTimes() ? CompanyPolicy::current()->late_tolerance_minutes : 0;
        $reviews = self::reviews($byDay);
        $days = [];
        $hours = [];

        foreach (range(0, 6) as $offset) {
            $date = $week->start()->addDays($offset);
            $marks = $byDay[$date->toDateString()] ?? [];
            $hours[] = $day = WorkedDay::hours($date, $marks, $schedules->on($employeeId, $date), $tolerance, isset($away[$offset]));
            // The day the person is still working (its last mark is their last one, from yesterday on) is not missing its end yet.
            $stillOpen = $marks !== [] && end($marks)->id === $latest && $date->greaterThanOrEqualTo($yesterday);

            $days[] = [
                'date' => $date->toDateString(),
                'marks' => array_map(fn (AttendanceMark $mark): array => self::mark($mark, $date, $timezone, $reviews[$mark->id] ?? null), $marks),
                'workedMinutes' => $day->workedMinutes,
                'scheduledMinutes' => $day->scheduledMinutes,
                'restDay' => $day->restDay,
                'lateMinutes' => $day->lateMinutes,
                'missingEnd' => WorkedDay::missingEnd($marks) && ! $stillOpen,
                // Approved vacations or a leave: the day is not one they missed. Its marks, if any, still count.
                'absence' => $away[$offset] ?? null,
                // Whether a mark is asked of this day (criterio 5.2) and it is still missing; a rest day, a holiday, a day away or one not over is not.
                ...$completeness->day($offset),
            ];
        }

        $summary = HoursCalculator::week($hours, $schedules->on($employeeId, $week->end())->shift_type ?? ShiftType::Day, $week->start()->year);
        // Of each day's minutes, how many were overtime (for the bar of the week): the calculator's, never a second count.
        $days = array_map(fn (array $day): array => [...$day, 'overtimeMinutes' => $summary->overtimeByDay[$day['date']] ?? 0], $days);
        $answer = WeeklyConfirmation::query()->where('employee_id', $employeeId)->where('week_start', $week->start()->toDateString())->first();
        $status = $answer?->status->value ?? ($week->isOver() ? 'pending' : 'open');
        $start = $week->start()->toDateString();
        $marks = array_merge([], ...array_values($byDay));
        $blocked = $status === 'pending' ? ConfirmWeek::blocker($marks, $completeness) : null;

        return [
            'name' => $name,
            'week' => ['start' => $start, 'end' => $week->end()->toDateString()],
            'status' => $status,
            'answeredAt' => $answer === null ? null : ($answer->confirmed_at ?? $answer->disputed_at)?->setTimezone($timezone)->format('Y-m-d H:i'),
            'disputeReason' => $answer?->reason,
            // RH's answer to their dispute, with the week back to them (criterio 5.2).
            'resolution' => $answer?->resolution_note,
            'digest' => WeekDigest::ofMarks($marks, $absences),
            'blocked' => $blocked,
            // The days to cover, also with the block off: the person sees what is missing even if they can confirm.
            'uncoveredDays' => $completeness->uncoveredDays(),
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
     * Where RH's review of each mark made without a connection stands: the decision in force, by mark id, in one query that a
     * week with none does not make.
     *
     * @param  array<string, list<AttendanceMark>>  $byDay
     * @return array<string, MarkReview>
     */
    private static function reviews(array $byDay): array
    {
        $offline = array_filter(array_merge([], ...array_values($byDay)), fn (AttendanceMark $mark): bool => self::offline($mark));

        return MarkReview::latestByMark(array_values(array_map(fn (AttendanceMark $mark): string => $mark->id, $offline)));
    }

    private static function offline(AttendanceMark $mark): bool
    {
        return in_array($mark->method, [MarkMethod::OfflineQr, MarkMethod::OfflineDeclared], true);
    }

    /**
     * @return array{id: string, type: string, time: string, clock: string|null, away: bool, nextDay: bool, corrected: bool, reason: string|null, evidence: string, offline: bool, review: string|null, reviewNote: string|null}
     */
    private static function mark(AttendanceMark $mark, CarbonImmutable $date, string $timezone, ?MarkReview $review): array
    {
        $at = ($mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time."))->setTimezone($mark->timezone);
        $corrected = $mark->method === MarkMethod::Correction;

        return [
            'id' => $mark->id,
            'type' => $mark->type->value,
            'time' => $at->format('H:i'),
            // Whose hour it is, when not the week's own zone: «14:00 · hora de Tijuana».
            'clock' => $mark->timezone === $timezone ? null : $mark->timezone,
            'away' => $mark->away_center_id !== null,
            'nextDay' => $at->toDateString() !== $date->toDateString(),
            'corrected' => $corrected,
            // Why it was corrected (criterio 8.1): the person sees the reason of every correction of their record.
            'reason' => $corrected ? $mark->reason : null,
            // How much the mark proves, to say it in words (criterio 5.3).
            'evidence' => $mark->method->evidence()->value,
            // Made without a connection, and where RH's review of it stands (criterio 5.4): every one waits for it, with a QR or
            // without; the note is shown with `incorrect`, the way RH told the person what was wrong.
            'offline' => self::offline($mark),
            'review' => self::offline($mark) ? ($review?->decision->value ?? 'pending') : null,
            'reviewNote' => $review?->decision === ReviewDecision::Incorrect ? $review->note : null,
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
