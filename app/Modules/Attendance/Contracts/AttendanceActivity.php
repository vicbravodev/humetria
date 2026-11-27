<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Data\CenterAttendanceActivity;
use Carbon\CarbonImmutable;

/**
 * What was set up and marked at a work center of the current company since a moment, for the demo guide: counts only,
 * never a person's marks.
 */
interface AttendanceActivity
{
    public function atCenter(string $workCenterId, CarbonImmutable $since): CenterAttendanceActivity;
}
