<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Organization\Models\Employee;

final class CompanyHeadcount implements Headcount
{
    public function byWorkCenter(): array
    {
        $counts = [];

        foreach (Employee::query()->whereNull('deactivated_at')->groupBy('work_center_id')->selectRaw('work_center_id, count(*) as employees')->toBase()->get() as $row) {
            if (is_string($row->work_center_id) && is_int($row->employees)) {
                $counts[$row->work_center_id] = $row->employees;
            }
        }

        return $counts;
    }

    public function ofCompany(string $tenantId): int
    {
        return Employee::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deactivated_at')->count();
    }

    public function areasByWorkCenter(array $workCenterIds): array
    {
        $areas = [];
        $rows = Employee::query()
            ->whereNull('deactivated_at')
            ->whereIn('work_center_id', $workCenterIds)
            ->join('areas', 'areas.id', '=', 'employees.area_id')
            ->groupBy('employees.work_center_id', 'areas.id', 'areas.name')
            ->selectRaw('employees.work_center_id, areas.name, count(*) as employees')
            ->orderByDesc('employees')
            ->orderBy('areas.name')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->work_center_id) && is_string($row->name) && is_int($row->employees)) {
                $areas[$row->work_center_id][] = ['name' => $row->name, 'employees' => $row->employees];
            }
        }

        return $areas;
    }
}
