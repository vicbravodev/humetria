<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Instruments\Data\ConditionDefinition;
use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Enums\BipedestationType;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What the Ley Silla pages send to the browser: the list of centers with their declared sector, the page of one center
 * with its positions, and the page of one position. Nothing here names a person except the people of a position, which only
 * who may assess sees (the viewer gets counts).
 */
final readonly class SeatingPages
{
    private const int CENTERS_PER_PAGE = 12;

    private const int POSITIONS_PER_PAGE = 12;

    private const int PEOPLE_PER_PAGE = 15;

    /** The three results of Figura 1 with the DOF's own words for each. */
    private const array PLACEMENTS = [
        'at_workstation' => ['A', 'Se debe proporcionar el asiento o silla con respaldo a la persona trabajadora con las características determinadas para su espacio de trabajo.'],
        'nearby' => ['B', 'Se debe proporcionar el asiento o silla con respaldo a la persona trabajadora en un lugar determinado cerca de su lugar de trabajo.'],
        'within_center' => ['C', 'Se debe proporcionar el asiento o silla con respaldo a la persona trabajadora en un lugar determinado dentro del centro de trabajo.'],
    ];

    /** The conditions a position answers for its people; the footwear and the discomfort are each person's own. */
    private const array POSITION_CONDITIONS = ['standing_time', 'posture_change', 'surface', 'movement_space', 'pauses'];

    public function __construct(private VisibleCenters $visible, private Roster $roster, private SeatingAssessment $assessment, private CenterBoard $board) {}

    /**
     * @return array<string, mixed>
     */
    public function index(int $userId): array
    {
        $page = $this->visible->page($userId, self::CENTERS_PER_PAGE);
        // The items of a page are already a list.
        $ids = array_values(array_map(fn (WorkCenterSummary $center): string => $center->id, $page->items())); // @pest-mutate-ignore: UnwrapArrayValues
        $profiles = SeatingProfile::query()->whereIn('work_center_id', $ids)->get(['work_center_id', 'sector'])->keyBy('work_center_id');

        return ['centers' => $page->through(function (WorkCenterSummary $center) use ($profiles): array {
            $sector = $profiles->get($center->id)?->sector;

            return [
                'id' => $center->id,
                'name' => $center->name,
                'activity' => $center->mainActivity,
                'sector' => $sector?->value,
                'sectorLabel' => $sector?->label(),
            ];
        })];
    }

    /**
     * @return array<string, mixed>
     */
    public function center(WorkCenterSummary $center): array
    {
        $profile = SeatingProfile::query()->where('work_center_id', $center->id)->first();

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name, 'activity' => $center->mainActivity],
            'profile' => $profile === null ? null : [
                'sector' => $profile->sector->value,
                'sectorLabel' => $profile->sector->label(),
                'noStandingPositions' => $profile->no_standing_positions,
                'declaredOn' => $profile->declared_on->toDateString(),
                'note' => $profile->note,
            ],
            'sectors' => array_map(fn (Sector $sector): array => ['value' => $sector->value, 'label' => $sector->label()], Sector::cases()),
            'canManage' => Gate::allows('manage-seating'),
            'canAssess' => Gate::allows('assess-seating'),
            'positions' => $this->positions($center->id),
            ...$this->board->for($center->id),
            'tabs' => $this->tabs($center->id),
        ];
    }

    /**
     * The parts of the center the person may open, with how many records each holds: seats and positions for whoever sees the
     * center, the rest for who assesses, the survey for who manages.
     *
     * @return list<array{key: string, label: string, count: int|null}>
     */
    private function tabs(string $workCenterId): array
    {
        $counts = $this->board->counts($workCenterId);
        $assess = Gate::allows('assess-seating');
        $tabs = [['positions', 'Puestos', $counts['positions'], true], ['people', 'Personas', $counts['people'], $assess], ['inventory', 'Inventario', $counts['inventory'], true], ['reports', 'Reportes', $counts['reports'], $assess], ['plan', 'Plan', $counts['plan'], $assess], ['regulation', 'Reglamento', null, $assess], ['documents', 'Documentos', $counts['documents'], $assess], ['survey', 'Encuesta', null, Gate::allows('manage-seating')]];

        return array_values(array_map(fn (array $tab): array => ['key' => $tab[0], 'label' => $tab[1], 'count' => $tab[2]], array_filter($tabs, fn (array $tab): bool => $tab[3])));
    }

    /**
     * The page of a position; `$position` is null for the form of a new one. `$people` is null for who sees counts only.
     *
     * @param  LengthAwarePaginator<int, array{assignmentId: string, employeeId: string, name: string, area: string, startsOn: string}>|null  $people
     * @return array<string, mixed>
     */
    public function position(WorkCenterSummary $center, ?StandingPosition $position, ?LengthAwarePaginator $people): array
    {
        $industrial = SeatingProfile::query()->where('work_center_id', $center->id)->first()?->sector === Sector::Industrial;

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'position' => $position === null ? null : $this->positionRow($position),
            'types' => array_map(fn (BipedestationType $type): array => ['value' => $type->value, 'label' => $type->label()], BipedestationType::cases()),
            'industrial' => $industrial,
            'conditions' => $this->conditions(),
            'canManage' => Gate::allows('manage-seating'),
            'people' => $people,
        ];
    }

    /**
     * The people currently in a position, a page at a time; null for whoever may only see counts (the viewer).
     *
     * @return LengthAwarePaginator<int, array{assignmentId: string, employeeId: string, name: string, area: string, startsOn: string}>|null
     */
    public function people(StandingPosition $position): ?LengthAwarePaginator
    {
        if (! Gate::allows('assess-seating') && ! Gate::allows('manage-seating')) {
            return null;
        }

        $page = PositionAssignment::query()->where('position_id', $position->id)->whereNull('ends_on')->orderBy('starts_on')->orderBy('id')->paginate(self::PEOPLE_PER_PAGE)->withQueryString();
        $people = $this->roster->findMany(array_values(array_map(fn (PositionAssignment $assignment): string => $assignment->employee_id, $page->items())));

        return $page->through(fn (PositionAssignment $assignment): array => [
            'assignmentId' => $assignment->id,
            'employeeId' => $assignment->employee_id,
            'name' => array_key_exists($assignment->employee_id, $people) ? $people[$assignment->employee_id]->name : '',
            'area' => array_key_exists($assignment->employee_id, $people) ? $people[$assignment->employee_id]->areaName : '',
            'startsOn' => $assignment->starts_on->toDateString(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{id: string, name: string, typeLabel: string, placement: string|null, placementLetter: string|null, placementText: string|null, people: int}>
     */
    private function positions(string $workCenterId): LengthAwarePaginator
    {
        $page = StandingPosition::query()->where('work_center_id', $workCenterId)->orderBy('name')->orderBy('id')->paginate(self::POSITIONS_PER_PAGE, pageName: 'positions')->withQueryString();
        $ids = array_values(array_map(fn (StandingPosition $position): string => $position->id, $page->items()));
        $counts = PositionAssignment::query()->whereIn('position_id', $ids)->whereNull('ends_on')->pluck('position_id')->countBy();

        return $page->through(fn (StandingPosition $position): array => [
            'id' => $position->id,
            'name' => $position->name,
            'typeLabel' => $position->bipedestation_type->label(),
            ...$this->placement($position->placement),
            'people' => $counts->get($position->id, 0),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function positionRow(StandingPosition $position): array
    {
        return [
            'id' => $position->id,
            'name' => $position->name,
            'tasks' => $position->tasks,
            'type' => $position->bipedestation_type->value,
            'continuousMinutes' => $position->continuous_minutes,
            'canWorkSeated' => $position->can_work_seated,
            'hasSpaceForSeat' => $position->has_space_for_seat,
            'needsToBeNearby' => $position->needs_to_be_nearby,
            ...$this->placement($position->placement),
            'figureOneApplies' => $position->figure_one_applies,
            'maxPeopleAtOnce' => $position->max_people_at_once,
            'areaConditions' => $position->area_conditions,
            'tools' => $position->tools,
            'physicalLoad' => $position->physical_load,
            'attentionLevel' => $position->attention_level,
            'defaultStandingTime' => $position->default_standing_time,
            'defaultPostureChange' => $position->default_posture_change,
            'defaultSurface' => $position->default_surface,
            'defaultMovementSpace' => $position->default_movement_space,
            'defaultPauses' => $position->default_pauses,
            'natureAllows' => $position->nature_allows,
            'natureReason' => $position->nature_reason,
        ];
    }

    /**
     * The DOF's options for the conditions a position answers, to fill the defaults of its people.
     *
     * @return list<array{key: string, letter: string, title: string, options: list<array{points: int, text: string}>}>
     */
    private function conditions(): array
    {
        $own = array_filter($this->assessment->definition()->conditions, fn (ConditionDefinition $condition): bool => in_array($condition->key, self::POSITION_CONDITIONS, true));

        return array_values(array_map(fn (ConditionDefinition $condition): array => [
            'key' => $condition->key,
            'letter' => $condition->letter,
            'title' => $condition->title,
            'options' => array_map(fn (int $points, string $text): array => ['points' => $points, 'text' => $text], array_keys($condition->options), array_values($condition->options)),
        ], $own));
    }

    /**
     * @return array{placement: string|null, placementLetter: string|null, placementText: string|null}
     */
    private function placement(?SeatPlacement $placement): array
    {
        $result = $placement === null ? null : self::PLACEMENTS[$placement->value];

        return ['placement' => $placement?->value, 'placementLetter' => $result[0] ?? null, 'placementText' => $result[1] ?? null];
    }
}
