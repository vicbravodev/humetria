<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Contracts\OpenCampaigns;
use App\Modules\Campaigns\Data\CampaignsInCourse;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;

final class CompanyOpenCampaigns implements OpenCampaigns
{
    public function existFor(string $workCenterId): bool
    {
        return Campaign::query()
            ->where('work_center_id', $workCenterId)
            ->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Active])
            ->exists();
    }

    public function inCourse(): CampaignsInCourse
    {
        // The soonest to close, carrying how many are in course with it: one query.
        $soonest = Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->orderBy('closes_on')
            ->orderBy('id')
            ->select(['id', 'launched_at', 'closes_on'])
            ->selectRaw('count(*) over ()::int as in_course')
            ->first();

        if ($soonest?->launched_at === null || $soonest->closes_on === null) {
            return new CampaignsInCourse(0, null, 0);
        }

        $count = $soonest->getAttribute('in_course');

        return new CampaignsInCourse(
            is_int($count) ? $count : 1,
            CampaignClock::daysLeft($soonest->closes_on),
            CampaignClock::elapsed($soonest->launched_at, CampaignClock::deadline($soonest->closes_on)->subDay()),
        );
    }
}
