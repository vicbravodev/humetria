<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Listeners;

use App\Modules\Engagement\Actions\CancelInvitation;
use App\Modules\Organization\Events\EmployeeDeactivated;

final readonly class CancelOnDeactivation
{
    public function __construct(private CancelInvitation $cancelInvitation) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $this->cancelInvitation->handle($event->employeeId);
    }
}
