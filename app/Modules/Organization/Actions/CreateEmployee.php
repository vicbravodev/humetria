<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeAttributes;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class CreateEmployee
{
    public function __construct(private CurrentTenant $currentTenant, private FindOrCreateArea $findOrCreateArea, private FindOrCreateJobGrade $findOrCreateJobGrade, private CompanyLimits $limits) {}

    /**
     * @throws ValidationException when the plan has no seat left.
     */
    public function handle(EmployeeData $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            // The seat is taken in the same transaction as the person: two requests for the last one cannot both pass.
            $this->limits->reserve(CompanyLimits::SEATS, 1, 'seats');

            $employee = Employee::query()->create([
                'tenant_id' => $this->currentTenant->id(),
                ...EmployeeAttributes::from($data, $this->findOrCreateArea->handle($data->area)),
                ...EmployeeAttributes::payScale($data, $this->findOrCreateJobGrade->handle($data->jobCategory, $data->jobLevel)),
            ]);

            // Only the id: a person's name and contact data stay out of logs.
            Log::info('employee.created', ['employee_id' => $employee->id]);

            // A union's people have no work center, which the evaluations need to count them.
            if ($data->workCenterId !== null) {
                event(new EmployeeCreated($employee->tenant_id, $employee->id, $data->workCenterId));
            }

            return $employee;
        });
    }
}
