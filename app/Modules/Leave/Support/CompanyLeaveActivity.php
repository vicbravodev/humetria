<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Contracts\LeaveActivity;
use App\Modules\Leave\Data\LeaveCounts;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;

final readonly class CompanyLeaveActivity implements LeaveActivity
{
    public function since(CarbonImmutable $since): LeaveCounts
    {
        // A bound date loses its offset: compare in UTC, as the columns are kept.
        $counts = LeaveRequest::query()->toBase()->where('created_at', '>=', $since->utc())
            ->selectRaw('count(*) as requested, count(*) filter (where status in (?, ?)) as approved', [RequestStatus::Approved->value, RequestStatus::Taken->value])
            ->first();

        return new LeaveCounts(
            is_int($counts?->requested) ? $counts->requested : 0,
            is_int($counts?->approved) ? $counts->approved : 0,
        );
    }
}
