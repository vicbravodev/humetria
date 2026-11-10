<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\TouchedWeeks;
use Illuminate\Support\Facades\Log;

/**
 * A correction after a confirmation reopens the week (the design's Semana y día): what the person agreed to changed, so
 * they answer again. The weeks are those of the mark's old time and its new one, each by the day it belongs to (the start
 * before it; a night that ends on Monday belongs to Sunday's week). A disputed week stays disputed: RH resolves it.
 */
final readonly class ReopenWeeks
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(string $correctionId): void
    {
        $correction = AttendanceMark::query()->findOrFail($correctionId);
        // A mark added has no original: only its own weeks.
        $original = $correction->corrects_mark_id === null ? null : AttendanceMark::query()->findOrFail($correction->corrects_mark_id);
        $weeks = TouchedWeeks::of($correction->employee_id, array_values(array_filter([$original?->marked_at, $correction->marked_at])));
        // Locked whatever their state: a confirmation holding its row (it checks the digest under that lock) is waited for and
        // then seen confirmed; one that comes after waits for this correction and counts its mark. A week with no row yet is
        // not locked: a first confirmation in flight may then keep a digest without this mark (closing it needs ConfirmWeek to
        // take the person's lock on their marks).
        $rows = WeeklyConfirmation::query()->where('employee_id', $correction->employee_id)
            ->whereIn('week_start', $weeks)->lockForUpdate()->get();

        foreach ($rows->where('status', WeekStatus::Confirmed) as $week) {
            $week->update(['status' => WeekStatus::Pending, 'confirmed_at' => null, 'device_id' => null, 'digest' => null]);
            $ids = ['employee_id' => $week->employee_id, 'week_start' => $week->week_start->toDateString()];
            $this->audit->handle('attendance.week_reopened', $week, [...$ids, 'mark_id' => $correction->id]);
            Log::info('attendance.week_reopened', ['confirmation_id' => $week->id, ...$ids]);
        }
    }
}
