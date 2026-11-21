<?php

declare(strict_types=1);

namespace App\Modules\Leave\Jobs;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Notifications\RequestCancelled as RequestCancelledMail;
use App\Modules\Leave\Support\RequestSummary;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the manager, by email, that the person cancelled a request that had been approved. The request is read again when
 * it runs: one that is not cancelled, or that never had a manager (RH answered it), tells nobody. Whoever has no email
 * gets none.
 */
final class RequestCancelled implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $tenantId, public readonly string $requestId) {}

    public function handle(Companies $companies, Roster $roster, WorkerLinks $links): void
    {
        $company = $companies->enter($this->tenantId);
        $request = LeaveRequest::query()->find($this->requestId);

        if ($request === null || $request->status !== RequestStatus::Cancelled || $request->approver_employee_id === null) {
            return;
        }

        $people = $roster->findMany([$request->employee_id, $request->approver_employee_id]);
        $manager = $people[$request->approver_employee_id] ?? null;
        $person = $people[$request->employee_id] ?? null;

        if ($manager !== null && $manager->active && $manager->email !== null && $person !== null) {
            Notification::route('mail', $manager->email)->notify(new RequestCancelledMail($links->to('portal.vacations.team.show'), $company, $manager->name, $person->name, RequestSummary::of($request)));
        }
    }
}
