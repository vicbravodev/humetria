<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\WorkerAccess\Contracts\WorkerInvitations;
use App\Modules\WorkerAccess\Data\InvitationOutcome;
use Illuminate\Support\Facades\Log;

/**
 * Invites people of the current company to their portal (WorkerAccess), where «Mis vacaciones» is: this is how a person gets
 * there the first time to read and confirm their annual statement. Whoever has no email (RH invites them by hand in
 * «Accesos»), left, or is of another company gets none.
 */
final readonly class SendPortalLinks
{
    public function __construct(private Roster $roster, private WorkerInvitations $invitations) {}

    /**
     * @param  list<string>  $employeeIds
     * @param  int  $userId  who of RH asked for it
     * @return int how many emails went out
     */
    public function handle(array $employeeIds, int $userId): int
    {
        $sent = 0;

        // ponytail: sent one by one in the request; a queued job per batch if RH sends to hundreds at once.
        foreach ($this->roster->findMany($employeeIds) as $person) {
            if ($person->email !== null && $this->invitations->send($person->id, $userId)->status === InvitationOutcome::SENT) {
                $sent++;
            }
        }

        Log::info('leave.portal_links_sent', ['sent' => $sent, 'asked' => count($employeeIds), 'user_id' => $userId]);

        return $sent;
    }
}
