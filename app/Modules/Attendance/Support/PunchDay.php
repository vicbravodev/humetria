<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * What the screen of Marcar shows a person: the marks of their day (type and local time only, never where) and what may
 * come next (MarkType::allowedAfter, the one place the sequence is written). Their day is today's in the zone of their
 * center, reaching back to its start when it began before midnight and is still open or ended today. Each mark shows the
 * time of the place it was taken, with its zone when that is not their center's («14:00 · hora de Tijuana»).
 */
final class PunchDay
{
    /**
     * @param  string  $timezone  the zone of the person's center
     * @return array{marks: list<array{type: string, time: string, clock: string|null}>, next: list<string>}
     */
    public static function of(string $employeeId, string $timezone): array
    {
        // Bound in UTC: a Carbon binding loses its offset and the database session is UTC.
        $from = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $last = EffectiveMarks::of($employeeId)->latest('marked_at')->first(['type', 'marked_at']);

        if ($last !== null && ($last->type !== MarkType::End || $last->marked_at?->greaterThanOrEqualTo($from) === true)) {
            $start = EffectiveMarks::of($employeeId)->where('type', MarkType::Start)->latest('marked_at')->first(['marked_at'])?->marked_at;
            $from = $start !== null && $start->lessThan($from) ? $start->utc() : $from;
        }

        $marks = EffectiveMarks::of($employeeId)->where('marked_at', '>=', $from)->orderBy('marked_at')->get(['type', 'marked_at', 'timezone']);

        return [
            'marks' => array_values($marks->map(fn (AttendanceMark $mark): array => [
                'type' => $mark->type->value,
                'time' => ($mark->marked_at ?? throw new LogicException('A mark has no time.'))->setTimezone($mark->timezone)->format('H:i'),
                // The zone of a mark taken elsewhere, for the screen to say whose hour it is; null at their own.
                'clock' => $mark->timezone === $timezone ? null : $mark->timezone,
            ])->all()),
            'next' => array_map(fn (MarkType $type): string => $type->value, MarkType::allowedAfter($last?->type)),
        ];
    }
}
