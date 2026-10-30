<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\EmployeeAttributes;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class CreateEmployee
{
    public function __construct(private CurrentTenant $currentTenant, private FindOrCreateArea $findOrCreateArea) {}

    public function handle(EmployeeData $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            $employee = Employee::query()->create([
                'tenant_id' => $this->currentTenant->id(),
                ...EmployeeAttributes::from($data, $this->findOrCreateArea->handle($data->area)),
            ]);

            // Only the id: a person's name and contact data stay out of logs.
            Log::info('employee.created', ['employee_id' => $employee->id]);

            return $employee;
        });
    }
}
