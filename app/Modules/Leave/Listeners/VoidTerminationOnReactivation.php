<?php

declare(strict_types=1);

namespace App\Modules\Leave\Listeners;

use App\Modules\Leave\Actions\RestoreVoidedPeriods;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Log;

/**
 * When a person comes back: the days counted when they left, if RH had not settled them, no longer stand, and the periods
 * the departure voided (granted after the day they left) are given back with entries of the ledger: their balance is as it
 * was before the departure. A settled termination stays as it is, with its voids.
 */
final readonly class VoidTerminationOnReactivation
{
    public function __construct(private Companies $companies, private RestoreVoidedPeriods $restore) {}

    public function handle(EmployeeReactivated $event): void
    {
        $this->companies->enter($event->tenantId);
        $voided = LeaveTermination::query()->where('employee_id', $event->employeeId)->whereNull('settled_at')->whereNull('voided_at')->update(['voided_at' => now()]);

        if ($voided > 0) {
            $this->restore->handle($event->employeeId);
            Log::info('leave.termination_voided', ['employee_id' => $event->employeeId, 'terminations' => $voided]);
        }
    }
}
