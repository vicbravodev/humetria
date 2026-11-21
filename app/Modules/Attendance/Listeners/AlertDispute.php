<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Listeners;

use App\Modules\Attendance\Events\WeekDisputed;
use App\Modules\Attendance\Jobs\SendDisputeAlert;
use App\Modules\Tenancy\Contracts\CurrentTenant;

/** A disputed week stays in view of RH until resolved (criterio 5.2): RH is told by email, after the dispute commits. */
final readonly class AlertDispute
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(WeekDisputed $event): void
    {
        SendDisputeAlert::dispatch($this->tenant->id(), $event->confirmationId);
    }
}
