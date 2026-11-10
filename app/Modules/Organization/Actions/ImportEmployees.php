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
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ImportEmployees
{
    public function __construct(private CurrentTenant $currentTenant, private FindOrCreateArea $findOrCreateArea, private FindOrCreateJobGrade $findOrCreateJobGrade, private CompanyLimits $limits) {}

    /**
     * Writes a valid plan in one transaction: every row or none, and none when the new people do not fit in the seats the plan has left.
     *
     * @return array{created: int, updated: int}
     */
    public function handle(EmployeeImportPlan $plan): array
    {
        return DB::transaction(function () use ($plan): array {
            $this->limits->reserve(CompanyLimits::SEATS, $plan->created(), 'file');

            /** One lookup per area name and per category and level, not per row. */
            $areas = [];
            $grades = [];

            foreach ($plan->rows as $row) {
                $area = $row->data->area === null ? null : ($areas[mb_strtolower($row->data->area)] ??= $this->findOrCreateArea->handle($row->data->area));
                $attributes = [
                    ...EmployeeAttributes::from($row->data, $area, $row->absent),
                    ...EmployeeAttributes::payScale($row->data, $this->grade($row->data, $grades), $row->absent),
                ];

                if ($row->existingId === null) {
                    $created = Employee::query()->create(['tenant_id' => $this->currentTenant->id(), ...$attributes]);

                    if ($row->data->workCenterId !== null) {
                        event(new EmployeeCreated($created->tenant_id, $created->id, $row->data->workCenterId));
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
            }

            $counts = ['created' => $plan->created(), 'updated' => $plan->updated()];
            // Only the counts: the people's data stays out of logs.
            Log::info('employees.imported', $counts);

            return $counts;
        });
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
