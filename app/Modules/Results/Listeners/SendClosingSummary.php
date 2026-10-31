<?php

declare(strict_types=1);

namespace App\Modules\Results\Listeners;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Events\CampaignClosed;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Results\Notifications\ClosingSummary;
use App\Modules\Results\Support\ResultsPages;
use App\Modules\Results\Support\RiskDistribution;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the company's owner how an evaluation closed: participation and, through the one service that applies the
 * minimum group (criterio C-23), how many questionnaires sit at each risk level. Counts only, never an answer.
 */
final readonly class SendClosingSummary implements ShouldQueue
{
    public function __construct(
        private Companies $companies,
        private CampaignRecords $campaigns,
        private WorkCenters $workCenters,
        private ResponseReader $responses,
        private RiskDistribution $distribution,
    ) {}

    public function handle(CampaignClosed $event): void
    {
        $company = $this->companies->enter($event->tenantId);
        $owner = $this->companies->contact();
        $campaign = $this->campaigns->find($event->campaignId);

        if ($company->blocked || $owner === null || $campaign === null) {
            return;
        }

        $guide = ResultsPages::riskGuide($campaign);
        $measured = $guide ?? 'nom035.guide-i';
        $risk = $guide === null ? null : $this->distribution->of($guide, $this->responses->anonymous($campaign->id, $guide));

        Notification::route('mail', $owner)->notify(new ClosingSummary(
            tradeName: $company->tradeName,
            workCenter: $this->workCenters->profile($campaign->workCenterId)->name ?? '',
            answered: $campaign->answered[$measured] ?? 0,
            invited: $campaign->invited[$measured] ?? 0,
            levels: $risk !== null && $risk['visible'] ? $risk['final'] : null,
            domain: $risk !== null && $risk['visible'] ? self::heaviest($risk['domains']) : null,
            hasRisk: $guide !== null,
            requiringAssessment: $this->responses->screeningCounts($campaign->id)['requiring'],
            url: route('tenant.campaigns.results', [$company->slug, $campaign->id]),
        ));
        Log::info('campaign.closing_summary_sent', ['campaign_id' => $campaign->id]);
    }

    /**
     * The domain with the most questionnaires at Alto or Muy alto, and how many: a count, never an average (C-14).
     *
     * @param  list<array{key: string, label: string, levels: list<int>}>  $domains
     * @return array{label: string, high: int}|null
     */
    private static function heaviest(array $domains): ?array
    {
        $heaviest = null;

        foreach ($domains as $domain) {
            $high = ($domain['levels'][3] ?? 0) + ($domain['levels'][4] ?? 0);

            if ($high > 0 && ($heaviest === null || $high > $heaviest['high'])) {
                $heaviest = ['label' => $domain['label'], 'high' => $high];
            }
        }

        return $heaviest;
    }
}
