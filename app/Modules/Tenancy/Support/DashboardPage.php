<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Results\Contracts\EvaluationResults;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What the company's panel shows: the evaluation in course that closes soonest, what comes next, where each work
 * center stands in its two-year cycle and the closed evaluations. Campaigns and results are read through their
 * contracts; answers only ever counted, and scored only through the minimum group.
 */
final readonly class DashboardPage
{
    private const int MAX_STEPS = 5;

    private const int CYCLE_PER_PAGE = 10;

    private const int CLOSED_PER_PAGE = 5;

    public function __construct(
        private CampaignRecords $campaigns,
        private EvaluationResults $results,
        private ResponseReader $responses,
        private Instruments $instruments,
        private Nom035Guides $guides,
        private Headcount $headcount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(bool $canManage, bool $canSeeScreenings): array
    {
        $current = $this->campaigns->current();
        $active = array_values(array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->daysLeft !== null));
        $drafts = array_values(array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->launchedOn === null));
        $closed = $this->campaigns->closedEvaluations(self::CLOSED_PER_PAGE, 'closed');
        $cycle = $this->cycle();
        $names = $this->names([...array_map(fn (CampaignRecord $campaign): string => $campaign->workCenterId, $current), ...array_map(fn (CampaignRecord $campaign): string => $campaign->workCenterId, $closed->items())]);
        $steps = $this->steps($current, $drafts, $names, $canManage, $canSeeScreenings);

        return [
            'today' => ucfirst(CarbonImmutable::now(config()->string('app.display_timezone'))->isoFormat('dddd D [de] MMMM')),
            // Every evaluation hangs from a work center: until there is one, the panel points there first.
            'hasWorkCenters' => $cycle->total() > 0,
            'counts' => ['active' => count($active), 'drafts' => count($drafts)],
            'active' => $active === [] ? null : [
                'id' => $active[0]->id,
                'workCenter' => $names[$active[0]->workCenterId] ?? '',
                'kindLabel' => $active[0]->kindLabel,
                'instruments' => $this->titles($active[0]->instruments),
                'closesOn' => $active[0]->closesOn,
                'daysLeft' => $active[0]->daysLeft,
                ...self::progress($active[0]),
            ],
            'nextSteps' => array_slice($steps, 0, self::MAX_STEPS),
            'pendingSteps' => count($steps),
            'cycle' => $cycle,
            'closed' => $closed->through(fn (CampaignRecord $campaign): array => [
                'id' => $campaign->id,
                'workCenter' => $names[$campaign->workCenterId] ?? '',
                'instruments' => $this->titles($campaign->instruments),
                'closedOn' => $campaign->closedOn,
                ...self::progress($campaign),
                'final' => $this->results->finalLevels($campaign),
            ]),
            'levels' => array_map(fn (RiskLevel $level): array => ['value' => $level->value, 'label' => $level->label()], RiskLevel::cases()),
        ];
    }

    /**
     * Drafts to launch and reports to complete for whoever manages campaigns; people to channel for whoever may see
     * the Guía I.
     *
     * @param  list<CampaignRecord>  $current
     * @param  list<CampaignRecord>  $drafts
     * @param  array<string, string>  $names
     * @return list<array{kind: string, campaignId: string, workCenter: string, workers: int|null, instruments: list<string>, people: int|null}>
     */
    private function steps(array $current, array $drafts, array $names, bool $canManage, bool $canSeeScreenings): array
    {
        $step = fn (string $kind, CampaignRecord $campaign, ?int $people = null): array => [
            'kind' => $kind,
            'campaignId' => $campaign->id,
            'workCenter' => $names[$campaign->workCenterId] ?? '',
            // What a draft will apply, to how many.
            'workers' => $kind === 'launch' ? $campaign->workers : null,
            'instruments' => $kind === 'launch' ? $this->titles($campaign->instruments) : [],
            'people' => $people,
        ];
        $closed = array_values(array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->closed && $campaign->kind === 'nom035_evaluation'));
        $launched = array_values(array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->launchedOn !== null));
        $unreported = $canManage ? $this->results->withoutReportDetails(array_map(fn (CampaignRecord $campaign): string => $campaign->id, $closed)) : [];
        $requiring = $canSeeScreenings ? $this->responses->requiringCounts(array_map(fn (CampaignRecord $campaign): string => $campaign->id, $launched)) : [];

        return [
            ...($canManage ? array_map(fn (CampaignRecord $draft): array => $step('launch', $draft), $drafts) : []),
            ...array_map(fn (CampaignRecord $campaign): array => $step('report', $campaign), array_values(array_filter($closed, fn (CampaignRecord $campaign): bool => in_array($campaign->id, $unreported, true)))),
            ...array_map(fn (CampaignRecord $campaign): array => $step('screenings', $campaign, $requiring[$campaign->id]), array_values(array_filter($launched, fn (CampaignRecord $campaign): bool => isset($requiring[$campaign->id])))),
        ];
    }

    /**
     * The work centers in use, by name, each with its people, its guides and where it stands in its cycle.
     *
     * @return LengthAwarePaginator<int, array{id: string, name: string, workers: int, instruments: list<string>, standing: string, from: string|null, until: string|null, elapsed: int}>
     */
    private function cycle(): LengthAwarePaginator
    {
        $page = WorkCenter::query()->whereNull('archived_at')->orderBy('name')->orderBy('id')
            ->paginate(self::CYCLE_PER_PAGE, ['id', 'name'], 'cycle')->withQueryString();
        $ids = array_values(array_map(fn (WorkCenter $workCenter): string => $workCenter->id, $page->items()));
        $cycles = $ids === [] ? [] : $this->campaigns->cycles($ids);
        $headcount = $ids === [] ? [] : $this->headcount->byWorkCenter();

        return $page->through(function (WorkCenter $workCenter) use ($cycles, $headcount): array {
            $workers = $headcount[$workCenter->id] ?? 0;
            $cycle = $cycles[$workCenter->id];

            return [
                'id' => $workCenter->id,
                'name' => $workCenter->name,
                'workers' => $workers,
                'instruments' => array_map(fn (string $numeral): string => "Guía {$numeral}", $this->guides->for($workers)),
                'standing' => $cycle->standing,
                'from' => $cycle->from,
                'until' => $cycle->until,
                'elapsed' => $cycle->elapsed,
            ];
        });
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string> work center names by id, archived ones included
     */
    private function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (WorkCenter::query()->whereIn('id', array_unique($ids))->get(['id', 'name']) as $workCenter) {
            $names[$workCenter->id] = $workCenter->name;
        }

        return $names;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string> "Guía I", "Guía III"
     */
    private function titles(array $keys): array
    {
        return array_map(fn (string $key): string => $this->instruments->definition($key)->shortTitle, $keys);
    }

    /**
     * The guide that measures the campaign (the last one applied): answered against the sample when Guía III takes
     * one, against everyone invited to it when not.
     *
     * @return array{completed: int, target: int}
     */
    private static function progress(CampaignRecord $campaign): array
    {
        $main = array_last($campaign->instruments) ?? '';
        $sampleSize = $main === 'nom035.guide-iii' ? $campaign->sampleSize : null;

        return ['completed' => $campaign->answered[$main] ?? 0, 'target' => $sampleSize ?? $campaign->invited[$main] ?? 0];
    }
}
