<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Organization\Models\Employee;

final class CompanySeniority implements Seniority
{
    public function startDatesFor(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $dates = [];

        foreach (Employee::query()->among('id', $employeeIds)->get(['id', 'seniority_on', 'hired_on']) as $employee) {
            $since = $employee->seniority_on ?? $employee->hired_on;

            if ($since !== null) {
                $dates[$employee->id] = $since;
            }
        }

        return $dates;
    }
}
