<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** The kinds of shift of art. 60 of the LFT, each with its daily ordinary maximum (art. 61, criterio 2.2). */
enum ShiftType: string
{
    case Day = 'day';
    case Night = 'night';
    case Mixed = 'mixed';
}
