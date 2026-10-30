<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Listeners;

use App\Modules\Campaigns\Actions\CancelParticipation;
use App\Modules\Organization\Events\EmployeeMoved;

/** Someone who moved no longer works in the work center their old evaluations apply to. */
final readonly class CancelOnMove
{
    public function __construct(private CancelParticipation $cancelParticipation) {}

    public function handle(EmployeeMoved $event): void
    {
        $this->cancelParticipation->handle($event->employeeId, $event->fromWorkCenterId);
    }
}
