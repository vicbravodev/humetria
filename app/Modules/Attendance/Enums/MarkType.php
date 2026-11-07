<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** What a mark records (criterios 3.1 and 3.2): the start and end of the working day, and of each break. */
enum MarkType: string
{
    case Start = 'start';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
    case End = 'end';

    /**
     * What may follow the person's last mark (null: none yet): a start only with no day open, X     * the day open, the end of a break only during one. RecordMark enforces it and the screen of Marcar offers it.
     *
     * @return list<self>
     */
    public static function allowedAfter(?self $last): array
    {
        return match ($last) {
            null, self::End => [self::Start],
            self::Start, self::BreakEnd => [self::BreakStart, self::End],
            self::BreakStart => [self::BreakEnd, self::End],
        };
    }
}
