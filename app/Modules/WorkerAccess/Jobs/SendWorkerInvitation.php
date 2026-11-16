<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Actions\IssueAccessLink;
use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Invites one person of a center's batch. Only ids travel in the job: the link is made here, in the worker, and goes straight
 * into the email, so its token never sits in the queue, Horizon or failed_jobs. A person who left, has no email by then,
 * created a key or got a link that still opens meanwhile, or is not the company's, or a blocked company, gets nothing. A
 * person has at most one waiting, so a batch sent twice before the worker runs emails them once. It runs once: the link is
 * kept before the email goes, so a retry would find it and skip.
 */
final class SendWorkerInvitation implements ShouldBeUniqueUntilProcessing, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** Seconds a person's waiting invitation blocks another one. */
    public int $uniqueFor = 3600;

    public function __construct(public readonly string $tenantId, public readonly string $employeeId, public readonly int $userId) {}

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->employeeId}";
    }

    public function handle(Companies $companies, Roster $roster, WorkerCredentials $credentials, IssueAccessLink $issue): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->blocked || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        $reason = match (true) {
            ($credentials->countsByEmployee([$person->id])[$person->id]->active ?? 0) > 0 => 'has_key',
            (WorkerAccessLink::issuedWaiting([$person->id])[$person->id] ?? null)?->open() === true => 'has_link',
            default => null,
        };

        if ($reason !== null) {
            Log::info('worker_access.invitation_skipped', ['tenant_id' => $this->tenantId, 'employee_id' => $person->id, 'reason' => $reason]);

            return;
        }

        try {
            $issue->handle($person->id, $this->userId);
        } catch (Throwable $failure) {
            // RH was told the batch went out: the log says whose email did not. Ids only; the job still fails.
            Log::error('worker_access.invitation_email_failed', ['tenant_id' => $this->tenantId, 'employee_id' => $person->id]);

            throw $failure;
        }
    }
}
