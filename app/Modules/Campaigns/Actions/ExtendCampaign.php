<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\CampaignClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Gives people more days to answer. The last day only ever moves later: nobody loses the time they were told they had.
 * Once it has passed, the evaluation is over even if the hourly close has not run yet (criterio C-25).
 */
final class ExtendCampaign
{
    /**
     * @throws ValidationException when the new last day is not after the current one, or the current one is over.
     */
    public function handle(Campaign $campaign, CarbonImmutable $closesOn): void
    {
        DB::transaction(function () use ($campaign, $closesOn): void {
            $this->extend(Campaign::lockIn($campaign, CampaignStatus::Active), $closesOn);
        });
    }

    private function extend(Campaign $campaign, CarbonImmutable $closesOn): void
    {
        $current = $campaign->closes_on;

        // Past its last day its links already say "cerrada": the hourly close is only catching up.
        if ($current !== null && CampaignClock::hasPassed($current)) {
            throw ValidationException::withMessages(['closes_on' => 'Su último día ya pasó: ya no se puede extender.']);
        }

        if ($current !== null && ! $closesOn->greaterThan($current)) {
            throw ValidationException::withMessages(['closes_on' => 'La nueva fecha debe ser posterior al '.CampaignClock::day($current, isDate: true).'.']);
        }

        $campaign->update(['closes_on' => $closesOn]);
        Log::info('campaign.extended', ['campaign_id' => $campaign->id, 'closes_on' => $closesOn->format('Y-m-d')]);
    }
}
