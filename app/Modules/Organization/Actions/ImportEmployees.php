<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Data\EmployeeImportPlan;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Organization\Support\EmployeeAttributes;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ImportEmployees
{
    public function __construct(private CurrentTenant $currentTenant, private FindOrCreateArea $findOrCreateArea, private FindOrCreateJobGrade $findOrCreateJobGrade, private SetManager $setManager) {}

    /**
     * Writes a valid plan in one transaction: every row or none. The managers go last, once everybody the file names
     * is there.
     *
     * @return array{created: int, updated: int}
     */
    public function handle(EmployeeImportPlan $plan): array
    {
        return DB::transaction(function () use ($plan): array {
            /** One lookup per area name and per category and level, not per row. */
            $areas = [];
            $grades = [];
            $employees = [];

            foreach ($plan->rows as $place => $row) {
                $area = $row->data->area === null ? null : ($areas[mb_strtolower($row->data->area)] ??= $this->findOrCreateArea->handle($row->data->area));
                $attributes = [
                    ...EmployeeAttributes::from($row->data, $area, $row->absent),
                    ...EmployeeAttributes::payScale($row->data, $this->grade($row->data, $grades), $row->absent),
                ];

                if ($row->existingId === null) {
                    $employee = Employee::query()->create(['tenant_id' => $this->currentTenant->id(), ...$attributes]);

                    if ($row->data->workCenterId !== null) {
                        event(new EmployeeCreated($employee->tenant_id, $employee->id, $row->data->workCenterId));
                    }
                } else {
                    $employee = Employee::query()->findOrFail($row->existingId);
                    $from = $employee->work_center_id;
                    $employee->update($attributes);

                    // As moving them by hand: an open evaluation of the old work center lets them go (criterio C-24).
                    if ($from !== null && $row->data->workCenterId !== null && $row->data->workCenterId !== $from) {
                        event(new EmployeeMoved($employee->tenant_id, $employee->id, $from, $row->data->workCenterId));
                    }
                }

                $employees[$place] = $employee;
            }

            $counts = ['created' => $plan->created(), 'updated' => $plan->updated()];
            $managers = $this->managers($plan, $employees);
            // Only the counts: the people's data stays out of logs.
            Log::info('employees.imported', [...$counts, ...($managers === null ? [] : ['managers_changed' => $managers])]);

            return $counts;
        });
    }

    /**
     * Puts each row's person under the manager the file gave them.
     *
     * @param  array<int, Employee>  $employees  the person of each row, by its place in the plan
     * @return int|null how many changed manager; null when the file says nothing of managers.
     */
    private function managers(EmployeeImportPlan $plan, array $employees): ?int
    {
        $managers = [];

        foreach ($plan->rows as $place => $row) {
            if (! in_array('manager_id', $row->absent, true) && isset($employees[$place])) {
                $managers[$place] = $row->managerId ?? ($row->managerRow === null ? null : $employees[$row->managerRow]->id ?? null);
            }
        }

        if ($managers === []) {
            return null;
        }

        $changing = array_filter($employees, fn (Employee $employee, int $place): bool => array_key_exists($place, $managers) && $employee->manager_id !== null && $employee->manager_id !== $managers[$place], ARRAY_FILTER_USE_BOTH);

        // Everybody who moves lets go first, in the database only: two people who swap places would otherwise close
        // a cycle halfway, and each model still knows whom they reported to, for the audit trail.
        if ($changing !== []) {
            Employee::query()->among('id', array_map(fn (Employee $employee): string => $employee->id, $changing))->toBase()->update(['manager_id' => null]);
        }

        $changed = 0;

        foreach ($managers as $place => $managerId) {
            $changed += (int) $this->setManager->handle($employees[$place], $managerId);
        }

        return $changed;
    }

    /**
     * The row's job grade, looked up once per category and level.
     *
     * @param  array<string, JobGrade|null>  $grades
     */
    private function grade(EmployeeData $data, array &$grades): ?JobGrade
    {
        if ($data->jobCategory === null || $data->jobLevel === null) {
            return null;
        }

        return $grades[mb_strtolower($data->jobCategory).'|'.mb_strtolower($data->jobLevel)] ??= $this->findOrCreateJobGrade->handle($data->jobCategory, $data->jobLevel);
    }
}
