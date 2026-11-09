<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use Illuminate\Support\Facades\DB;

/**
 * The lock of one person's marks, until the transaction ends: whoever writes their marks (RecordMark, CorrectMark,
 * ResolveCorrection) and whoever answers their week on a digest (ConfirmWeek, DisputeWeek) take it, so an answer reads the
 * marks only once a correction in flight committed. Always before the row of the week: a correction takes this lock and
 * then that row.
 */
final class MarkLock
{
    public static function of(string $employeeId): void
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["attendance_marks:{$employeeId}"]);
    }
}
