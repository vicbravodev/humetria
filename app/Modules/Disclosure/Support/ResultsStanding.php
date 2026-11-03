<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\RiskBreakdown;

/**
 * Where the results stand for each work center (criterio C-54). The only way figures reach the people is Results' risk
 * service, asked for the whole center and never for an area, so the minimum of C-23 is applied there and nowhere else. Centers
 * of up to 15 people have no 7.x obligations (num. 2 a): 7.8 does not apply to them.
 */
final readonly class ResultsStanding
{
    private const int MINIMUM_PEOPLE = 16;

    private const int LARGE_CENTER = 51;

    public function __construct(private CampaignRecords $campaigns, private RiskResults $results, private Headcount $headcount) {}

    /**
     * @param  list<string>  $workCenterIds
     * @return array<string, ResultsFact> by work center id
     */
    public function forCenters(array $workCenterIds): array
    {
        $people = $this->headcount->byWorkCenter();
        $latest = $this->campaigns->latestClosedEvaluations($workCenterIds);
        $published = [];

        foreach (ResultPublication::query()->whereIn('work_center_id', $workCenterIds)->whereNull('withdrawn_at')->get(['campaign_id', 'work_center_id']) as $publication) {
            $published[$publication->work_center_id][$publication->campaign_id] = true;
        }

        $facts = [];

        foreach ($workCenterIds as $center) {
            $facts[$center] = $this->fact($center, $people[$center] ?? 0, $latest[$center] ?? null, $published[$center] ?? []);
        }

        return $facts;
    }

    /**
     * @param  array<string, true>  $published  the evaluations of the center that are published
     */
    private function fact(string $center, int $people, ?CampaignRecord $latest, array $published): ResultsFact
    {
        $latestBreakdown = $latest === null ? null : $this->results->forCampaign($latest);
        $applies = $latestBreakdown === null ? $people >= self::MINIMUM_PEOPLE : $latestBreakdown->state !== RiskBreakdown::NOT_SCORED;
        $latestPublished = $latest !== null && isset($published[$latest->id]);
        $shown = $latestPublished ? $latest : ($published === [] ? null : $this->firstPublished($center, $published));

        return new ResultsFact(
            $applies,
            $people >= self::LARGE_CENTER ? 'iii' : 'ii',
            $latest,
            $latestBreakdown,
            $latestPublished,
            $shown,
            $shown === null ? null : ($shown->id === $latest?->id ? $latestBreakdown : $this->results->forCampaign($shown)),
        );
    }

    /**
     * The most recent published evaluation of a center, when it is not its latest closed one.
     *
     * @param  array<string, true>  $published
     */
    private function firstPublished(string $center, array $published): ?CampaignRecord
    {
        foreach ($this->campaigns->closedAt($center) as $record) {
            if (isset($published[$record->id])) {
                return $record;
            }
        }

        return null;
    }
}
