<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Notifications\RequestDecided;
use App\Modules\Leave\Notifications\RequestEscalated;
use App\Modules\Leave\Notifications\RequestToDecide;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Closure;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The emails of a request of the current company, by mail only. They are sent after the request is saved: one that
 * cannot be sent is reported and the request stays, since it is in the portal and in the panel anyway. Whoever has no
 * email gets none. Portal links are made here and never logged.
 */
final readonly class RequestNotifier
{
    public function __construct(private Companies $companies, private Roster $roster, private PortalAccess $portal) {}

    /**
     * Tells the manager whose it is to answer, with the link of their own portal.
     */
    public function toDecide(LeaveRequest $request): void
    {
        self::quietly(function () use ($request): void {
            $people = $this->roster->findMany(array_values(array_filter([$request->employee_id, $request->approver_employee_id])));
            $manager = $people[$request->approver_employee_id ?? ''] ?? null;

            if ($manager !== null && $manager->email !== null) {
                // ponytail: the path of the team page of the portal; route('portal.vacations.team.show') once that page names it.
                $url = url("portal/{$this->portal->token($manager->id)}/vacations/team");
                Notification::route('mail', $manager->email)->notify(new RequestToDecide($url, $this->companies->current(), $manager->name, ($people[$request->employee_id] ?? $manager)->name, RequestSummary::of($request)));
            }
        });
    }

    /**
     * Tells the owner a request reached RH: the same recipient as the digests of the other tools.
     */
    public function escalated(LeaveRequest $request): void
    {
        self::quietly(function () use ($request): void {
            $owner = $this->companies->contact();
            $person = $this->roster->find($request->employee_id);

            if ($owner !== null && $person !== null && $request->escalation_reason !== null) {
                $company = $this->companies->current();
                // ponytail: the path of RH's queue; route('tenant.vacations.requests.index') once that page names it.
                Notification::route('mail', $owner)->notify(new RequestEscalated(url("admin/{$company->slug}/vacations/requests"), $company, $person->name, $request->escalation_reason, RequestSummary::of($request)));
            }
        });
    }

    /**
     * Tells who asked what was answered, with the link of their own portal.
     */
    public function decided(LeaveRequest $request): void
    {
        self::quietly(function () use ($request): void {
            $person = $this->roster->find($request->employee_id);

            if ($person !== null && $person->email !== null) {
                // ponytail: the path of «Mis vacaciones» in the portal; route('portal.vacations.show') once that page names it.
                $url = url("portal/{$this->portal->token($person->id)}/vacations");
                Notification::route('mail', $person->email)->notify(new RequestDecided($url, $this->companies->current(), $person->name, RequestSummary::of($request), $request->status === RequestStatus::Approved, $request->rejection_reason));
            }
        });
    }

    /**
     * @param  Closure(): void  $send
     */
    private static function quietly(Closure $send): void
    {
        try {
            $send();
        } catch (Throwable $failure) {
            report($failure);
        }
    }
}
