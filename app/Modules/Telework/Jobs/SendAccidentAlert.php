<?php

declare(strict_types=1);

namespace App\Modules\Telework\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Notifications\AccidentReported;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Emails RH (the company's contact, as the daily digest) about an accident notice (rediseño v2, docs/02, «Aviso de
 * accidente»): at once when it arrives, or as the reminder of one nobody answered. Only ids travel in the job. A blocked
 * company, or one without a contact, gets nothing.
 */
final class SendAccidentAlert implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly string $tenantId, public readonly string $noticeId, public readonly bool $reminder = false) {}

    public function handle(Companies $companies, Roster $roster, WorkCenters $workCenters): void
    {
        $company = $companies->enter($this->tenantId);
        $contact = $company->blocked ? null : $companies->contact();
        $notice = WorkerNotice::query()->find($this->noticeId, ['id', 'teleworker_id', 'occurred_on']);
        $teleworker = $notice === null ? null : Teleworker::query()->find($notice->teleworker_id, ['id', 'employee_id', 'work_center_id']);

        if ($contact === null || $notice === null || $teleworker === null) {
            return;
        }

        $person = $roster->find($teleworker->employee_id);
        $center = $workCenters->findActive($teleworker->work_center_id);

        Notification::route('mail', $contact)->notify(new AccidentReported(
            $company,
            $person === null ? 'Una persona' : $person->name,
            $center === null ? '' : $center->name,
            str_replace('.', '', $notice->occurred_on->isoFormat('D MMM')),
            route('tenant.telework.notices.show', [$company->slug, $notice->id]),
            $this->reminder,
        ));
    }
}
