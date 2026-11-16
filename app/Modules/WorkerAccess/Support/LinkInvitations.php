<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Actions\IssueAccessLink;
use App\Modules\WorkerAccess\Contracts\WorkerInvitations;
use App\Modules\WorkerAccess\Data\InvitationOutcome;

final readonly class LinkInvitations implements WorkerInvitations
{
    public function __construct(private IssueAccessLink $issue) {}

    public function send(string $employeeId, int $issuedBy): InvitationOutcome
    {
        return $this->issue->handle($employeeId, $issuedBy);
    }
}
