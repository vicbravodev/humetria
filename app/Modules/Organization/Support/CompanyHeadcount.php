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
}
