<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Models\AttendancePolicy;

/**
 * The current company's policy (criterios 4.1 to 4.4 and 7.1), or, while RH has not saved one, an unsaved policy with the
 * defaults the table writes (its migration's column defaults: a test keeps them equal).
 */
final class CompanyPolicy
{
    public static function current(): AttendancePolicy
    {
        return AttendancePolicy::query()->first() ?? new AttendancePolicy([
            'out_of_zone' => OutOfZoneRule::Block,
            'max_accuracy_m' => 100,
            'require_qr_onsite' => true,
            'trusted_may_use_browser' => true,
            'retention_months' => 12,
            'late_tolerance_minutes' => 10,
        ]);
    }
}
