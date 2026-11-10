<?php

declare(strict_types=1);

namespace App\Modules\Leave\Listeners;

use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Actions\RecordTermination;
use App\Modules\Leave\Actions\RouteRequests;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\PeriodWindow;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * When a person leaves: their requests still waiting and their approved vacations that have not started are cancelled,
 * and the days go back to their balance (what they are owed is counted from it); what they already enjoyed or started
 * stays. The requests that waited for their answer go right away to the next active manager above, or to RH. Then the
 * days they are owed are counted (criterio C-07). It runs inside the transaction that deactivates them.
 */
final readonly class CloseOnDeactivation
{
    public function __construct(private Companies $companies, private CancelRequest $cancel, private RouteRequests $route, private RecordTermination $terminate) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $this->companies->enter($event->tenantId);
        $today = PeriodWindow::today()->toDateString();

        $open = LeaveRequest::query()->where('employee_id', $event->employeeId)
            ->where(fn ($query) => $query->where('status', RequestStatus::Requested)->orWhere(fn ($query) => $query->where('status', RequestStatus::Approved)->where('starts_on', '>', $today)))
            ->orderBy('starts_on')->pluck('id');

        foreach ($open as $requestId) {
            if (is_string($requestId)) {
                $this->cancel->handle($requestId, Decider::system());
            }
        }

        $this->route->reroute($event->employeeId);
        $this->terminate->handle($event->employeeId);
    }
}
