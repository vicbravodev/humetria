<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeAttributes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class UpdateEmployee
{
    public function __construct(private FindOrCreateArea $findOrCreateArea, private FindOrCreateJobGrade $findOrCreateJobGrade, private SetManager $setManager) {}

    public function handle(Employee $employee, EmployeeData $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $from = $employee->work_center_id;
            $employee->update([
                ...EmployeeAttributes::from($data, $this->findOrCreateArea->handle($data->area)),
                ...EmployeeAttributes::payScale($data, $this->findOrCreateJobGrade->handle($data->jobCategory, $data->jobLevel)),
            ]);

            // Someone who had no work center is placed in one, not moved.
            if ($from !== null && $data->workCenterId !== null && $data->workCenterId !== $from) {
                event(new EmployeeMoved($employee->tenant_id, $employee->id, $from, $data->workCenterId));
            }

            // sort() also renumbers the keys, so the list stays a list once updated_at is taken out.
            $changed = array_diff(array_keys($employee->getChanges()), ['updated_at']);
            sort($changed);

            if ($changed !== []) {
                // Only the names of the fields: a person's data stays out of logs.
                Log::info('employee.updated', ['employee_id' => $employee->id, 'changed' => $changed]);
            }

            // Last: it saves on its own, with its own log and audit entry.
            $this->setManager->handle($employee, $data->managerId);

            return $employee;
        });
    }
}
