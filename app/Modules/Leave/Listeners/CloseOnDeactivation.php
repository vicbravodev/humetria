<?php

declare(strict_types=1);

namespace App\Modules\Leave\Listeners;

use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Actions\RecordTermination;
use App\Modules\Leave\Actions\RouteRequests;
use App\Modules\Leave\Actions\TrimRequestsAfter;
use App\Modules\Leave\Actions\VoidLaterPeriods;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\Decider;
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
    public function __construct(private Companies $companies, private CancelRequest $cancel, private RouteRequests $route, private TrimRequestsAfter $trim, private VoidLaterPeriods $void, private RecordTermination $terminate) {}

    public function handle(EmployeeDeactivated $event): void
    {
        $this->companies->enter($event->tenantId);
        // What starts after the day they left is not theirs to enjoy, even if that day is already behind us.
        $cutoff = $event->effectiveOn->toDateString();

        $open = LeaveRequest::query()->where('employee_id', $event->employeeId)
            ->where(fn ($query) => $query->where('status', RequestStatus::Requested)->orWhere(fn ($query) => $query->whereIn('status', [RequestStatus::Approved, RequestStatus::Taken])->where('starts_on', '>', $cutoff)))
            ->orderBy('starts_on')->pluck('id');

        foreach ($open as $requestId) {
            if (is_string($requestId)) {
                $this->cancel->handle($requestId, Decider::system());
            }
        }

        // What began before they left and goes past it keeps what was worked until then.
        $this->trim->handle($event->employeeId, $event->effectiveOn);
        $this->route->reroute($event->employeeId);
        // What was granted after the day they left was never theirs: out of the days they are owed.
        $this->void->handle($event->employeeId, $event->effectiveOn);
        $this->terminate->handle($event->employeeId, $event->effectiveOn);
    }
}
