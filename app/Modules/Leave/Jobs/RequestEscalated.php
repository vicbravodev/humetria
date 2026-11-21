<?php

declare(strict_types=1);

namespace App\Modules\Leave\Jobs;

use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Notifications\RequestEscalated as RequestEscalatedMail;
use App\Modules\Leave\Support\RequestSummary;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the owner, by email, that a request reached RH: the same recipient as the digests of the other tools. The request
 * is read again when it runs: one that was answered or cancelled since, or that a manager has again, tells nobody.
 */
final class RequestEscalated implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $tenantId, public readonly string $requestId) {}

    public function handle(Companies $companies, Roster $roster): void
    {
        $company = $companies->enter($this->tenantId);
        $request = LeaveRequest::query()->find($this->requestId);

        if ($request === null || $request->status !== RequestStatus::Requested || $request->escalated_at === null || $request->escalation_reason === null) {
            return;
        }

        $owner = $companies->contact();
        $person = $roster->find($request->employee_id);

        if ($owner !== null && $person !== null) {
            Notification::route('mail', $owner)->notify(new RequestEscalatedMail(route('tenant.vacations.requests.index', $company->slug), $company, $person->name, $request->escalation_reason, RequestSummary::of($request)));
        }
    }
}
