<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeAttributes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class UpdateEmployee
{
    public function __construct(private FindOrCreateArea $findOrCreateArea) {}

    public function handle(Employee $employee, EmployeeData $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $employee->update(EmployeeAttributes::from($data, $this->findOrCreateArea->handle($data->area)));

            // sort() also renumbers the keys, so the list stays a list once updated_at is taken out.
            $changed = array_diff(array_keys($employee->getChanges()), ['updated_at']);
            sort($changed);

            if ($changed !== []) {
                // Only the names of the fields: a person's data stays out of logs.
                Log::info('employee.updated', ['employee_id' => $employee->id, 'changed' => $changed]);
            }

            return $employee;
        });
    }
}
