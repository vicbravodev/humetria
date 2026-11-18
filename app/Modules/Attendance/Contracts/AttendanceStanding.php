<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Data\AttendanceSetup;

/**
 * Where the current company stands with the registro de jornada, as RH's panel shows it: counts of the whole company,
 * never a person's marks, hours, schedule or reasons.
 */
interface AttendanceStanding
{
    public function current(): AttendanceSetup;
}
