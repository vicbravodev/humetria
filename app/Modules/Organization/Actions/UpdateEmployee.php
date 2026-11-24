<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeAttributes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class UpdateEmployee
{
    /** What a person's record changes that the audit trail keeps with its before and after: where they work, their job title, area and level. */
    private const array PLACEMENT = ['work_center_id', 'job_title', 'area_id', 'job_grade_id'];

    public function __construct(private FindOrCreateArea $findOrCreateArea, private FindOrCreateJobGrade $findOrCreateJobGrade, private SetManager $setManager, private RecordAudit $audit) {}

    public function handle(Employee $employee, EmployeeData $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $from = $employee->work_center_id;
            $before = $employee->only(self::PLACEMENT);
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

            // Only the ids and the job title, never the person's name or contact: what was and what is, field by field.
            $changes = [];
            foreach ($employee->only(self::PLACEMENT) as $field => $after) {
                if ($after !== $before[$field]) {
                    $changes[$field] = ['before' => $before[$field], 'after' => $after];
                }
            }

            if ($changes !== []) {
                $this->audit->handle('employee.updated', $employee, ['employee_id' => $employee->id, 'changes' => $changes]);
            }

            // Last: it saves on its own, with its own log and audit entry.
            $this->setManager->handle($employee, $data->managerId);

            return $employee;
        });
    }
}
