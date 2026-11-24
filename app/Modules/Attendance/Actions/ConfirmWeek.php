<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Events\WeekConfirmed;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\AbsentDays;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\MarkLock;
use App\Modules\Attendance\Support\PersonTimezone;
use App\Modules\Attendance\Support\ScheduleTimeline;
use App\Modules\Attendance\Support\WeekCompleteness;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Leave\Contracts\Absences;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A person confirms their week (criterio 5.2) with their verified device: what they confirm is «acordado» (1.3), so it
 * goes to the audit trail with the device and the version of the week confirmed (its digest, WeekDigest). Only a week
 * that is over (its Sunday ended, local), with marks of the person, whose last day is not still open (a night that
 * started on Sunday ends on Monday: its end would change the week after it was confirmed), and only the version the
 * person read: a week RH changed meanwhile is read again first. Nor is a week with days without cover (a working day of
 * their schedule, already over, with no marks and no holiday, hire date or approved absence: WeekCompleteness): it needs a
 * correction that adds the missing marks, or a leave RH records. That block holds for every company; the setting
 * `attendance.week.block_uncovered_days` turns it off, and then the days are only logged. Confirming again keeps the first
 * moment. A disputed week is not confirmed over the dispute: RH resolves it (ResolveDispute), and then the person
 * confirms what they read.
 */
final readonly class ConfirmWeek
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit, private PersonTimezone $timezones, private Absences $absences, private Roster $roster) {}

    /**
     * @throws WeekRejected
     */
    public function handle(string $employeeId, CarbonImmutable $weekStart, string $credentialId, string $digest): WeeklyConfirmation
    {
        $week = WeekPeriod::ofDate($weekStart, $this->timezones->of($employeeId));

        if (! $week->isOver()) {
            throw new WeekRejected('week_not_over');
        }

        return DB::transaction(function () use ($employeeId, $week, $credentialId, $digest): WeeklyConfirmation {
            // Before the row (a correction takes this lock, then the row): a correction in flight commits first, and the
            // marks read below are the ones after it.
            MarkLock::of($employeeId);
            $confirmation = WeeklyConfirmation::lockedFor($this->tenant->id(), $employeeId, $week);

            if ($confirmation->status === WeekStatus::Confirmed) {
                return $confirmation;
            }

            if ($confirmation->status === WeekStatus::Disputed) {
                throw new WeekRejected('week_disputed');
            }

            $byDay = EffectiveMarks::byDay($employeeId, $week);
            $marks = array_merge([], ...array_values($byDay));
            $absences = WeekDigest::absences($employeeId, $week);
            $completeness = WeekCompleteness::of(
                $week, $byDay, ScheduleTimeline::of([$employeeId]), $employeeId, AbsentDays::of($absences, $week)[$employeeId] ?? [],
                $this->absences->holidaysBetween($week->start(), $week->end()), $this->roster->find($employeeId)?->hiredOn,
            );
            $blocked = self::blocker($marks, $completeness) ?? (hash_equals(WeekDigest::ofMarks($marks, $absences), $digest) ? null : 'week_changed');

            if ($blocked !== null) {
                throw new WeekRejected($blocked);
            }

            // Only here with the block turned off: how many weeks it would stop, as a count, never the days.
            if ($completeness->uncoveredDays() !== []) {
                Log::info('attendance.week_uncovered_days_computed', ['employee_id' => $employeeId, 'week_start' => $week->start()->toDateString(), 'uncovered_days' => count($completeness->uncoveredDays())]);
            }

            $confirmation->update(['status' => WeekStatus::Confirmed, 'confirmed_at' => CarbonImmutable::now()->utc(), 'disputed_at' => null, 'reason' => null, 'credential_id' => $credentialId, 'digest' => $digest, ...WeeklyConfirmation::NO_RESOLUTION]);
            $ids = ['employee_id' => $employeeId, 'week_start' => $week->start()->toDateString(), 'key_id' => $credentialId];
            $this->audit->handle('attendance.week_confirmed', $confirmation, [...$ids, 'digest' => $digest]);
            Log::info('attendance.week_confirmed', ['confirmation_id' => $confirmation->id, ...$ids]);
            event(new WeekConfirmed($confirmation->id, $employeeId, $ids['week_start']));

            return $confirmation;
        });
    }

    /**
     * Why a week with these marks cannot be confirmed yet, whatever was read: `no_marks`, `day_open` while its last mark is
     * not an end, or `uncovered_days` when a working day was left without cover (unless the company's block is off:
     * `attendance.week.block_uncovered_days`). Null when it can.
     *
     * @param  list<AttendanceMark>  $marks  the week's effective marks, in order of time
     */
    public static function blocker(array $marks, WeekCompleteness $completeness): ?string
    {
        return match (true) {
            $marks === [] => 'no_marks',
            end($marks)->type !== MarkType::End => 'day_open',
            $completeness->uncoveredDays() !== [] && config()->boolean('attendance.week.block_uncovered_days') => 'uncovered_days',
            default => null,
        };
    }
}
