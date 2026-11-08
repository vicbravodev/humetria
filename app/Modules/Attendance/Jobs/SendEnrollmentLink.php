<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Jobs;

use App\Modules\Attendance\Actions\IssueEnrollment;
use App\Modules\Attendance\Support\DevicePages;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one person of a batch their link to link a phone. Only ids travel in the job: the link is made here, in the worker,
 * and goes straight into the email, so its token never sits in the queue, Horizon or failed_jobs. A person who left, has
 * no email by then, linked a device meanwhile or is not the company's, or a company that may not be used, gets nothing. A
 * person has at most one waiting, so a batch sent twice before the worker runs emails them once.
 */
final class SendEnrollmentLink implements ShouldBeUniqueUntilProcessing, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** Seconds a person's waiting link blocks another one. */
    public int $uniqueFor = 3600;

    public function __construct(public readonly string $tenantId, public readonly string $employeeId, public readonly string $userId) {}

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->employeeId}";
    }

    public function handle(Companies $companies, Roster $roster, IssueEnrollment $issue): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->blocked || $person === null || ! $person->active || $person->email === null || DevicePages::states([$person->id])[$person->id] === 'linked') {
            return;
        }

        try {
            $issue->handle($person->id, $this->userId);
        } catch (Throwable $failure) {
            // RH was told the batch went out: the log says whose email did not. Ids only; the job still fails.
            Log::error('attendance.enrollment_email_failed', ['tenant_id' => $this->tenantId, 'employee_id' => $person->id]);

            throw $failure;
        }
    }
}
