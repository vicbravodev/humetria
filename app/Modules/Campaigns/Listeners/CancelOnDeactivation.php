<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Listeners;

use App\Modules\Campaigns\Actions\CancelParticipation;
use App\Modules\Organization\Events\EmployeeDeactivated;

final readonly class CancelOnDeactivation
{
    public function __construct(private CancelParticipation $cancelParticipation) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $this->cancelParticipation->handle($event->employeeId);
    }
}
