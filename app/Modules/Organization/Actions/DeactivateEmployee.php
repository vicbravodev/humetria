<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

final readonly class DeactivateEmployee
{
    public function __construct(private SetManager $setManager, private ReportingLines $lines, private CompanyLimits $limits) {}

    /**
     * The person no longer works here. Nothing is deleted: evaluations that name them keep their history. What other
     * modules do about it (cancelling their pending guides) commits together with the deactivation, or not at all.
     *
     * Their active direct reports go to `$reassignTo` first, so nobody is left under someone who left. Their own
     * manager is not touched: reactivated, they are back in their place.
     *
     * @param  string|null  $reassignTo  an active person of the company, already checked by whoever calls.
     */
    public function handle(Employee $employee, ?string $reassignTo = null): Employee
    {
        DB::transaction(function () use ($employee, $reassignTo): void {
            $wasActive = $employee->deactivated_at === null;
            $this->reassign($employee, $reassignTo);
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

    private function reassign(Employee $employee, ?string $reassignTo): void
    {
        $reports = $employee->reports()->whereNull('deactivated_at')->get();

        if ($reports->isEmpty()) {
            return;
        }

        if ($reassignTo === null) {
            throw new LogicException('Somebody with direct reports is not deactivated without saying whom they will report to.');
        }

        // Whoever is below the person goes up to their place first: the others cannot hang from someone under them.
        if (in_array($reassignTo, $this->lines->subtreeIds($employee->id), true)) {
            $this->setManager->handle(Employee::query()->findOrFail($reassignTo), $employee->manager_id);
        }

        foreach ($reports as $report) {
            if ($report->id !== $reassignTo) {
                $this->setManager->handle($report, $reassignTo);
            }
        }
    }
}
