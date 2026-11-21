<?php

declare(strict_types=1);

namespace App\Modules\Leave\Listeners;

use App\Modules\Leave\Actions\RouteRequests;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Events\ManagerChanged;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * When a person's manager changes, what waited for the previous one waits for the new one right away, not at the next
 * daily run. It runs inside the company of the event, and only if it uses vacations. A previous manager who left is not
 * this: that is what the departure of a person does (CloseOnDeactivation), which leaves with RH what already reached it.
 */
final readonly class ReassignOnManagerChange
{
    public function __construct(private Companies $companies, private Roster $roster, private RouteRequests $route) {}

    public function handle(ManagerChanged $event): void
    {
        if ($event->fromManagerId === null || $this->companies->enter($event->tenantId)->stopped('vacations') || ! ($this->roster->find($event->fromManagerId)->active ?? false)) {
            return;
        }

        $this->route->reassign($event->employeeId, $event->fromManagerId);
    }
}
