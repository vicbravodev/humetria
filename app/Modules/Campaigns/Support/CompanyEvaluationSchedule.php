<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Contracts\EvaluationSchedule;
use App\Modules\Campaigns\Data\EvaluationStanding;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;

final class CompanyEvaluationSchedule implements EvaluationSchedule
{
    public function forWorkCenters(array $workCenterIds): array
    {
        $standings = [];

        // Closed ones first, oldest to newest, and the open one last (Postgres puts nulls last): what is left for each
        // work center is its open evaluation or, without one, the latest that closed.
        $evaluations = Campaign::query()
            ->where('kind', CampaignKind::Nom035Evaluation)
            ->whereIn('work_center_id', $workCenterIds)
            ->orderBy('closed_at')
            ->get(['work_center_id', 'status', 'closes_on', 'closed_at']);

        foreach ($evaluations as $campaign) {
            $standings[$campaign->work_center_id] = $this->standing($campaign);
        }

        return $standings;
    }

    private function standing(Campaign $campaign): EvaluationStanding
    {
        if ($campaign->status === CampaignStatus::Draft) {
            return new EvaluationStanding('draft', null);
        }

        if ($campaign->closed_at === null) {
            return new EvaluationStanding('running', $campaign->closes_on === null ? null : CampaignClock::day($campaign->closes_on, isDate: true));
        }

        $due = $campaign->closed_at->addYears(2);

        return new EvaluationStanding($due->isPast() ? 'overdue' : 'due', CampaignClock::day($due));
    }
}
