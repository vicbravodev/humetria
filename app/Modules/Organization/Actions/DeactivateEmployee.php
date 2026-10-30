<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Employee;
use Illuminate\Support\Facades\Log;

final class DeactivateEmployee
{
    /** The person no longer works here. Nothing is deleted: evaluations that name them keep their history. */
    public function handle(Employee $employee): Employee
    {
        $employee->update(['deactivated_at' => now()]);

        Log::info('employee.deactivated', ['employee_id' => $employee->id]);

        return $employee;
    }
}
