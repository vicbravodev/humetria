<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** What happens to a mark outside every zone (criterio 4.2): blocked by default, or allowed and flagged. */
enum OutOfZoneRule: string
{
    case Block = 'block';
    case AllowAndFlag = 'allow_and_flag';
}
