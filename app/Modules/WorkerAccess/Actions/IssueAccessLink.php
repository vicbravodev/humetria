<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Data\InvitationOutcome;
use App\Modules\WorkerAccess\Enums\LinkKind;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * RH issues a person the link to enter and create their key (CUJ 1): good once and for 48 hours, revoking every link of theirs
 * that still opened. Emailed to the person, and then RH never gets it: RH may not create a key as them. Only when the person
 * has no email is the address returned, for RH to deliver by hand, and the audit trail says RH delivered it. The token
 * exists in clear only in the address: never in the log, the audit trail or the queue.
 */
final readonly class IssueAccessLink
{
    public function __construct(private Roster $roster, private Companies $companies, private CreateAccessLink $create, private RecordAudit $audit) {}

    public function handle(string $employeeId, int $issuedBy): InvitationOutcome
    {
        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active) {
            return new InvitationOutcome(InvitationOutcome::UNAVAILABLE);
        }

        $byRh = $person->email === null;

        [$link, $url] = DB::transaction(function () use ($person, $issuedBy, $byRh): array {
            $issued = $this->create->handle($person->id, LinkKind::Issued, $issuedBy);
            $this->audit->handle('worker_access.link_issued', $issued[0], ['employee_id' => $person->id, 'delivered_by_rh' => $byRh]);

            return $issued;
        });

        Log::info('worker_access.link_issued', ['link_id' => $link->id, 'employee_id' => $person->id, 'delivered_by_rh' => $byRh]);

        if ($person->email === null) {
            return new InvitationOutcome(InvitationOutcome::WITHOUT_EMAIL, $url);
        }

        // Sent now, never queued: the plain token must not reach the queue.
        Notification::route('mail', $person->email)->notify(new WorkerInvitation($url, $person->name, $this->companies->current()));

        return new InvitationOutcome(InvitationOutcome::SENT);
    }
}
