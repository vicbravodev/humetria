<?php

declare(strict_types=1);

namespace App\Modules\Leave\Listeners;

use App\Modules\Leave\Actions\GrantEntitlements;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Log;

/**
 * When a person is added (or comes back) with seniority, the periods of the anniversaries they already completed are granted
 * right away, not at the next 00:30 run, which would then find them done. It runs inside the company of the event.
 */
final readonly class GrantOnHire
{
    public function __construct(private Companies $companies, private GrantEntitlements $grant) {}

    public function handle(EmployeeCreated|EmployeeReactivated $event): void
    {
        if ($this->companies->enter($event->tenantId)->stopped('vacations')) {
            return;
        }

        $granted = $this->grant->grantFor($event->employeeId);

        if ($granted > 0) {
            Log::info('leave.entitlements_granted_on_hire', ['employee_id' => $event->employeeId, 'granted' => $granted]);
        }
    }
}
