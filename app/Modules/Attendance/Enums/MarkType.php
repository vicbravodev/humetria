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
}
