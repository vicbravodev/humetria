<?php

declare(strict_types=1);

namespace App\Modules\Leave\Jobs;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Notifications\RequestToDecide as RequestToDecideMail;
use App\Modules\Leave\Support\RequestSummary;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a manager, by email, that a request is theirs to answer. The request is read again when it runs: one that was
 * answered, cancelled or given to another manager since is no longer theirs, and tells nobody; one that reached RH still
 * is: the manager keeps it until it is answered. Only ids
 * travel in the job; whoever has no email gets none, and the person's pages are the same either way.
 */
final class RequestToDecide implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $tenantId, public readonly string $requestId, public readonly string $managerId) {}

    public function handle(Companies $companies, Roster $roster, WorkerLinks $links): void
    {
        $company = $companies->enter($this->tenantId);
        $request = LeaveRequest::query()->find($this->requestId);

        if ($request === null || $request->status !== RequestStatus::Requested || $request->approver_employee_id !== $this->managerId) {
            return;
        }

        $people = $roster->findMany([$request->employee_id, $this->managerId]);
        $manager = $people[$this->managerId] ?? null;

        if ($manager !== null && $manager->active && $manager->email !== null) {
            Notification::route('mail', $manager->email)->notify(new RequestToDecideMail($links->to('portal.vacations.team.show'), $company, $manager->name, ($people[$request->employee_id] ?? $manager)->name, RequestSummary::of($request)));
        }
    }
}
