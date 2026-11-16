<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Notifications\RequestDecided;
use App\Modules\Leave\Notifications\RequestEscalated;
use App\Modules\Leave\Notifications\RequestToDecide;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The emails of a request of the current company, by mail only. They are sent after the request is saved: one that
 * cannot be sent is reported and the request stays, since it is in the portal and in the panel anyway. Whoever has no
 * email gets none. The addresses of the person's pages carry no credential: they enter first.
 */
final readonly class RequestNotifier
{
    public function __construct(private Companies $companies, private Roster $roster, private WorkerLinks $links) {}

    /**
     * Tells the manager whose it is to answer, with the address of their team's requests in their pages.
     */
    public function toDecide(LeaveRequest $request): void
    {
        self::quietly(function () use ($request): void {
            $people = $this->roster->findMany(array_values(array_filter([$request->employee_id, $request->approver_employee_id])));
            $manager = $people[$request->approver_employee_id ?? ''] ?? null;

            if ($manager !== null && $manager->email !== null) {
                $url = $this->links->to('portal.vacations.team.show');
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
                Notification::route('mail', $owner)->notify(new RequestEscalated(route('tenant.vacations.requests.index', $company->slug), $company, $person->name, $request->escalation_reason, RequestSummary::of($request)));
            }
        });
    }

    /**
     * Tells who asked what was answered, with the address of their vacations in their pages.
     */
    public function decided(LeaveRequest $request): void
    {
        self::quietly(function () use ($request): void {
            $person = $this->roster->find($request->employee_id);

            if ($person !== null && $person->email !== null) {
                $url = $this->links->to('portal.vacations.show');
                Notification::route('mail', $person->email)->notify(new RequestDecided($url, $this->companies->current(), $person->name, RequestSummary::of($request), $request->status === RequestStatus::Approved, $request->rejection_reason));
            }
        });
    }

    /**
     * @param  Closure(): void  $send
     */
    private static function quietly(Closure $send): void
    {
        // Once what it tells about is saved for good; right away when no transaction is open.
        DB::afterCommit(function () use ($send): void {
            try {
                $send();
            } catch (Throwable $failure) {
                report($failure);
            }
        });
    }
}
