<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Results\Contracts\EvaluationResults;
use App\Modules\Results\Models\ReportDetails;

final readonly class CompanyEvaluationResults implements EvaluationResults
{
    public function __construct(private ResponseReader $responses, private RiskDistribution $distribution) {}

    public function withoutReportDetails(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $filled = ReportDetails::query()->whereIn('campaign_id', $campaignIds)->get(['campaign_id'])->map(fn (ReportDetails $details): string => $details->campaign_id)->all();

        return array_values(array_diff($campaignIds, $filled));
    }

    public function finalLevels(CampaignRecord $campaign): ?array
    {
        $guide = ResultsPages::riskGuide($campaign);

        if ($guide === null || ! $campaign->closed) {
            return null;
        }

        $result = $this->distribution->of($guide, $this->responses->anonymous($campaign->id, $guide));

        return $result['visible'] ? $result['final'] : null;
    }
}
