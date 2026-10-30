<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Organization\Models\Employee;
use Illuminate\Support\Facades\Log;

final class ReactivateEmployee
{
    public function handle(Employee $employee): Employee
    {
        $employee->update(['deactivated_at' => null]);

        Log::info('employee.reactivated', ['employee_id' => $employee->id]);
        event(new EmployeeReactivated($employee->tenant_id, $employee->id));

        return $employee;
    }
}
