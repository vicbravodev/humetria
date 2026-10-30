<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Throws away a draft and its drawn sample. A launched campaign is never deleted: its answers are evidence (num. 10.4).
 */
final class DeleteDraft
{
    public function handle(Campaign $campaign): void
    {
        DB::transaction(fn () => Campaign::lockIn($campaign, CampaignStatus::Draft)->delete());
        Log::info('campaign.draft_deleted', ['campaign_id' => $campaign->id]);
    }
}
