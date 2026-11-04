<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What the screen of results for the people receives (criterio C-54): the closed evaluations, a page at a time, each with whether
 * RH published it and whether it can be (a scored guide: the Guía I alone has no figures). Words and days only: no figure, no
 * area, no person.
 */
final readonly class ResultsPages
{
    public function __construct(private CampaignRecords $campaigns, private RiskResults $results, private WorkCenters $workCenters) {}

    /**
     * @return array{evaluations: LengthAwarePaginator<int, array{id: string, workCenter: string, closedOn: string|null, guide: string, state: string, publishedOn: string|null, canPublish: bool}>}
     */
    public function show(): array
    {
        $names = [];

        foreach ($this->workCenters->all() as $center) {
            $names[$center->id] = $center->name;
        }

        $page = $this->campaigns->closedEvaluations(config()->integer('disclosure.per_page'), 'evaluations');
        $published = [];

        foreach (ResultPublication::query()->whereIn('campaign_id', array_map(fn (CampaignRecord $record): string => $record->id, $page->items()))->whereNull('withdrawn_at')->get(['campaign_id', 'published_at']) as $publication) {
            $published[$publication->campaign_id] = Days::moment($publication->published_at);
        }

        return ['evaluations' => $page->withQueryString()->through(fn (CampaignRecord $record): array => [
            'id' => $record->id,
            'workCenter' => $names[$record->workCenterId] ?? '',
            'closedOn' => $record->closedOn,
            'guide' => in_array('nom035.guide-iii', $record->instruments, true) ? 'Guía III' : (in_array('nom035.guide-ii', $record->instruments, true) ? 'Guía II' : 'Guía I'),
            'state' => isset($published[$record->id]) ? 'published' : 'unpublished',
            'publishedOn' => $published[$record->id] ?? null,
            'canPublish' => ! in_array($this->results->forCampaign($record)->state, [RiskBreakdown::OPEN, RiskBreakdown::NOT_SCORED], true),
        ])];
    }
}
