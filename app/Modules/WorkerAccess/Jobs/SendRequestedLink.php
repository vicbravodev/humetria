<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Actions\CreateAccessLink;
use App\Modules\WorkerAccess\Enums\LinkKind;
use App\Modules\WorkerAccess\Notifications\AccessLinkRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Emails a person the link they asked for. Only ids travel in the job: the link is made here, in the worker, and goes straight
 * into the email, so its token never sits in the queue, Horizon or failed_jobs. A person who left or has no email by then, or a
 * blocked company, gets nothing. A retry makes a new link, which revokes the one before.
 */
final class SendRequestedLink implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $tenantId, public readonly string $employeeId) {}

    public function handle(Companies $companies, Roster $roster, CreateAccessLink $create): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->blocked || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        [$link, $url] = $create->handle($person->id, LinkKind::Requested);
        Notification::route('mail', $person->email)->notify(new AccessLinkRequested($url, $person->name, $company));
        Log::info('worker_access.link_sent', ['link_id' => $link->id, 'employee_id' => $person->id]);
    }
}
