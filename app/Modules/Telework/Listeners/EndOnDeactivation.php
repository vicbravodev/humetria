<?php

declare(strict_types=1);

namespace App\Modules\Telework\Listeners;

use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Telework\Actions\EndTelework;
use App\Modules\Telework\Enums\EndReason;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;

/** Someone who left the company no longer teleworks: their open period ends with `left_company`. Nothing open, nothing to do. */
final readonly class EndOnDeactivation
{
    public function __construct(private Companies $companies, private EndTelework $end) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $this->companies->enter($event->tenantId);
        $period = Teleworker::query()->where('employee_id', $event->employeeId)->where('status', '!=', TeleworkerStatus::Ended)->first();

        if ($period !== null) {
            $this->end->handle($period, EndReason::LeftCompany, CarbonImmutable::now(), null, null);
        }
    }
}
