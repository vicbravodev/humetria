<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Actions\RecordAudit;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\InstrumentTally;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Closes an evaluation: no link works from now on. Whoever did not answer stays as pending, so the record shows who was
 * invited and did not answer. The audit trail keeps how many answered each guide, never who or what.
 */
final readonly class CloseCampaign
{
    public function __construct(private InstrumentTally $tally, private RecordAudit $recordAudit) {}

    public function handle(Campaign $campaign, bool $automatic = false): void
    {
        if ($campaign->status !== CampaignStatus::Active) {
            throw new LogicException('Only an active campaign is closed.');
        }

        $campaign->update(['status' => CampaignStatus::Closed, 'closed_at' => now()]);
        $tally = $this->tally->of([$campaign->id])[$campaign->id] ?? [];
        $counts = [
            'completed' => array_filter(array_map(fn (array $statuses): int => $statuses[InstrumentStatus::Completed->value] ?? 0, $tally)),
            'pending' => array_filter(array_map(fn (array $statuses): int => $statuses[InstrumentStatus::Pending->value] ?? 0, $tally)),
            'automatic' => $automatic,
        ];

        Log::info('campaign.closed', ['campaign_id' => $campaign->id, 'automatic' => $automatic]);
        $this->recordAudit->handle('campaign.closed', $campaign, $counts);
    }
}
