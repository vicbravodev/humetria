<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;

/** How many open complaints nobody holds: only the count, for who manages the channel. */
final readonly class UnassignedCases
{
    public function count(): int
    {
        return Complaint::query()
            ->where('status', '<>', ComplaintStatus::Closed->value)
            ->whereNotIn('id', ComplaintAssignment::query()->whereNull('ended_on')->select('complaint_id'))
            ->count();
    }
}
