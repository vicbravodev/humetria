<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Actions\RecordAudit;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Events\CampaignClosed;
use App\Modules\Campaigns\Exceptions\CampaignStateChanged;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\InstrumentTally;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes an evaluation: no link works from now on. Whoever did not answer stays as pending, so the record shows who was
 * invited and did not answer. The audit trail keeps how many answered each guide, never who or what.
 */
final readonly class CloseCampaign
{
    public function __construct(private InstrumentTally $tally, private RecordAudit $recordAudit) {}

    /**
     * @throws CampaignStateChanged when it is not active any more.
     */
    public function handle(Campaign $campaign, bool $automatic = false): void
    {
        DB::transaction(function () use ($campaign, $automatic): void {
            // Under lock: answers being taken (they hold a shared lock on it) finish first, and a second close finds it
            // closed.
            $campaign = Campaign::lockIn($campaign, CampaignStatus::Active);
            $this->close($campaign, $automatic);
        });
    }

    private function close(Campaign $campaign, bool $automatic): void
    {
        $campaign->update(['status' => CampaignStatus::Closed, 'closed_at' => now()]);
        $tally = $this->tally->of([$campaign->id])[$campaign->id] ?? [];
        $counts = [
            'completed' => array_filter(array_map(fn (array $statuses): int => $statuses[InstrumentStatus::Completed->value] ?? 0, $tally)),
            'pending' => array_filter(array_map(fn (array $statuses): int => $statuses[InstrumentStatus::Pending->value] ?? 0, $tally)),
            'automatic' => $automatic,
        ];

        Log::info('campaign.closed', ['campaign_id' => $campaign->id, 'automatic' => $automatic]);
        $this->recordAudit->handle('campaign.closed', $campaign, $counts);
        event(new CampaignClosed($campaign->tenant_id, $campaign->id));
    }
}
