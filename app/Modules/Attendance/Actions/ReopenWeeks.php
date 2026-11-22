<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\PersonTimezone;
use App\Modules\Attendance\Support\TouchedWeeks;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * A correction after an answer, or an absence approved or cancelled (AbsenceChanged), reopens the week (the design's Semana y día): what the person agreed to changed, so
 * they answer again. The weeks are those of the mark's old time and its new one, each by the day it belongs to (the start
 * before it; a night that ends on Monday belongs to Sunday's week). A disputed week reopens too: what the person disputed
 * changed. A pending week, even one whose dispute RH resolved, stays as it is.
 */
final readonly class ReopenWeeks
{
    public function __construct(private RecordAudit $audit, private PersonTimezone $timezones) {}

    public function handle(string $correctionId): void
    {
        $correction = AttendanceMark::query()->findOrFail($correctionId);
        // A mark added has no original: only its own weeks.
        $original = $correction->corrects_mark_id === null ? null : AttendanceMark::query()->findOrFail($correction->corrects_mark_id);
        $weeks = TouchedWeeks::of($correction->employee_id, array_values(array_filter([$original?->marked_at, $correction->marked_at])), $this->timezones->of($correction->employee_id));

        $this->reopen($correction->employee_id, $weeks, ['mark_id' => $correction->id]);
    }

    /**
     * An absence approved or cancelled reopens the answered weeks of its days (local dates, Y-m-d, both included): the
     * absences are part of what the person agreed to (criterio 1.3).
     */
    public function forAbsence(string $requestId, string $employeeId, string $from, string $to): void
    {
        $weeks = [];
        for ($monday = CarbonImmutable::parse($from)->startOfWeek(CarbonInterface::MONDAY); $monday->toDateString() <= $to; $monday = $monday->addWeek()) {
            $weeks[] = $monday->toDateString();
        }

        $this->reopen($employeeId, $weeks, ['leave_request_id' => $requestId]);
    }

    /**
     * @param  list<string>  $weeks  their Mondays, Y-m-d
     * @param  array<string, string>  $cause  what reopened them, for the audit trail: ids only
     */
    private function reopen(string $employeeId, array $weeks, array $cause): void
    {
        // Locked whatever their state: a confirmation holding its row (it checks the digest under that lock) is waited for and
        // then seen confirmed; one that comes after waits for this change and counts it. A week with no row yet is
        // not locked: a first confirmation in flight may then keep a digest without this change (closing it needs ConfirmWeek to
        // take the person's lock on their marks).
        $rows = WeeklyConfirmation::query()->where('employee_id', $employeeId)
            ->whereIn('week_start', $weeks)->lockForUpdate()->get();

        foreach ($rows->whereIn('status', [WeekStatus::Confirmed, WeekStatus::Disputed]) as $week) {
            $week->update(['status' => WeekStatus::Pending, 'confirmed_at' => null, 'disputed_at' => null, 'reason' => null, 'credential_id' => null, 'digest' => null]);
            $ids = ['employee_id' => $week->employee_id, 'week_start' => $week->week_start->toDateString()];
            $this->audit->handle('attendance.week_reopened', $week, [...$ids, ...$cause]);
            Log::info('attendance.week_reopened', ['confirmation_id' => $week->id, ...$ids]);
        }
    }
}
