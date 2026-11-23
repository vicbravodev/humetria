<?php

declare(strict_types=1);

namespace App\Modules\Leave\Jobs;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Notifications\RequestDecided as RequestDecidedMail;
use App\Modules\Leave\Support\RequestSummary;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells who asked, by email, what was answered, with the address of their vacations in their pages. The request is read
 * again when it runs: one cancelled since is no longer answered, and tells nobody. Whoever has no email gets none.
 */
final class RequestDecided implements ShouldQueue, ShouldQueueAfterCommit
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

        if ($request === null || ! in_array($request->status, [RequestStatus::Approved, RequestStatus::Rejected, RequestStatus::Taken], true)) {
            return;
        }

        $person = $roster->find($request->employee_id);

        if ($person !== null && $person->email !== null) {
            Notification::route('mail', $person->email)->notify(new RequestDecidedMail($links->to('portal.vacations.show'), $company, $person->name, RequestSummary::of($request), $request->status !== RequestStatus::Rejected, $request->rejection_reason));
        }
    }
}
