<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Actions\DrawReplacements;
use App\Modules\Campaigns\Data\PeopleFilter;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Enums\Standing;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * What the NOM-035 pages show: the company's evaluations with their progress, and each active work center with its
 * last evaluation and the date the next one is due (num. 7.9: at least every two years). Never an answer: only who
 * answered, counted.
 */
final readonly class CampaignPages
{
    private const array GENDER_LABELS = ['female' => 'Mujeres', 'male' => 'Hombres', 'other' => 'Otro'];

    private const int OPEN_PER_PAGE = 12;

    private const int CLOSED_PER_PAGE = 25;

    private const array PERSON_GENDER = ['female' => 'Mujer', 'male' => 'Hombre', 'other' => 'Otro'];

    public function __construct(
        private WorkCenters $workCenters,
        private Headcount $headcount,
        private Nom035Guides $guides,
        private Instruments $instruments,
        private Roster $roster,
        private InstrumentTally $tally,
        private DrawReplacements $replacements,
        private CampaignPeople $people,
        private Areas $areas,
    ) {}

    /**
     * Each work center in use with its evaluations, the open Guía I by event and the closed campaigns (history), each
     * paged on its own; how many centers fall in each size the norm sets apart, and the minimum group of results.
     *
     * @return array{events: LengthAwarePaginator<int, array<string, mixed>>, closed: LengthAwarePaginator<int, array<string, mixed>>, workCenters: LengthAwarePaginator<int, array<string, mixed>>, centersBySize: array{small: int, medium: int, large: int}, minGroup: int}
     */
    public function index(): array
    {
        $events = Campaign::query()->where('kind', CampaignKind::Nom035Trauma)->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Active])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::OPEN_PER_PAGE, pageName: 'events')->withQueryString();
        $closed = Campaign::query()->where('status', CampaignStatus::Closed)->orderByDesc('closed_at')->orderByDesc('id')
            ->paginate(self::CLOSED_PER_PAGE, pageName: 'closed')->withQueryString();
        $centers = $this->workCenters->activePage(CampaignPeople::PER_PAGE, 'centers');
        $evaluations = $this->evaluationsOf($centers->items());
        $all = $this->workCenters->all();
        $headcount = $this->headcount->byWorkCenter();
        $card = $this->cards(
            [...$events->getCollection()->all(), ...$closed->getCollection()->all(), ...$evaluations->where('status', '!==', CampaignStatus::Closed)->all()],
            self::names($all),
        );

        return [
            'events' => $events->through($card),
            'closed' => $closed->through($card),
            'workCenters' => $centers->through(fn (WorkCenterSummary $workCenter): array => $this->workCenterRow(
                $workCenter, $headcount[$workCenter->id] ?? 0, $evaluations->where('work_center_id', $workCenter->id), $card,
            )),
            'centersBySize' => $this->centersBySize($all, $headcount),
            'minGroup' => config()->integer('results.min_group'),
        ];
    }

    /**
     * A work center in use, as the assistant for a new evaluation needs it; null when archived or not found.
     *
     * @return array<string, mixed>|null
     */
    public function candidate(string $workCenterId): ?array
    {
        $workCenter = $this->workCenters->findActive($workCenterId);

        if ($workCenter === null) {
            return null;
        }

        $evaluations = $this->evaluationsOf([$workCenter]);
        $card = $this->cards($evaluations->where('status', '!==', CampaignStatus::Closed)->all(), [$workCenter->id => $workCenter->name]);

        return $this->workCenterRow($workCenter, $this->headcount->byWorkCenter()[$workCenter->id] ?? 0, $evaluations, $card);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Campaign $campaign, bool $canManage, PeopleFilter $filter = new PeopleFilter): array
    {
        $tally = $this->tally->of([$campaign->id])[$campaign->id] ?? [];
        $followed = $canManage && $campaign->status !== CampaignStatus::Draft;
        $unreachable = $campaign->status === CampaignStatus::Draft ? [] : $this->tally->unreachable($campaign->id);

        return [
            'campaign' => [
                ...$this->card($campaign, $this->workCenterNames(), $tally),
                'workers' => $campaign->workers,
                'population' => $campaign->population?->value,
                'populationLabel' => $campaign->population?->label(),
                'sampleSize' => $campaign->sample_size,
                'quotas' => $this->quotas($campaign),
                'workCenterId' => $campaign->work_center_id,
                'createdOn' => CampaignClock::day($campaign->created_at),
                // Num. 7.9: evaluated again within two years of closing.
                'nextDueBy' => $campaign->closed_at === null ? null : CampaignClock::day($campaign->closed_at->addYears(2)),
            ],
            'instruments' => array_map(fn (string $key): array => [
                'key' => $key,
                'title' => $this->instruments->definition($key)->shortTitle,
                'invited' => ($tally[$key][InstrumentStatus::Pending->value] ?? 0) + ($tally[$key][InstrumentStatus::Completed->value] ?? 0),
                'completed' => $tally[$key][InstrumentStatus::Completed->value] ?? 0,
                'pending' => ($tally[$key][InstrumentStatus::Pending->value] ?? 0) - ($unreachable[$key] ?? 0),
                'unreachable' => $unreachable[$key] ?? 0,
                'cancelled' => $tally[$key][InstrumentStatus::Cancelled->value] ?? 0,
            ], $campaign->instruments),
            'people' => $followed ? $this->people->page($campaign, $filter) : null,
            'standings' => $followed ? $this->standings($campaign, $filter->guide) : null,
            'areas' => $followed ? $this->people->byArea($campaign) : null,
            'answersByDay' => $followed && $campaign->status === CampaignStatus::Active ? $this->people->answersByDay($campaign) : null,
            // Before launch: how many of the people the links will reach, by email.
            'contact' => $canManage && $campaign->status === CampaignStatus::Draft ? $this->contact($campaign) : null,
            'sending' => $campaign->status === CampaignStatus::Active ? $this->sending($campaign) : null,
            'filters' => [
                'status' => $filter->standing->value ?? 'all',
                'guide' => $filter->guide,
                'area' => $filter->areaId === null ? null : ['value' => $filter->areaId, 'label' => $this->areas->name($filter->areaId) ?? ''],
                'q' => $filter->search,
            ],
            'sample' => $canManage && $campaign->status === CampaignStatus::Draft && $campaign->population === Population::Sample
                ? $this->sample($campaign)
                : null,
            // The deadline it can be launched with: tomorrow at the earliest, two weeks suggested.
            'launch' => $canManage && $campaign->status === CampaignStatus::Draft
                ? ['min' => CampaignClock::today()->addDay()->format('Y-m-d'), 'suggested' => CampaignClock::today()->addWeeks(2)->format('Y-m-d')]
                : null,
            // The earliest last day it can be extended to, while it runs.
            'minDeadline' => $canManage && $campaign->status === CampaignStatus::Active ? CampaignClock::today()->addDay()->format('Y-m-d') : null,
            'replacementPool' => $canManage && $campaign->status === CampaignStatus::Active && $campaign->population === Population::Sample
                ? $this->replacementPool($campaign)
                : null,
        ];
    }

    /**
     * @param  array<string, string>  $names
     * @param  array<string, array<string, int>>  $tally
     * @return array<string, mixed>
     */
    private function card(Campaign $campaign, array $names, array $tally): array
    {
        return [
            'id' => $campaign->id,
            'workCenter' => $names[$campaign->work_center_id] ?? '',
            'kind' => $campaign->kind->value,
            'kindLabel' => $campaign->kind->label(),
            'status' => $campaign->status->value,
            'statusLabel' => $campaign->status->label(),
            'instruments' => array_map(fn (string $key): string => $this->instruments->definition($key)->shortTitle, $campaign->instruments),
            'closesOn' => $campaign->closes_on === null ? null : CampaignClock::day($campaign->closes_on, isDate: true),
            'closedOn' => $campaign->closed_at === null ? null : CampaignClock::day($campaign->closed_at),
            'launchedOn' => $campaign->launched_at === null ? null : CampaignClock::day($campaign->launched_at),
            'progress' => $this->progress($campaign, $tally),
        ];
    }

    /**
     * The guide that measures the campaign (Guía II or III in an evaluation, Guía I otherwise): answered against
     * the sample when there is one, against everyone invited to it when not.
     *
     * @param  array<string, array<string, int>>  $tally
     * @return array{completed: int, target: int, againstSample: bool}|null
     */
    private function progress(Campaign $campaign, array $tally): ?array
    {
        if ($campaign->status === CampaignStatus::Draft) {
            return null;
        }

        $main = array_last($campaign->instruments);
        $counts = $main === null ? [] : ($tally[$main] ?? []);
        $completed = $counts[InstrumentStatus::Completed->value] ?? 0;
        $sampleSize = $main === 'nom035.guide-iii' ? $campaign->sample_size : null;

        return [
            'completed' => $completed,
            'target' => $sampleSize ?? $completed + ($counts[InstrumentStatus::Pending->value] ?? 0),
            'againstSample' => $sampleSize !== null,
        ];
    }

    /**
     * @return list<array{gender: string, label: string, count: int}>
     */
    private function quotas(Campaign $campaign): array
    {
        $quotas = [];

        // In the order of the labels: jsonb keeps its keys in its own order.
        foreach (self::GENDER_LABELS as $gender => $label) {
            if (isset($campaign->quotas[$gender])) {
                $quotas[] = ['gender' => $gender, 'label' => $label, 'count' => $campaign->quotas[$gender]];
            }
        }

        return $quotas;
    }

    /**
     * Who the draw put in the sample, by name, a page at a time.
     *
     * @return LengthAwarePaginator<int, array{id: string, name: string, area: string, gender: string}>
     */
    private function sample(Campaign $campaign): LengthAwarePaginator
    {
        $sampled = array_values($campaign->participants()->get(['employee_id'])->map(fn (Participant $participant): string => $participant->employee_id)->all());

        return $this->roster->page(new RosterQuery(employeeIds: $sampled), CampaignPeople::PER_PAGE)->through(fn (RosterEntry $person): array => [
            'id' => $person->id,
            'name' => $person->name,
            'area' => $person->areaName,
            'gender' => self::PERSON_GENDER[$person->gender] ?? $person->gender,
        ]);
    }

    /**
     * @return array{reachable: int, unreachable: int}
     */
    private function contact(Campaign $campaign): array
    {
        $people = $this->roster->activeIn($campaign->work_center_id);
        $reachable = count(array_filter($people, fn (RosterEntry $person): bool => $person->email !== null));

        return ['reachable' => $reachable, 'unreachable' => count($people) - $reachable];
    }

    /**
     * How many people of each gender are left outside the sample, to replace someone of that gender who will not answer.
     *
     * @return list<array{gender: string, label: string, available: int}>
     */
    private function replacementPool(Campaign $campaign): array
    {
        $available = $this->replacements->available($campaign);
        $pool = [];

        foreach (self::GENDER_LABELS as $gender => $label) {
            if (isset($available[$gender])) {
                $pool[] = ['gender' => $gender, 'label' => $label, 'available' => count($available[$gender])];
            }
        }

        return $pool;
    }

    /**
     * How the last batch of links goes while some are still waiting: how many it already sent or gave up on, of all.
     *
     * @return array{done: int, total: int}|null
     */
    private function sending(Campaign $campaign): ?array
    {
        if ($campaign->invitation_batch === null) {
            return null;
        }

        $counts = $campaign->participants()
            ->where('invitation_batch', $campaign->invitation_batch)
            ->toBase()
            ->selectRaw("count(*)::int as total, count(*) filter (where invitation_status = 'queued')::int as queued")
            ->first();
        $total = is_int($counts?->total) ? $counts->total : 0;
        $queued = is_int($counts?->queued) ? $counts->queued : 0;

        return $queued === 0 ? null : ['done' => $total - $queued, 'total' => $total];
    }

    /**
     * How many people stand where, in the order of the filter's choices, and everyone.
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    private function standings(Campaign $campaign, ?string $guide): array
    {
        $counts = $this->people->standings($campaign, $guide);
        $standings = array_map(fn (Standing $standing): array => ['value' => $standing->value, 'label' => $standing->label(), 'count' => $counts[$standing->value] ?? 0], Standing::cases());

        return [...$standings, ['value' => 'all', 'label' => 'Todas', 'count' => array_sum($counts)]];
    }

    /**
     * @return array<string, string>
     */
    private function workCenterNames(): array
    {
        return self::names($this->workCenters->all());
    }

    /**
     * @param  list<WorkCenterSummary>  $workCenters
     * @return array<string, string>
     */
    private static function names(array $workCenters): array
    {
        $names = [];

        foreach ($workCenters as $workCenter) {
            $names[$workCenter->id] = $workCenter->name;
        }

        return $names;
    }

    /**
     * The card of each campaign given, its progress tallied for all of them at once.
     *
     * @param  array<int, Campaign>  $campaigns
     * @param  array<string, string>  $names
     * @return Closure(Campaign): array<string, mixed>
     */
    private function cards(array $campaigns, array $names): Closure
    {
        $tally = $this->tally->of(array_values(array_map(fn (Campaign $campaign): string => $campaign->id, $campaigns)));

        return fn (Campaign $campaign): array => $this->card($campaign, $names, $tally[$campaign->id] ?? []);
    }

    /**
     * The NOM-035 evaluations of the work centers given.
     *
     * @param  array<int, WorkCenterSummary>  $workCenters
     * @return Collection<int, Campaign>
     */
    private function evaluationsOf(array $workCenters): Collection
    {
        return Campaign::query()
            ->where('kind', CampaignKind::Nom035Evaluation)
            ->whereIn('work_center_id', array_map(fn (WorkCenterSummary $workCenter): string => $workCenter->id, $workCenters))
            ->get();
    }

    /**
     * How many work centers in use call for Guía I alone (up to 15 people), Guías I and II (16 to 50) and Guías I and
     * III (more than 50), num. 2. A center with nobody yet counts in none.
     *
     * @param  list<WorkCenterSummary>  $workCenters
     * @param  array<string, int>  $headcount
     * @return array{small: int, medium: int, large: int}
     */
    private function centersBySize(array $workCenters, array $headcount): array
    {
        $sizes = ['small' => 0, 'medium' => 0, 'large' => 0];

        foreach ($workCenters as $workCenter) {
            $guides = $workCenter->archived ? [] : $this->guides->for($headcount[$workCenter->id] ?? 0);
            $size = match (array_last($guides)) {
                'I' => 'small',
                'II' => 'medium',
                'III' => 'large',
                null => null,
            };

            if ($size !== null) {
                $sizes[$size]++;
            }
        }

        return $sizes;
    }

    /**
     * @param  Collection<int, Campaign>  $evaluations  of this work center
     * @param  Closure(Campaign): array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function workCenterRow(WorkCenterSummary $workCenter, int $workers, Collection $evaluations, Closure $card): array
    {
        $open = $evaluations->first(fn (Campaign $campaign): bool => $campaign->status !== CampaignStatus::Closed);
        $last = $evaluations->where('status', CampaignStatus::Closed)->sortByDesc('closed_at')->first();
        $due = $last?->closed_at?->addYears(2);

        return [
            'id' => $workCenter->id,
            'name' => $workCenter->name,
            'activity' => $workCenter->mainActivity,
            'workers' => $workers,
            'guides' => $this->guides->for($workers),
            'lastEvaluatedOn' => $last?->closed_at === null ? null : CampaignClock::day($last->closed_at),
            'dueOn' => $due === null ? null : CampaignClock::day($due),
            'overdue' => $due !== null && $due->isPast(),
            'lastCampaignId' => $last?->id,
            'openCampaign' => $open === null ? null : $card($open),
            'eligible' => $workers > 0 && $open === null,
        ];
    }
}
