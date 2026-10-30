<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Support\Str;

final readonly class CompanyCampaignRecords implements CampaignRecords
{
    public function __construct(private InstrumentTally $tally) {}

    public function find(string $id): ?CampaignRecord
    {
        $campaign = Str::isUuid($id) ? Campaign::query()->find($id) : null;

        if ($campaign === null) {
            return null;
        }

        $tally = $this->tally->of([$campaign->id])[$campaign->id] ?? [];
        $invited = [];
        $answered = [];

        foreach ($campaign->instruments as $instrument) {
            $answered[$instrument] = $tally[$instrument][InstrumentStatus::Completed->value] ?? 0;
            $invited[$instrument] = $answered[$instrument] + ($tally[$instrument][InstrumentStatus::Pending->value] ?? 0);
        }

        return new CampaignRecord(
            id: $campaign->id,
            workCenterId: $campaign->work_center_id,
            kind: $campaign->kind->value,
            kindLabel: $campaign->kind->label(),
            closed: $campaign->status === CampaignStatus::Closed,
            instruments: $campaign->instruments,
            workers: $campaign->workers,
            sample: $campaign->population === Population::Sample,
            sampleSize: $campaign->sample_size,
            launchedOn: $campaign->launched_at === null ? null : CampaignClock::day($campaign->launched_at),
            closedOn: $campaign->closed_at === null ? null : CampaignClock::day($campaign->closed_at),
            invited: $invited,
            answered: $answered,
        );
    }
}
