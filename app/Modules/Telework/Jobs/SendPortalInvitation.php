<?php

declare(strict_types=1);

namespace App\Modules\Telework\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Notifications\PortalInvitation;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Gives a person their portal link and emails it. Only ids travel in the job: the link is made here and goes straight
 * into the email, so the plain token never sits in the queue, Horizon or failed_jobs. It is dispatched only once the surrounding transaction commits.
 */
final class SendPortalInvitation implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $tenantId, public readonly string $employeeId) {}

    public function handle(Companies $companies, Roster $roster, IssuePortalLink $issue): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->stopped('nom037') || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        Notification::route('mail', $person->email)->notify(new PortalInvitation($issue->handle($person->id), $person->name, $company));
    }
}
