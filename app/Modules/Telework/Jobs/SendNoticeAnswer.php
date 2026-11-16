<?php

declare(strict_types=1);

namespace App\Modules\Telework\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Notifications\NoticeAnswered;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the person, by email, that their accident notice was answered (rediseño v2, board V3-Aviso): what was done waits in
 * their portal, never in the email. Only ids travel in the job; the portal link is made as it runs. A blocked company, a
 * person who left or one without email gets nothing.
 */
final class SendNoticeAnswer implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $tenantId, public readonly string $teleworkerId) {}

    public function handle(Companies $companies, Roster $roster, WorkerLinks $links): void
    {
        $company = $companies->enter($this->tenantId);
        $teleworker = Teleworker::query()->find($this->teleworkerId, ['id', 'employee_id']);
        $person = $company->stopped('nom037') || $teleworker === null ? null : $roster->find($teleworker->employee_id);

        if ($person !== null && $person->active && $person->email !== null) {
            Notification::route('mail', $person->email)->notify(new NoticeAnswered($links->to('portal.notices.show'), $person->name, $company));
        }
    }
}
