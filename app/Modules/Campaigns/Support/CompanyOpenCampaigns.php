<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Contracts\OpenCampaigns;
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
}
