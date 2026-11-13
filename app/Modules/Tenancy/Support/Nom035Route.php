<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Disclosure\Contracts\DisclosureStanding;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Interventions\Contracts\ProgramStandings;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Results\Contracts\EvaluationResults;
use App\Modules\Tenancy\Data\RouteProgress;

/**
 * The NOM-035 route of the company's work centers, put together from the modules that keep each part of it (Campaigns,
 * Disclosure, Responses, Results and Interventions) through their contracts, in a fixed number of queries whatever the number
 * of centers or evaluations. The people who require a clinical assessment are only ever counted.
 */
final readonly class Nom035Route
{
    public function __construct(
        private CampaignRecords $campaigns,
        private DisclosureStanding $disclosure,
        private ResponseReader $responses,
        private EvaluationResults $results,
        private ProgramStandings $programs,
        private Headcount $headcount,
        private Nom035Guides $guides,
    ) {}

    /**
     * @param  list<string>  $workCenterIds
     * @param  list<CampaignRecord>  $current  what is on the go (CampaignRecords::current())
     * @param  array<string, CampaignRecord>  $latest  the latest closed evaluation of each center
     * @param  array<string, int>  $toChannel  people left to channel by evaluation; empty for whoever may not see the Guía I
     * @return array<string, RouteProgress> by work center id
     */
    public function progress(array $workCenterIds, array $current, array $latest, array $toChannel): array
    {
        return array_map(Nom035Stages::from(...), $this->facts($workCenterIds, $current, $latest, $toChannel));
    }

    /**
     * What the route of each center is read from (the same arguments as `progress()`).
     *
     * @param  list<string>  $workCenterIds
     * @param  list<CampaignRecord>  $current
     * @param  array<string, CampaignRecord>  $latest
     * @param  array<string, int>  $toChannel
     * @return array<string, Nom035Facts> by work center id
     */
    public function facts(array $workCenterIds, array $current, array $latest, array $toChannel): array
    {
        $latestIds = array_values(array_map(fn (CampaignRecord $campaign): string => $campaign->id, $latest));
        $policies = $this->disclosure->withPolicy($workCenterIds);
        $cycles = $this->campaigns->cycles($workCenterIds);
        $sharing = $this->disclosure->sharing($latestIds);
        $programs = $this->programs->forEvaluations($latestIds);
        $unreported = $this->results->withoutReportDetails($latestIds);
        $people = $this->headcount->byWorkCenter();
        $facts = [];

        foreach ($workCenterIds as $id) {
            $evaluations = array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->workCenterId === $id && $campaign->kind === 'nom035_evaluation');
            $running = array_find($evaluations, fn (CampaignRecord $campaign): bool => $campaign->daysLeft !== null);
            $evaluated = $latest[$id] ?? null;
            $facts[$id] = new Nom035Facts(
                in_array($id, $policies, true),
                $cycles[$id]->standing,
                $cycles[$id]->until,
                $running === null ? null : [...self::answered($running), 'daysLeft' => $running->daysLeft ?? 0],
                array_any($evaluations, fn (CampaignRecord $campaign): bool => $campaign->launchedOn === null),
                array_intersect(['II', 'III'], $this->guides->for($people[$id] ?? 0)) === [],
                $evaluated === null ? 0 : $toChannel[$evaluated->id] ?? 0,
                $evaluated !== null && in_array($evaluated->id, $sharing['shared'], true),
                $evaluated === null ? null : $programs[$evaluated->id] ?? null,
                $evaluated !== null && ! in_array($evaluated->id, $unreported, true),
                $evaluated === null ? null : ['closedOn' => $evaluated->closedOn, ...self::answered($evaluated)],
                $evaluated !== null && in_array($evaluated->id, $sharing['unseen'], true),
                $evaluated === null || ! isset($evaluated->invited['nom035.guide-i']) ? null : ['answered' => $evaluated->answered['nom035.guide-i'] ?? 0, 'target' => $evaluated->invited['nom035.guide-i']],
            );
        }

        return $facts;
    }

    /**
     * How many people of each evaluation require a clinical assessment and are not channeled yet (num. 5.5).
     *
     * @param  list<string>  $campaignIds
     * @return array<string, int> by campaign id; one with nobody left is left out
     */
    public function toChannel(array $campaignIds): array
    {
        $channeled = $this->results->channeledCounts($campaignIds);
        $left = [];

        foreach ($this->responses->requiringCounts($campaignIds) as $id => $requiring) {
            if ($requiring - ($channeled[$id] ?? 0) > 0) {
                $left[$id] = $requiring - ($channeled[$id] ?? 0);
            }
        }

        return $left;
    }

    /**
     * Who answered the guide that measures the evaluation (the last one applied), against its sample when a Guía III takes
     * one and against everyone invited to it when not.
     *
     * @return array{answered: int, target: int}
     */
    public static function answered(CampaignRecord $campaign): array
    {
        $main = array_last($campaign->instruments) ?? '';
        $sampleSize = $main === 'nom035.guide-iii' ? $campaign->sampleSize : null;

        return ['answered' => $campaign->answered[$main] ?? 0, 'target' => $sampleSize ?? $campaign->invited[$main] ?? 0];
    }
}
