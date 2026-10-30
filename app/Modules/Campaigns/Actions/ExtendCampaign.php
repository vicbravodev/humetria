<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\CampaignClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Gives people more days to answer. The last day only ever moves later: nobody loses the time they were told they had.
 */
final class ExtendCampaign
{
    /**
     * @throws ValidationException when the new last day is not after the current one.
     */
    public function handle(Campaign $campaign, CarbonImmutable $closesOn): void
    {
        $current = $campaign->closes_on;

        if ($current !== null && ! $closesOn->greaterThan($current)) {
            throw ValidationException::withMessages(['closes_on' => 'La nueva fecha debe ser posterior al '.CampaignClock::day($current, isDate: true).'.']);
        }

        $campaign->update(['closes_on' => $closesOn]);
        Log::info('campaign.extended', ['campaign_id' => $campaign->id, 'closes_on' => $closesOn->format('Y-m-d')]);
    }
}
