<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class DeactivateEmployee
{
    public function __construct(private CompanyLimits $limits) {}

    /**
     * The person no longer works here. Nothing is deleted: evaluations that name them keep their history. What other
     * modules do about it (cancelling their pending guides) commits together with the deactivation, or not at all.
     */
    public function handle(Employee $employee): Employee
    {
        DB::transaction(function () use ($employee): void {
            $wasActive = $employee->deactivated_at === null;
            $employee->update(['deactivated_at' => now()]);

            // Their seat is free again for the plan; someone already out has none to give back.
            if ($wasActive) {
                $this->limits->release(CompanyLimits::SEATS, 1);
            }

            Log::info('employee.deactivated', ['employee_id' => $employee->id]);
            event(new EmployeeDeactivated($employee->tenant_id, $employee->id));
        });

        return $employee;
    }
}
