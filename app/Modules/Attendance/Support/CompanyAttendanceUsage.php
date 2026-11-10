<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Contracts\AttendanceUsage;
use Illuminate\Support\Facades\DB;

final class CompanyAttendanceUsage implements AttendanceUsage
{
    /** The tables whose rows say a company has put Attendance to work. */
    private const array TABLES = ['attendance_policies', 'attendance_kiosk_devices', 'attendance_devices', 'attendance_marks'];

    public function among(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $using = [];

        foreach (self::TABLES as $table) {
            $using = [...$using, ...DB::table($table)->whereIn('tenant_id', $tenantIds)->distinct()->pluck('tenant_id')->all()];
        }

        return array_values(array_filter($tenantIds, fn (string $id): bool => in_array($id, $using, true)));
    }
}
