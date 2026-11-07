<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** Where a person works, as RH sets it on their schedule (criterios 4.2 and 4.3): it decides how they may mark. */
enum WorkMode: string
{
    case Onsite = 'onsite';
    case Remote = 'remote';
}
