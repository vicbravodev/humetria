<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Contracts\AttendanceActivity;
use App\Modules\Attendance\Data\CenterAttendanceActivity;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use Carbon\CarbonImmutable;

final readonly class CompanyAttendanceActivity implements AttendanceActivity
{
    public function atCenter(string $workCenterId, CarbonImmutable $since): CenterAttendanceActivity
    {
        // A bound date loses its offset: compare in UTC, as the columns are kept.
        $since = $since->utc();

        return new CenterAttendanceActivity(
            AttendanceCenterMode::query()->where('work_center_id', $workCenterId)->where('updated_at', '>=', $since)->exists(),
            AttendanceZone::query()->where('work_center_id', $workCenterId)->where('created_at', '>=', $since)->count(),
            KioskDevice::query()->where('work_center_id', $workCenterId)->where('created_at', '>=', $since)->whereNull('revoked_at')->count(),
            // When it reached the server, not when it was taken: an offline mark may carry an earlier hour.
            AttendanceMark::query()->where('work_center_id', $workCenterId)->where('created_at', '>=', $since)->whereNull('corrects_mark_id')->count(),
        );
    }
}
