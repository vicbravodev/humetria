<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Listeners;

use App\Modules\Engagement\Actions\CancelInvitation;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Tenancy\Contracts\Companies;

final readonly class CancelOnDeactivation
{
    public function __construct(private Companies $companies, private CancelInvitation $cancelInvitation) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $this->companies->enter($event->tenantId);
        $this->cancelInvitation->handle($event->employeeId);
    }
}
