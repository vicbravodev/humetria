<?php

declare(strict_types=1);

namespace App\Modules\Telework\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Notifications\DocumentsToConfirm;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a person they have documents to confirm. An employee gets a fresh portal link made here (it replaces the one they
 * had: the newest email always works); a Comisión member, the link to the documents page. Only ids travel in the job.
 * Nothing is sent when they have nothing pending any more.
 */
final class SendAcknowledgementRequest implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly string $tenantId, public readonly ?string $employeeId, public readonly ?int $userId) {}

    public function handle(Companies $companies, Roster $roster, Members $members, IssuePortalLink $issue): void
    {
        $company = $companies->enter($this->tenantId);

        if ($company->blocked) {
            return;
        }

        $pending = Acknowledgement::query()->whereNull('acknowledged_at')
            ->when($this->employeeId !== null, fn ($query) => $query->where('employee_id', $this->employeeId))
            ->when($this->userId !== null, fn ($query) => $query->where('user_id', $this->userId))
            ->exists();

        if (! $pending) {
            return;
        }

        if ($this->employeeId !== null) {
            $person = $roster->find($this->employeeId);

            if ($person !== null && $person->active && $person->email !== null) {
                Notification::route('mail', $person->email)->notify(new DocumentsToConfirm(route('portal.show', $issue->handle($person->id)), $person->name, $company->tradeName));
            }

            return;
        }

        $member = $this->userId === null ? null : $members->find($this->userId);

        if ($member !== null) {
            Notification::route('mail', $member->email)->notify(new DocumentsToConfirm(route('tenant.telework.documents.index', $company->slug), $member->name, $company->tradeName));
        }
    }
}
