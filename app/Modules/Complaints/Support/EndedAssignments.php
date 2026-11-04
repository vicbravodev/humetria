<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Models\ComplaintAssignment;

/** Ends a person's current assignments with the reason: they stay on record, and the cases they held may be held by nobody. */
final readonly class EndedAssignments
{
    /**
     * @return int how many assignments ended.
     */
    public function of(string $tenantId, int $userId, string $reason): int
    {
        return ComplaintAssignment::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('ended_on')
            ->update(['ended_on' => Dates::today()->toDateString(), 'end_reason' => $reason]);
    }
}
