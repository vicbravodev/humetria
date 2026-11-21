<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Listeners;

use App\Modules\Campaigns\Actions\CancelParticipation;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Tenancy\Contracts\Companies;

final readonly class CancelOnDeactivation
{
    public function __construct(private Companies $companies, private CancelParticipation $cancelParticipation) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $this->companies->enter($event->tenantId);
        $this->cancelParticipation->handle($event->employeeId);
    }
}
