<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Collection;

/**
 * What the NOM-035 pages show: the company's evaluations with their progress, and each active work center with its
 * last evaluation and the date the next one is due (num. 7.9: at least every two years). Never an answer: only who
 * answered, counted.
 */
final readonly class CampaignPages
{
    private const array GENDER_LABELS = ['female' => 'Mujeres', 'male' => 'Hombres', 'other' => 'Otro'];

    private const array PERSON_GENDER = ['female' => 'Mujer', 'male' => 'Hombre', 'other' => 'Otro'];

    public function __construct(
        private WorkCenters $workCenters,
        private Headcount $headcount,
        private Nom035Guides $guides,
        private Instruments $instruments,
        private Roster $roster,
        private InstrumentTally $tally,
    ) {}

    /**
     * @return array{campaigns: list<array<string, mixed>>, workCenters: list<array<string, mixed>>}
     */
    public function index(): array
    {
        $campaigns = Campaign::query()->orderByDesc('created_at')->get();
        $tally = $this->tally->of(array_values($campaigns->map(fn (Campaign $campaign): string => $campaign->id)->all()));
        $names = $this->workCenterNames();

        return [
            'campaigns' => array_values($campaigns->map(fn (Campaign $campaign): array => $this->card($campaign, $names, $tally[$campaign->id] ?? []))->all()),
            'workCenters' => $this->workCenterRows($campaigns),
        ];
    }

    /**
     * @return array{campaign: array<string, mixed>, instruments: list<array<string, mixed>>, people: list<array<string, mixed>>|null, sample: list<array<string, string>>|null, launch: array{min: string, suggested: string}|null, minDeadline: string|null}
     */
    public function show(Campaign $campaign, bool $canManage): array
    {
        $tally = $this->tally->of([$campaign->id])[$campaign->id] ?? [];

        return [
            'campaign' => [
                ...$this->card($campaign, $this->workCenterNames(), $tally),
                'workers' => $campaign->workers,
                'population' => $campaign->population?->value,
                'populationLabel' => $campaign->population?->label(),
                'sampleSize' => $campaign->sample_size,
                'quotas' => $this->quotas($campaign),
                'launchedOn' => $campaign->launched_at === null ? null : CampaignClock::day($campaign->launched_at),
            ],
            'instruments' => array_map(fn (string $key): array => [
                'key' => $key,
                'title' => $this->instruments->definition($key)->shortTitle,
                'invited' => ($tally[$key][InstrumentStatus::Pending->value] ?? 0) + ($tally[$key][InstrumentStatus::Completed->value] ?? 0),
                'completed' => $tally[$key][InstrumentStatus::Completed->value] ?? 0,
                'cancelled' => $tally[$key][InstrumentStatus::Cancelled->value] ?? 0,
            ], $campaign->instruments),
            'people' => $canManage && $campaign->status !== CampaignStatus::Draft ? $this->people($campaign) : null,
            'sample' => $canManage && $campaign->status === CampaignStatus::Draft && $campaign->population === Population::Sample
                ? $this->sample($campaign)
                : null,
            // The deadline it can be launched with: tomorrow at the earliest, two weeks suggested.
            'launch' => $canManage && $campaign->status === CampaignStatus::Draft
                ? ['min' => CampaignClock::today()->addDay()->format('Y-m-d'), 'suggested' => CampaignClock::today()->addWeeks(2)->format('Y-m-d')]
                : null,
            // The earliest last day it can be extended to, while it runs.
            'minDeadline' => $canManage && $campaign->status === CampaignStatus::Active ? CampaignClock::today()->addDay()->format('Y-m-d') : null,
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
     * @return list<array<string, string>>
     */
    private function sample(Campaign $campaign): array
    {
        $people = $this->roster->findMany(array_values($campaign->participants()->get(['employee_id'])->map(fn (Participant $participant): string => $participant->employee_id)->all()));
        usort($people, fn (RosterEntry $a, RosterEntry $b): int => strcmp($a->name, $b->name));

        return array_map(fn (RosterEntry $person): array => [
            'id' => $person->id,
            'name' => $person->name,
            'area' => $person->areaName,
            'gender' => self::PERSON_GENDER[$person->gender] ?? $person->gender,
        ], $people);
    }

    /**
     * Who was invited and where each stands with each guide: whether they answered, never what. By name.
     *
     * @return list<array<string, mixed>>
     */
    private function people(Campaign $campaign): array
    {
        $participants = $campaign->participants()->with('instruments')->get();
        $roster = $this->roster->findMany(array_values($participants->map(fn (Participant $participant): string => $participant->employee_id)->all()));
        $titles = array_combine($campaign->instruments, array_map(fn (string $key): string => $this->instruments->definition($key)->shortTitle, $campaign->instruments));
        $people = [];

        foreach ($participants as $participant) {
            $person = $roster[$participant->employee_id] ?? null;
            $guides = $participant->instruments->sortBy(fn (ParticipantInstrument $guide): int|false => array_search($guide->instrument, $campaign->instruments, true));

            $people[] = [
                'id' => $participant->id,
                'name' => $person->name ?? '',
                'area' => $person->areaName ?? '',
                'reachable' => $person?->email !== null,
                'inSample' => $participant->in_sample,
                'guides' => array_values($guides->map(fn (ParticipantInstrument $guide): array => [
                    'key' => $guide->instrument,
                    'title' => $titles[$guide->instrument] ?? $guide->instrument,
                    'status' => $guide->status->value,
                    'statusLabel' => $guide->status->label(),
                ])->all()),
            ];
        }

        usort($people, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $people;
    }

    /**
     * @return array<string, string>
     */
    private function workCenterNames(): array
    {
        $names = [];

        foreach ($this->workCenters->all() as $workCenter) {
            $names[$workCenter->id] = $workCenter->name;
        }

        return $names;
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return list<array<string, mixed>>
     */
    private function workCenterRows(Collection $campaigns): array
    {
        $headcount = $this->headcount->byWorkCenter();
        $evaluations = $campaigns->where('kind', CampaignKind::Nom035Evaluation);
        $rows = [];

        foreach ($this->workCenters->all() as $workCenter) {
            if ($workCenter->archived) {
                continue;
            }

            $rows[] = $this->workCenterRow($workCenter, $headcount[$workCenter->id] ?? 0, $evaluations->where('work_center_id', $workCenter->id));
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Campaign>  $evaluations  of this work center
     * @return array<string, mixed>
     */
    private function workCenterRow(WorkCenterSummary $workCenter, int $workers, Collection $evaluations): array
    {
        $open = $evaluations->first(fn (Campaign $campaign): bool => $campaign->status !== CampaignStatus::Closed);
        $last = $evaluations->where('status', CampaignStatus::Closed)->sortByDesc('closed_at')->first();
        $due = $last?->closed_at?->addYears(2);

        return [
            'id' => $workCenter->id,
            'name' => $workCenter->name,
            'workers' => $workers,
            'guides' => $this->guides->for($workers),
            'lastEvaluatedOn' => $last?->closed_at === null ? null : CampaignClock::day($last->closed_at),
            'dueOn' => $due === null ? null : CampaignClock::day($due),
            'overdue' => $due !== null && $due->isPast(),
            'openCampaignId' => $open?->id,
            'eligible' => $workers > 0 && $open === null,
        ];
    }
}
