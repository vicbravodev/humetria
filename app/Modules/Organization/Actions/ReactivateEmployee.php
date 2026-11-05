<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Organization\Models\Employee;
use Illuminate\Support\Facades\Log;

final class ReactivateEmployee
{
    /**
     * The person works here again. Their evaluations are left as they are: HR invites them back from the evaluation,
     * which gives back the guides cancelled while they were away (criterio C-24). NOM-037 asks them to acknowledge the
     * policy in force (criterio C-20), on EmployeeReactivated.
     */
    public function handle(Employee $employee): Employee
    {
        $employee->update(['deactivated_at' => null]);

        Log::info('employee.reactivated', ['employee_id' => $employee->id]);

        // Only a person who works in a work center has policies to acknowledge there (a union's roster has none).
        if ($employee->work_center_id !== null) {
            event(new EmployeeReactivated($employee->tenant_id, $employee->id, $employee->work_center_id));
        }

        return $employee;
    }
}
