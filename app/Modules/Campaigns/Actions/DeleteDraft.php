<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Throws away a draft and its drawn sample. A launched campaign is never deleted: its answers are evidence (num. 10.4).
 */
final class DeleteDraft
{
    public function handle(Campaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            throw new LogicException('Only a draft is deleted.');
        }

        $campaign->delete();
        Log::info('campaign.draft_deleted', ['campaign_id' => $campaign->id]);
    }
}
