<?php

declare(strict_types=1);

namespace App\Modules\Leave\Listeners;

use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Log;

/**
 * When a person comes back: the days counted when they left, if RH had not settled them, no longer stand (their balance
 * was never touched). A settled termination stays as it is.
 */
final readonly class VoidTerminationOnReactivation
{
    public function __construct(private Companies $companies) {}

    public function handle(EmployeeReactivated $event): void
    {
        $this->companies->enter($event->tenantId);
        $voided = LeaveTermination::query()->where('employee_id', $event->employeeId)->whereNull('settled_at')->whereNull('voided_at')->update(['voided_at' => now()]);

        if ($voided > 0) {
            Log::info('leave.termination_voided', ['employee_id' => $event->employeeId, 'terminations' => $voided]);
        }
    }
}
