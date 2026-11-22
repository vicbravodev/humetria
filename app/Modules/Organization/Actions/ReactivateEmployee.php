<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class ReactivateEmployee
{
    public function __construct(private CompanyLimits $limits, private RecordAudit $audit) {}

    /**
     * The person works here again. Their evaluations are left as they are: HR invites them back from the evaluation,
     * which gives back the guides cancelled while they were away (criterio C-24). NOM-037 asks them to acknowledge the
     * policy in force (criterio C-20), on EmployeeReactivated. They take a seat of the plan again: with none left they stay out.
     * The departure it undoes (its date and reason) stays in the audit trail. A second call for someone already back does nothing.
     *
     * @throws ValidationException
     */
    public function handle(Employee $employee): Employee
    {
        $reactivated = DB::transaction(function () use ($employee): bool {
            // The row is locked: a second submit of the same return waits here and finds them already back.
            $out = Employee::query()->whereKey($employee->id)->lockForUpdate()->sole(['id', 'deactivated_at'])->deactivated_at !== null;
            $employee->refresh();

            if (! $out) {
                return false;
            }

            $this->limits->reserve(CompanyLimits::SEATS, 1, 'seats');
            $previous = ['previous_terminated_on' => $employee->terminated_on?->toDateString(), 'previous_reason' => $employee->deactivation_reason?->value];
            $employee->update(['deactivated_at' => null, 'terminated_on' => null, 'deactivation_reason' => null]);
            $this->audit->handle('employee.reactivated', $employee, $previous);
            DB::afterCommit(fn () => Log::info('employee.reactivated', ['employee_id' => $employee->id]));

            return true;
        });

        // Only a person who works in a work center has policies to acknowledge there (a union's roster has none).
        if ($reactivated && $employee->work_center_id !== null) {
            event(new EmployeeReactivated($employee->tenant_id, $employee->id, $employee->work_center_id));
        }

        return $employee;
    }
}
