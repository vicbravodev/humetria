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
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\MarkLock;
use App\Modules\Attendance\Support\PersonTimezone;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A person confirms their week (criterio 5.2) with their verified device: what they confirm is «acordado» (1.3), so it
 * goes to the audit trail with the device and the version of the week confirmed (its digest, WeekDigest). Only a week
 * that is over (its Sunday ended, local), with marks of the person, whose last day is not still open (a night that
 * started on Sunday ends on Monday: its end would change the week after it was confirmed), and only the version the
 * person read: a week RH changed meanwhile is read again first. Confirming again keeps the first moment. A disputed week
 * is not confirmed over the dispute: RH resolves it (ResolveDispute), and then the person confirms what they read.
 */
final readonly class ConfirmWeek
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit, private PersonTimezone $timezones) {}

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

            $marks = EffectiveMarks::forWeek($employeeId, $week);
            $blocked = self::blocker($marks) ?? (hash_equals(WeekDigest::ofMarks($marks), $digest) ? null : 'week_changed');

            if ($blocked !== null) {
                throw new WeekRejected($blocked);
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
     * Why a week with these marks cannot be confirmed yet, whatever was read: `no_marks`, or `day_open` while its last mark
     * is not an end. Null when it can.
     *
     * @param  list<AttendanceMark>  $marks  the week's effective marks, in order of time
     */
    public static function blocker(array $marks): ?string
    {
        return match (true) {
            $marks === [] => 'no_marks',
            end($marks)->type !== MarkType::End => 'day_open',
            default => null,
        };
    }
}
