<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeImportPlan;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeAttributes;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ImportEmployees
{
    public function __construct(private CurrentTenant $currentTenant, private FindOrCreateArea $findOrCreateArea) {}

    /**
     * Writes a valid plan in one transaction: every row or none.
     *
     * @return array{created: int, updated: int}
     */
    public function handle(EmployeeImportPlan $plan): array
    {
        return DB::transaction(function () use ($plan): array {
            /** One lookup per area name, not per row. */
            $areas = [];

            foreach ($plan->rows as $row) {
                $area = $areas[mb_strtolower($row->data->area)] ??= $this->findOrCreateArea->handle($row->data->area);
                $attributes = EmployeeAttributes::from($row->data, $area);

                if ($row->existingId === null) {
                    Employee::query()->create(['tenant_id' => $this->currentTenant->id(), ...$attributes]);
                } else {
                    $employee = Employee::query()->findOrFail($row->existingId);
                    $from = $employee->work_center_id;
                    $employee->update($attributes);

                    // As moving them by hand: an open evaluation of the old work center lets them go (criterio C-24).
                    if ($employee->work_center_id !== $from) {
                        event(new EmployeeMoved($employee->tenant_id, $employee->id, $from, $employee->work_center_id));
                    }
                }
            }

            $counts = ['created' => $plan->created(), 'updated' => $plan->updated()];
            // Only the counts: the people's data stays out of logs.
            Log::info('employees.imported', $counts);

            return $counts;
        });
    }
}
