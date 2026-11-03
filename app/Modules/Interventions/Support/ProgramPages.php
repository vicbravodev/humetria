<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Enums\Professional;
use App\Modules\Interventions\Exceptions\ProgramUnavailable;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\AreaRisk;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Data\RiskGroup;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
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

    public function __construct(private RiskResults $results, private WorkCenters $workCenters, private Instruments $instruments, private Members $members) {}

    /**
     * @return array<string, mixed>
     */
    public function show(CampaignRecord $campaign, ?Program $program, bool $canManage, ?string $itemFilter = null, ?string $statusFilter = null): array
    {
        $guide = array_find($campaign->instruments, fn (string $key): bool => $key !== 'nom035.guide-i');
        $areas = $program === null ? [] : $this->results->areaGroups($campaign);
        $items = $program === null ? [] : array_values(ProgramItem::query()->where('program_id', $program->id)->get()->all());
        $filters = ['item' => $this->itemFilter($itemFilter, $items), 'status' => ActionStatus::tryFrom($statusFilter ?? '')?->value];

        return [
            'campaign' => [
                'id' => $campaign->id,
                'workCenter' => $this->workCenters->profile($campaign->workCenterId)->name ?? '',
                'kindLabel' => $campaign->kindLabel,
                'closedOn' => $campaign->closedOn,
                'guide' => $guide === null ? null : $this->instruments->definition($guide)->shortTitle,
            ],
            'requirement' => $program === null ? $this->requirement($campaign) : null,
            'program' => $program === null ? null : $this->program($program, $items),
            'actions' => $program === null ? null : $this->actions($program, $areas, $filters, $items),
            'filters' => $filters,
            'catalog' => $canManage ? self::catalog() : null,
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
     * @return array<string, mixed>
     */
    private function program(Program $program, array $items): array
    {
        usort($items, fn (ProgramItem $a, ProgramItem $b): int => self::order($a) <=> self::order($b));

        return [
            'id' => $program->id,
            'highestLevel' => self::level($program->highest_level),
            'responsible' => $program->responsible_name === null ? null : ['name' => $program->responsible_name, 'userId' => $program->responsible_user_id],
            'items' => array_map(fn (ProgramItem $item): array => [
                'id' => $item->id,
                'scope' => $item->scope->value,
                'label' => $item->label,
                'category' => $item->category_key,
                'level' => self::level($item->highest_level),
                'manual' => $item->origin === ItemOrigin::Manual,
            ], $items),
        ];
    }

    /**
     * The actions of the Programa, a page at a time, the soonest due first.
     *
     * @param  list<AreaRisk>  $areas  the areas Results shows
     * @param  array{item: string|null, status: string|null}  $filters
     * @param  list<ProgramItem>  $items
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function actions(Program $program, array $areas, array $filters, array $items): LengthAwarePaginator
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
            ->with('areas')
            ->orderBy('due_on')
            ->orderBy('id')
            ->paginate(self::ACTIONS_PER_PAGE, pageName: 'actions')
            ->withQueryString()
            ->through(fn (ProgramAction $action): array => $this->actionRow($action, $shown, $labels));
    }

    /**
     * @param  array<string, string>  $shown  names of the areas Results shows, by id
     * @param  array<string, string>  $labels  label of each row of the Programa, by id
     * @return array<string, mixed>
     */
    private function actionRow(ProgramAction $action, array $shown, array $labels): array
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
