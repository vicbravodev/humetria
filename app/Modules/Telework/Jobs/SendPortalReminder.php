<?php

declare(strict_types=1);

namespace App\Modules\Telework\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Notifications\DocumentsToConfirm;
use App\Modules\Telework\Notifications\PortalReminder;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\PortalPending;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a person what is waiting for them. An employee gets one email listing everything pending in their portal, with a
 * fresh portal link made here (it replaces the one they had: the newest email always works); a Comisión member, the
 * documents to confirm and the link to the documents page. Only ids travel in the job. The email lists everything pending
 * when it runs, and a person has at most one waiting (the lock is released when it starts, so an item that arrives while it runs queues the next). Nothing is sent when they have nothing pending any more.
 */
final class SendPortalReminder implements ShouldBeUniqueUntilProcessing, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** Seconds the email waits, so a burst of requests (several documents, a new hire) becomes one email. */
    public const int DELAY = 120;

    /** Seconds a person's waiting request blocks another one: the one waiting lists everything pending when it runs. */
    public int $uniqueFor = 600;

    public function __construct(public readonly string $tenantId, public readonly ?string $employeeId, public readonly ?int $userId) {}

    /** Queues the reminder of an employee, waiting so a burst of pending items becomes one email. */
    public static function remind(string $tenantId, string $employeeId): void
    {
        self::dispatch($tenantId, $employeeId, null)->delay(self::DELAY);
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:".($this->employeeId ?? "user-{$this->userId}");
    }

    public function handle(Companies $companies, Roster $roster, Members $members, IssuePortalLink $issue, PortalPending $portal): void
    {
        $company = $companies->enter($this->tenantId);

        if ($company->blocked) {
            return;
        }

        if ($this->employeeId !== null) {
            $person = $roster->find($this->employeeId);

            if ($person !== null && $person->active && $person->email !== null) {
                $teleworker = Teleworker::query()->where('employee_id', $person->id)->where('status', '!=', TeleworkerStatus::Ended)->first(['id']);
                $pending = $portal->for(new OpenedPortal($company->id, $person->id, $teleworker?->id, $company->tradeName, $person->name));

                if ($pending->checklists !== [] || $pending->documents !== [] || $pending->equipment !== [] || $pending->photos !== [] || $pending->visits !== []) {
                    Notification::route('mail', $person->email)->notify(new PortalReminder(route('portal.show', $issue->handle($person->id)), $person->name, $company->tradeName, $pending));
                }
            }

            return;
        }

        $titles = $this->pendingTitles();
        $member = $this->userId === null || $titles === [] ? null : $members->find($this->userId);

        if ($member !== null) {
            Notification::route('mail', $member->email)->notify(new DocumentsToConfirm(route('tenant.telework.documents.index', $company->slug), $member->name, $company->tradeName, $titles));
        }
    }

    /**
     * The versions in force the Comisión member still has to confirm: what their email lists.
     *
     * @return list<string>
     */
    private function pendingTitles(): array
    {
        $pending = Acknowledgement::query()->whereNull('acknowledged_at')
            ->where('user_id', $this->userId)
            ->select('document_id');

        return array_values(TeleworkDocument::query()->inForce()->whereIn('id', $pending)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (TeleworkDocument $document): string => "{$document->kind->label()} · versión {$document->version}")->all());
    }
}
