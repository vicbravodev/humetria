<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\InvitationOutcome;

/**
 * RH invites a person of the current company to their portal: a link good once for 48 hours that lets them create their
 * key. It revokes every link of theirs that still opened.
 */
interface WorkerInvitations
{
    /** `$issuedBy` is the RH user who invites. */
    public function send(string $employeeId, int $issuedBy): InvitationOutcome;
}
