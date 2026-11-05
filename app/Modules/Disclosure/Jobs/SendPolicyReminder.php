<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Jobs;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Notifications\PolicyToConfirm;
use App\Modules\Disclosure\Support\PolicyStanding;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a person the policy in force in their center is waiting for their confirmation, with their personal link derived
 * here (the same one every email carries). Only ids travel in the job. Nothing is sent when they already confirmed it, were never
 * asked, have no email, left, or the company may not be used; a person has at most one waiting (the lock is released when
 * it starts, so a request that arrives while it runs queues the next).
 */
final class SendPolicyReminder implements ShouldBeUniqueUntilProcessing, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** Seconds the email waits, so a burst of requests (a new version, a new hire) becomes one email. */
    public const int DELAY = 120;

    /** Seconds a person's waiting request blocks another one. */
    public int $uniqueFor = 600;

    public function __construct(public readonly string $tenantId, public readonly string $employeeId) {}

    /** Queues the reminder of an employee, waiting so a burst of requests becomes one email. */
    public static function remind(string $tenantId, string $employeeId): void
    {
        self::dispatch($tenantId, $employeeId)->delay(self::DELAY);
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->employeeId}";
    }

    public function handle(Companies $companies, Roster $roster, PortalAccess $access, PolicyStanding $standing): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->blocked || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        $inForce = $standing->inForce([$person->workCenterId])[$person->workCenterId] ?? null;
        $pending = $inForce === null ? null : Acknowledgment::query()->where('document_id', $inForce->documentId)->where('employee_id', $person->id)->whereNull('acknowledged_at')->first(['id']);

        if ($pending === null) {
            return;
        }

        Notification::route('mail', $person->email)->notify(new PolicyToConfirm(route('portal.policy.show', $access->token($person->id)), $person->name, $company));
    }
}
