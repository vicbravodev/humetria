<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Interventions\Enums\ProgramState;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The list of Programas of the company: how many are in each state, and the closed evaluations a page at a time, each with
 * where its Programa stands. The states come from three queries over the whole company, whatever the page, and from
 * ProgramProgress, the one place that derives them. Counts and states only: no text HR wrote.
 */
final readonly class ProgramList
{
    private const int PER_PAGE = 10;

    public function __construct(private CampaignRecords $campaigns, private WorkCenters $workCenters) {}

    /**
     * @return array<string, mixed>
     */
    public function page(): array
    {
        $standing = $this->standing();
        $summary = array_fill_keys(array_map(fn (ProgramState $state): string => $state->value, ProgramState::cases()), 0);

        foreach ($standing as $program) {
            $summary[$program['state']->value]++;
        }

        return [
            'summary' => $summary,
            'evaluations' => $this->evaluations($standing),
        ];
    }

    /**
     * @param  array<string, array{state: ProgramState, meter: array{done: int, total: int}, level: string|null}>  $standing  by evaluation
     * @return LengthAwarePaginator<int, array{id: string, workCenter: string, closedOn: string|null, applies: bool, program: array{state: array{value: string, label: string}, meter: array{done: int, total: int}}|null}>
     */
    private function evaluations(array $standing): LengthAwarePaginator
    {
        $page = $this->campaigns->closedEvaluations(self::PER_PAGE);
        // One query for every name, however many centers the page has.
        $names = [];

        foreach ($this->workCenters->all() as $center) {
            $names[$center->id] = $center->name;
        }

        return $page->through(function (CampaignRecord $record) use ($standing, $names): array {
            $program = $standing[$record->id] ?? null;

            return [
                'id' => $record->id,
                'workCenter' => $names[$record->workCenterId] ?? '',
                'closedOn' => $record->closedOn,
                // Only an evaluation that applies a Guía II or III is scored by risk level: only it has a Programa to speak of.
                'applies' => array_any($record->instruments, fn (string $key): bool => $key !== 'nom035.guide-i'),
                'program' => $program === null ? null : ['state' => ['value' => $program['state']->value, 'label' => $program['state']->label()], 'meter' => $program['meter']],
            ];
        });
    }

    /**
     * Where each Programa stands, by the evaluation it comes from.
     *
     * @return array<string, array{state: ProgramState, meter: array{done: int, total: int}, level: string|null}>
     */
    private function standing(): array
    {
        $programs = Program::query()->get(['id', 'campaign_id', 'highest_level']);
        $actions = ProgramAction::query()->get(['id', 'program_id', 'item_id', 'status', 'due_on']);
        $items = ProgramItem::query()->get(['id', 'program_id', 'follow_up_due_on', 'follow_up_done_on']);
        $today = ProgramClock::today();
        $standing = [];

        foreach ($programs as $program) {
            $own = array_values($actions->where('program_id', $program->id)->all());
            $standingOf = ProgramProgress::standing(array_values($items->where('program_id', $program->id)->all()), $own, $today);

            $standing[$program->campaign_id] = ['state' => $standingOf['state'], 'meter' => $standingOf['meter'], 'level' => $program->highest_level?->value];
        }

        return $standing;
    }
}
