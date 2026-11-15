<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;

/**
 * Whether the people of a center can mark in it: it has a zone and, when the policy asks for the QR on site, a screen in
 * use. The one reading, for the links RH sends and for what the person is told once their phone is linked.
 */
final class CenterReadiness
{
    public static function of(string $workCenterId): bool
    {
        return AttendanceZone::query()->where('work_center_id', $workCenterId)->exists()
            && (! CompanyPolicy::current()->require_qr_onsite || KioskDevice::inUseIn($workCenterId) > 0);
    }
}
