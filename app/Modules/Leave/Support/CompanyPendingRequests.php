<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Contracts\PendingRequests;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;

/** The requests RH has to answer (the ones of «Por atender»: escalated, not those still with the manager): one query. */
final readonly class CompanyPendingRequests implements PendingRequests
{
    public function waiting(): int
    {
        return LeaveRequest::query()->where('status', RequestStatus::Requested)->whereNotNull('escalated_at')->count();
    }
}
