<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Contracts\ComplaintActivity;
use App\Modules\Complaints\Data\ComplaintCounts;
use App\Modules\Complaints\Models\Complaint;
use Carbon\CarbonImmutable;

final readonly class CompanyComplaintActivity implements ComplaintActivity
{
    public function since(CarbonImmutable $since): ComplaintCounts
    {
        $counts = Complaint::query()->toBase()->where('held', false)
            ->where('received_on', '>=', $since->setTimezone(config()->string('app.display_timezone'))->toDateString())
            ->selectRaw('count(*) as received, count(*) filter (where acknowledged_at >= ?) as acknowledged', [$since->utc()])
            ->first();

        return new ComplaintCounts(
            is_int($counts?->received) ? $counts->received : 0,
            is_int($counts?->acknowledged) ? $counts->acknowledged : 0,
        );
    }
}
