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

        $query = DB::table(self::TABLES[0])->select('tenant_id')->whereIn('tenant_id', $tenantIds);

        foreach (array_slice(self::TABLES, 1) as $table) {
            $query->union(DB::table($table)->select('tenant_id')->whereIn('tenant_id', $tenantIds));
        }

        $using = $query->pluck('tenant_id')->all();

        return array_values(array_filter($tenantIds, fn (string $id): bool => in_array($id, $using, true)));
    }
}
