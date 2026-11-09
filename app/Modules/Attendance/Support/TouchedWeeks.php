<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use Carbon\CarbonImmutable;

/**
 * The weeks a change of a mark touches, by their Monday ('Y-m-d'): those of the mark's old time and its new one, each by the
 * day it belongs to (the start before it; a night that ends on Monday belongs to Sunday's week). What a confirmed week
 * reopens on (ReopenWeeks) and what RH is told before approving (CorrectionPreview).
 */
final class TouchedWeeks
{
    /**
     * @param  list<CarbonImmutable>  $instants  the time the mark had, if it had one, and the time it will have
     * @return list<string>
     */
    public static function of(string $employeeId, array $instants): array
    {
        $weeks = [];
        foreach ($instants as $at) {
            $weeks[] = WeekPeriod::containing($at)->start()->toDateString();
            $start = self::startBefore($employeeId, $at);
            $weeks[] = $start === null ? null : WeekPeriod::containing($start)->start()->toDateString();
        }

        return array_values(array_unique(array_filter($weeks)));
    }

    /** The last start at or before a moment, replaced ones included: a wider guess only touches a week more. */
    private static function startBefore(string $employeeId, CarbonImmutable $at): ?CarbonImmutable
    {
        return AttendanceMark::query()->where('employee_id', $employeeId)->where('type', MarkType::Start)
            ->where('marked_at', '<=', $at->utc())->latest('marked_at')->first(['marked_at'])?->marked_at;
    }
}
