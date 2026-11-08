<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Jobs;

use App\Modules\Attendance\Actions\IssueEnrollment;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one person of a batch their link to link a phone. Only ids travel in the job: the link is made here, in the worker,
 * and goes straight into the email, so its token never sits in the queue, Horizon or failed_jobs. A person who left, has
 * no email by then or is not the company's, or a company that may not be used, gets nothing.
 */
final class SendEnrollmentLink implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly string $tenantId, public readonly string $employeeId, public readonly string $userId) {}

    public function handle(Companies $companies, Roster $roster, IssueEnrollment $issue): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->blocked || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        $issue->handle($person->id, $this->userId);
    }
}
