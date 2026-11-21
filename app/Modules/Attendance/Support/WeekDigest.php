<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Leave\Contracts\Absences;
use App\Modules\Leave\Data\AbsenceData;

/**
 * The version of a person's week: the sha256 of the sorted ids of its effective marks (EffectiveMarks::forWeek) and of its
 * approved absences (their days and whether vacations or leave, never the type). A mark added, corrected or voided, or an
 * absence approved or cancelled, gives another digest, since every correction is a new mark. A week with no absence keeps
 * the digest of its marks alone. The person's answer carries the digest of the week they read, so an answer is about that
 * week and not one RH changed meanwhile. Ids and dates only: no times.
 */
final class WeekDigest
{
    public static function of(string $employeeId, WeekPeriod $week): string
    {
        return self::ofMarks(EffectiveMarks::forWeek($employeeId, $week), self::absences($employeeId, $week));
    }

    /**
     * @param  list<AttendanceMark>  $marks
     * @param  list<AbsenceData>  $absences
     */
    public static function ofMarks(array $marks, array $absences = []): string
    {
        $ids = array_map(fn (AttendanceMark $mark): string => $mark->id, $marks);
        sort($ids);
        $away = array_map(fn (AbsenceData $absence): string => "{$absence->kind}:{$absence->from->toDateString()}:{$absence->to->toDateString()}", $absences);
        sort($away);

        return hash('sha256', implode(',', [...$ids, ...$away]));
    }

    /**
     * The person's absences that touch the week: one query.
     *
     * @return list<AbsenceData>
     */
    public static function absences(string $employeeId, WeekPeriod $week): array
    {
        return resolve(Absences::class)->between($week->start(), $week->end(), [$employeeId]);
    }
}
