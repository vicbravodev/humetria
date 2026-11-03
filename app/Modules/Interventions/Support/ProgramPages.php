<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Contracts\EvaluationSchedule;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Enums\Professional;
use App\Modules\Interventions\Enums\ProgramState;
use App\Modules\Interventions\Enums\RowState;
use App\Modules\Interventions\Exceptions\ProgramUnavailable;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\AreaRisk;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Data\RiskGroup;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * What the page of a Programa shows. The figures are Results' (read live, under the minimum in force); the Programa adds
 * only what HR wrote. An action names only the areas Results shows today (criterio C-33). Never an answer, an employee or
 * an id of one.
 */
final readonly class ProgramPages
{
    private const int ACTIONS_PER_PAGE = 10;

    public function __construct(private RiskResults $results, private WorkCenters $workCenters, private Instruments $instruments, private Members $members, private Companies $companies, private CampaignRecords $campaigns, private EvaluationSchedule $schedule, private GuideCatalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function show(CampaignRecord $campaign, ?Program $program, bool $canManage, ?string $itemFilter = null, ?string $statusFilter = null): array
    {
        $guide = array_find($campaign->instruments, fn (string $key): bool => $key !== 'nom035.guide-i');
        $areas = $program === null ? [] : $this->results->areaGroups($campaign);
        $items = $program === null ? [] : array_values(ProgramItem::query()->where('program_id', $program->id)->get()->all());
        $filters = ['item' => $this->itemFilter($itemFilter, $items), 'status' => ActionStatus::tryFrom($statusFilter ?? '')?->value];
        // The closed evaluations of the center that came after this one: the candidates to follow up the measures (8.4 e).
        $later = $program === null ? [] : $this->laterEvaluations($campaign);

        return [
            'campaign' => [
                'id' => $campaign->id,
                'workCenter' => $this->workCenters->profile($campaign->workCenterId)->name ?? '',
                'kindLabel' => $campaign->kindLabel,
                'closedOn' => $campaign->closedOn,
                'guide' => $guide === null ? null : $this->instruments->definition($guide)->shortTitle,
            ],
            'requirement' => $program === null ? $this->requirement($campaign) : null,
            'program' => $program === null ? null : $this->program($program, $items, $later, ProgramAction::query()->where('program_id', $program->id)->get(['id', 'item_id', 'status', 'due_on', 'kind'])->all()),
            'actions' => $program === null ? null : $this->actions($program, $areas, $filters, $items, $canManage ? fn (string $actionId, string $entryId): string => route('tenant.campaigns.program.evidence', [$this->companies->current()->slug, $campaign->id, $actionId, $entryId]) : null),
            'filters' => $filters,
            'catalog' => $canManage ? self::catalog() : null,
            'manualOptions' => $canManage && $program !== null && $guide !== null ? $this->manualOptions($guide, $items) : null,
            'followUpEvaluations' => $canManage && $program !== null ? array_map(fn (CampaignRecord $record): array => self::evaluation($record), $later) : null,
            'followUpSuggestion' => $canManage && $program !== null && $later === [] ? $this->followUpSuggestion($campaign->workCenterId) : null,
            'areaOptions' => $canManage && $program !== null ? $this->areaOptions($areas, $items) : null,
            'members' => $canManage ? array_map(fn ($member): array => ['id' => $member->userId, 'name' => $member->name], $this->members->all()) : null,
            'can' => ['manage' => $canManage],
        ];
    }

    /**
     * Why there is no Programa yet: the results ask for one, ask for none, or are under the minimum.
     *
     * @throws ProgramUnavailable when the evaluation still runs or applies only the Guía I.
     */
    private function requirement(CampaignRecord $campaign): string
    {
        $risk = $this->results->forCampaign($campaign);

        return match (true) {
            in_array($risk->state, [RiskBreakdown::OPEN, RiskBreakdown::NOT_SCORED], true) => throw new ProgramUnavailable,
            $risk->state === RiskBreakdown::BELOW_MINIMUM => 'below_minimum',
            $risk->needingProgram() === [] => 'not_required',
            default => 'required',
        };
    }

    /**
     * @param  list<ProgramItem>  $items
     * @param  list<CampaignRecord>  $later  the closed evaluations of the center that came after the Programa's own
     * @param  array<int, ProgramAction>  $actions  every action of the Programa: its row, state and due day
     * @return array<string, mixed>
     */
    private function program(Program $program, array $items, array $later, array $actions): array
    {
        usort($items, fn (ProgramItem $a, ProgramItem $b): int => self::order($a) <=> self::order($b));
        $today = ProgramClock::today();
        $rows = [];

        foreach ($items as $item) {
            $statuses = array_values(array_map(fn (ProgramAction $action): ActionStatus => $action->status, array_filter($actions, fn (ProgramAction $action): bool => $action->item_id === $item->id)));
            $rows[] = ['item' => $item, 'state' => ProgramProgress::rowState($statuses), 'followUpPending' => $item->follow_up_due_on !== null && $item->follow_up_done_on === null];
        }

        $kinds = array_values(array_map(fn (ProgramAction $action): ActionKind => $action->kind, array_filter($actions, fn (ProgramAction $action): bool => $action->status !== ActionStatus::Cancelled)));
        $analysisRows = [];

        foreach ($items as $item) {
            if ($item->origin === ItemOrigin::Results && $item->highest_level !== null) {
                $analysisRows[] = ['level' => $item->highest_level, 'written' => trim($item->analysis ?? '') !== ''];
            }
        }

        $overdue = array_any($actions, fn (ProgramAction $action): bool => ProgramProgress::isOverdue($action->status, $action->due_on->toDateString(), $today));

        return [
            'id' => $program->id,
            'highestLevel' => self::level($program->highest_level),
            'responsible' => $program->responsible_name === null ? null : ['name' => $program->responsible_name, 'userId' => $program->responsible_user_id],
            'state' => self::labeled(ProgramProgress::programState($rows, $overdue)),
            'requirements' => TableChecklist::for($program->highest_level, $kinds, $analysisRows),
            'meter' => ProgramProgress::meter(array_values(array_map(fn (ProgramAction $action): ActionStatus => $action->status, $actions))),
            'items' => array_map(fn (array $row): array => [
                'id' => $row['item']->id,
                'scope' => $row['item']->scope->value,
                'label' => $row['item']->label,
                'category' => $row['item']->category_key,
                'level' => self::level($row['item']->highest_level),
                'manual' => $row['item']->origin === ItemOrigin::Manual,
                'state' => self::labeled($row['state']),
                'analysis' => $row['item']->analysis,
                'missingAnalysis' => in_array($row['item']->highest_level, [RiskLevel::High, RiskLevel::VeryHigh], true) && trim($row['item']->analysis ?? '') === '',
                'followUp' => self::followUp($row['item'], $later),
            ], $rows),
        ];
    }

    /**
     * @param  list<CampaignRecord>  $later
     * @return array<string, mixed>
     */
    private static function followUp(ProgramItem $item, array $later): array
    {
        $evaluation = array_find($later, fn (CampaignRecord $record): bool => $record->id === $item->follow_up_campaign_id);

        return [
            'dueOn' => $item->follow_up_due_on?->toDateString(),
            'dueOnLabel' => $item->follow_up_due_on === null ? null : ProgramClock::day($item->follow_up_due_on),
            'doneOn' => $item->follow_up_done_on?->toDateString(),
            'doneOnLabel' => $item->follow_up_done_on === null ? null : ProgramClock::day($item->follow_up_done_on),
            'result' => $item->follow_up_result,
            'evaluation' => $evaluation === null ? null : self::evaluation($evaluation),
        ];
    }

    /**
     * @return array{id: string, label: string}
     */
    private static function evaluation(CampaignRecord $record): array
    {
        return ['id' => $record->id, 'label' => 'Cerrada el '.$record->closedOn];
    }

    /**
     * The closed evaluations of the work center that closed after this one, the latest first.
     *
     * @return list<CampaignRecord>
     */
    private function laterEvaluations(CampaignRecord $campaign): array
    {
        $closed = $this->campaigns->closedAt($campaign->workCenterId);
        $own = array_find_key($closed, fn (CampaignRecord $record): bool => $record->id === $campaign->id);

        return $own === null ? [] : array_slice($closed, 0, $own);
    }

    /**
     * The day the next evaluation of the center is due (num. 7.9), for the date of a follow-up: only while this Programa's
     * evaluation is the latest and the next one has not started.
     *
     * @return array{date: string, label: string}|null
     */
    private function followUpSuggestion(string $workCenterId): ?array
    {
        $standing = $this->schedule->forWorkCenters([$workCenterId])[$workCenterId] ?? null;

        return $standing !== null && in_array($standing->state, ['due', 'overdue'], true) && $standing->date !== null && $standing->day !== null
            ? ['date' => $standing->date, 'label' => $standing->day]
            : null;
    }

    /**
     * What HR can still add: the places of the guide that are not rows yet.
     *
     * @param  list<ProgramItem>  $items
     * @return list<array{scope: string, key: string, label: string, category: string|null}>
     */
    private function manualOptions(string $guide, array $items): array
    {
        $options = [];

        foreach ($this->catalog->groups($guide) as $group) {
            if (! array_any($items, fn (ProgramItem $item): bool => $item->scope === $group['scope'] && $item->scope_key === $group['key'])) {
                $options[] = ['scope' => $group['scope']->value, 'key' => $group['key'], 'label' => $group['label'], 'category' => $group['category']];
            }
        }

        return $options;
    }

    /**
     * @return array{value: string, label: string}
     */
    private static function labeled(RowState|ProgramState $state): array
    {
        return ['value' => $state->value, 'label' => $state->label()];
    }

    /**
     * The actions of the Programa, a page at a time, the soonest due first.
     *
     * @param  list<AreaRisk>  $areas  the areas Results shows
     * @param  array{item: string|null, status: string|null}  $filters
     * @param  list<ProgramItem>  $items
     * @param  (Closure(string, string): string)|null  $evidenceUrl
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function actions(Program $program, array $areas, array $filters, array $items, ?Closure $evidenceUrl): LengthAwarePaginator
    {
        $labels = [];

        foreach ($items as $item) {
            $labels[$item->id] = $item->label;
        }

        $shown = [];

        foreach ($areas as $area) {
            $shown[$area->id] = $area->name;
        }

        return ProgramAction::query()
            ->where('program_id', $program->id)
            ->when($filters['item'] !== null, fn ($query) => $query->where('item_id', $filters['item']))
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->with(['areas', 'progress'])
            ->orderBy('due_on')
            ->orderBy('id')
            ->paginate(self::ACTIONS_PER_PAGE, pageName: 'actions')
            ->withQueryString()
            ->through(fn (ProgramAction $action): array => $this->actionRow($action, $shown, $labels, $evidenceUrl));
    }

    /**
     * @param  array<string, string>  $shown  names of the areas Results shows, by id
     * @param  array<string, string>  $labels  label of each row of the Programa, by id
     * @param  (Closure(string, string): string)|null  $evidenceUrl  where a step's file opens (action id, step id); null for who may not open files
     * @return array<string, mixed>
     */
    private function actionRow(ProgramAction $action, array $shown, array $labels, ?Closure $evidenceUrl): array
    {
        $attached = $action->areas->map(fn (ActionArea $area): string => $area->area_id)->all();
        $visible = array_values(array_filter($attached, fn (string $id): bool => isset($shown[$id])));

        return [
            'id' => $action->id,
            'itemId' => $action->item_id,
            'itemLabel' => $labels[$action->item_id] ?? '',
            'level' => ['value' => $action->level->value, 'label' => $action->level->label()],
            'kind' => ['value' => $action->kind->value, 'label' => $action->kind->label()],
            'title' => $action->title,
            'description' => $action->description,
            'subjects' => ['value' => $action->subjects->value, 'label' => $action->subjects->label()],
            'areas' => array_map(fn (string $id): array => ['id' => $id, 'name' => $shown[$id]], $visible),
            'hiddenAreas' => count($attached) - count($visible),
            'startsOn' => $action->starts_on?->toDateString(),
            'startsOnLabel' => $action->starts_on === null ? null : ProgramClock::day($action->starts_on),
            'dueOn' => $action->due_on->toDateString(),
            'dueOnLabel' => ProgramClock::day($action->due_on),
            'responsible' => ['name' => $action->responsible_name, 'userId' => $action->responsible_user_id],
            'professional' => $action->professional === null ? null : ['value' => $action->professional->value, 'label' => $action->professional->label()],
            'status' => ['value' => $action->status->value, 'label' => $action->status->label()],
            'overdue' => ProgramProgress::isOverdue($action->status, $action->due_on->toDateString(), ProgramClock::today()),
            'history' => $action->progress->sortBy(fn (ProgressEntry $entry): string => $entry->recorded_on->toDateString().'|'.$entry->created_at->format('c').'|'.$entry->id)->map(fn (ProgressEntry $entry): array => [
                'onLabel' => ProgramClock::day($entry->recorded_on),
                'status' => ['value' => $entry->status->value, 'label' => $entry->status->label()],
                'note' => $entry->note,
                'hasEvidence' => $entry->evidence_path !== null,
                // Only whoever writes the Programa opens a file: an attendance list carries names.
                'evidenceUrl' => $evidenceUrl !== null && $entry->evidence_path !== null ? $evidenceUrl($action->id, $entry->id) : null,
            ])->values()->all(),
            'completedOnLabel' => $action->completed_on === null ? null : ProgramClock::day($action->completed_on),
        ];
    }

    /**
     * Each area shown, with how many of its questionnaires are in Medio or more in each row (what Resultados shows when
     * filtered by that area).
     *
     * @param  list<AreaRisk>  $areas
     * @param  list<ProgramItem>  $items
     * @return list<array{id: string, name: string, answered: int, atRisk: array<string, int>}>
     */
    private function areaOptions(array $areas, array $items): array
    {
        return array_map(fn (AreaRisk $area): array => [
            'id' => $area->id,
            'name' => $area->name,
            'answered' => $area->answered,
            'atRisk' => array_combine(array_map(fn (ProgramItem $item): string => $item->id, $items), array_map(fn (ProgramItem $item): int => self::fromMedium(self::levelsOf($area, $item)), $items)),
        ], $areas);
    }

    /**
     * @return list<int>
     */
    private static function levelsOf(AreaRisk $area, ProgramItem $item): array
    {
        $groups = match ($item->scope) {
            ItemScope::Final => [new RiskGroup('final', '', null, $area->final)],
            ItemScope::Category => $area->categories,
            ItemScope::Domain => $area->domains,
        };

        return array_find($groups, fn (RiskGroup $group): bool => $group->key === $item->scope_key)->levels ?? [];
    }

    /**
     * @param  list<int>  $levels  Nulo to Muy alto
     */
    private static function fromMedium(array $levels): int
    {
        return array_sum(array_slice($levels, 2));
    }

    /**
     * @param  list<ProgramItem>  $items
     */
    private function itemFilter(?string $id, array $items): ?string
    {
        return $id !== null && Str::isUuid($id) && array_any($items, fn (ProgramItem $item): bool => $item->id === $id) ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function catalog(): array
    {
        return [
            'kinds' => array_map(fn (ActionKind $kind): array => ['value' => $kind->value, 'label' => $kind->label(), 'level' => $kind->defaultLevel()?->value], ActionKind::cases()),
            'levels' => array_map(fn (ActionLevel $level): array => ['value' => $level->value, 'label' => $level->label()], ActionLevel::cases()),
            'professionals' => array_map(fn (Professional $professional): array => ['value' => $professional->value, 'label' => $professional->label()], Professional::cases()),
        ];
    }

    /** Final score first, then categories, then domains; by label inside each. */
    private static function order(ProgramItem $item): string
    {
        $place = match ($item->scope) {
            ItemScope::Final => 0,
            ItemScope::Category => 1,
            ItemScope::Domain => 2,
        };

        return "{$place}|{$item->label}";
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private static function level(?RiskLevel $level): ?array
    {
        return $level === null ? null : ['value' => $level->value, 'label' => $level->label()];
    }
}
