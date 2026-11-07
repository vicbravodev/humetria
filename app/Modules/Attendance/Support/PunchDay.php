<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * What the screen of Marcar shows a person: the marks of their day (type and local time only, never where) and what may
 * come next (MarkType::allowedAfter, the one place the sequence is written). Their day is today's, local, reaching back to
 * its start when it began before midnight and is still open or ended today.
 */
final class PunchDay
{
    /**
     * @return array{marks: list<array{type: string, time: string}>, next: list<string>}
     */
    public static function of(string $employeeId): array
    {
        $timezone = config()->string('app.display_timezone');
        // Bound in UTC: a Carbon binding loses its offset and the database session is UTC.
        $from = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $last = EffectiveMarks::of($employeeId)->latest('marked_at')->first(['type', 'marked_at']);

        if ($last !== null && ($last->type !== MarkType::End || $last->marked_at?->greaterThanOrEqualTo($from) === true)) {
            $start = EffectiveMarks::of($employeeId)->where('type', MarkType::Start)->latest('marked_at')->first(['marked_at'])?->marked_at;
            $from = $start !== null && $start->lessThan($from) ? $start->utc() : $from;
        }

        $marks = EffectiveMarks::of($employeeId)->where('marked_at', '>=', $from)->orderBy('marked_at')->get(['type', 'marked_at']);

        return [
            'marks' => array_values($marks->map(fn (AttendanceMark $mark): array => [
                'type' => $mark->type->value,
                'time' => ($mark->marked_at ?? throw new LogicException('A mark has no time.'))->setTimezone($timezone)->format('H:i'),
            ])->all()),
            'next' => array_map(fn (MarkType $type): string => $type->value, MarkType::allowedAfter($last?->type)),
        ];
    }
}
