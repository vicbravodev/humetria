<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Contracts\CampaignActivity;
use App\Modules\Campaigns\Data\CenterCampaignActivity;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;

final readonly class CompanyCampaignActivity implements CampaignActivity
{
    public function atCenter(string $workCenterId): CenterCampaignActivity
    {
        $evaluations = Campaign::query()->where('work_center_id', $workCenterId)->where('kind', CampaignKind::Nom035Evaluation);
        $latest = (clone $evaluations)->orderByDesc('created_at')->orderByDesc('id')->first();
        $drafts = (clone $evaluations)->where('status', CampaignStatus::Draft)->count();

        if ($latest === null) {
            return new CenterCampaignActivity($drafts, false, 0, 0, false, null);
        }

        // A person counts as answered with any guide completed, as in ChannelAnswers: the invitation's state, never an answer.
        $counts = Participant::query()->toBase()->where('campaign_id', $latest->id)->selectRaw(
            'count(*) filter (where invited_at is not null) as invited, '
            .'count(*) filter (where exists (select 1 from participant_instruments pi where pi.participant_id = campaign_participants.id and pi.status = ?)) as answered',
            [InstrumentStatus::Completed->value],
        )->first();

        return new CenterCampaignActivity(
            $drafts,
            $latest->status !== CampaignStatus::Draft,
            is_int($counts?->invited) ? $counts->invited : 0,
            is_int($counts?->answered) ? $counts->answered : 0,
            $latest->status === CampaignStatus::Closed,
            $latest->id,
        );
    }
}
