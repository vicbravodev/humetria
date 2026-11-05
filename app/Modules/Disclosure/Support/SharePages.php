<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Data\RiskGroup;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * «Comparte los resultados con tu equipo» (board V2-N035-Compartir): what the people of a center read once RH publishes the
 * results of an evaluation (num. 7.8; criterio C-54), beside how it reaches them. The center whole and never an area, by the
 * same route of data as Results with its minimum (C-23): below it the count is «menos de N» and no theme is named. The
 * measures are the Programa's that the page may show (ProgramMeasureFeed); the channel is the complaints channel's.
 */
final readonly class SharePages
{
    private const int THEMES = 3;

    public function __construct(
        private RiskResults $results,
        private ProgramMeasureFeed $measures,
        private ComplaintChannelFeed $channel,
        private PolicyStanding $policy,
        private Companies $companies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(CampaignRecord $campaign, WorkCenterSummary $center): array
    {
        $breakdown = $this->results->forCampaign($campaign);
        $figures = $breakdown->state === RiskBreakdown::AVAILABLE;
        $publication = ResultPublication::query()->where('campaign_id', $campaign->id)->whereNull('withdrawn_at')->first(['published_at', 'show_measures']);
        $channel = $this->channel->current();

        return [
            'campaign' => ['id' => $campaign->id, 'closedOn' => $campaign->closedOn],
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'company' => $this->companies->current()->tradeName,
            // Below the minimum, only that it is fewer than it: the page writes «menos de 5».
            'answered' => $figures ? $breakdown->answered : null,
            'minimum' => config()->integer('results.min_group'),
            'themes' => $figures ? $this->themes($breakdown) : [],
            'measures' => array_map(fn (PublicMeasure $measure): array => ['title' => $measure->title, 'dueOn' => $measure->dueOn], $this->measures->forCampaign($campaign->id)),
            'channel' => $channel === null ? null : ['anonymous' => $channel->anonymousAllowed],
            'policy' => isset($this->policy->inForce([$center->id])[$center->id]),
            'published' => $publication === null ? null : Days::moment($publication->published_at),
            'showMeasures' => $publication === null || $publication->show_measures,
            'canPublish' => ! in_array($breakdown->state, [RiskBreakdown::OPEN, RiskBreakdown::NOT_SCORED], true),
        ];
    }

    /**
     * The domains with the most questionnaires in Alto or Muy alto, the most first; one with none is left out.
     *
     * @return list<string>
     */
    private function themes(RiskBreakdown $breakdown): array
    {
        $high = fn (RiskGroup $group): int => ($group->levels[3] ?? 0) + ($group->levels[4] ?? 0);
        $domains = array_values(array_filter($breakdown->domains, fn (RiskGroup $group): bool => $high($group) > 0));
        usort($domains, fn (RiskGroup $a, RiskGroup $b): int => $high($b) <=> $high($a));

        return array_map(fn (RiskGroup $group): string => $group->label, array_slice($domains, 0, self::THEMES));
    }
}
