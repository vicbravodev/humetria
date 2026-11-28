<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Jobs;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Notifications\PolicyToConfirm;
use App\Modules\Disclosure\Support\PolicyStanding;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a person the policy in force in their center is waiting for their confirmation, with the address of their policy
 * page (no credential: they enter first). Only ids travel in the job. Nothing is sent when they already confirmed it, were never
 * asked, have no email, left, or the company may not be used; a person has at most one waiting (the lock is released when
 * it starts, so a request that arrives while it runs queues the next).
 */
final class SendPolicyReminder implements ShouldBeUniqueUntilProcessing, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300]; // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveArrayItem

    /** Seconds the email waits, so a burst of requests (a new version, a new hire) becomes one email. */
    public const int DELAY = 120; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    /** Seconds a person's waiting request blocks another one. */
    public int $uniqueFor = 600; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

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

    public function handle(Companies $companies, Roster $roster, WorkerLinks $links, PolicyStanding $standing): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->stopped('nom035') || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        $inForce = $standing->inForce([$person->workCenterId])[$person->workCenterId] ?? null;
        $pending = $inForce === null ? null : Acknowledgment::query()->where('document_id', $inForce->documentId)->where('employee_id', $person->id)->whereNull('acknowledged_at')->first(['id']);

        if ($pending === null) {
            return;
        }

        Notification::route('mail', $person->email)->notify(new PolicyToConfirm($links->to('portal.policy.show'), $person->name, $company));
    }
}
