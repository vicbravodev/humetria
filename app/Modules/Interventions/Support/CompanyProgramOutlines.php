<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Results\Contracts\ProgramOutlines;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\ProgramOutline;
use App\Modules\Results\Data\ProgramOutlineAction;
use App\Modules\Results\Data\ProgramOutlineItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The Programa as the 7.7 report (num. 7.7 g) and the results page read it, through Results' port. It writes words, not
 * records: labels and days as people read them, the areas Results shows (never one that stays hidden, and not even how many
 * do) and the names of whoever answers. No file, no note of a step, no description, no id of a person.
 */
final readonly class CompanyProgramOutlines implements ProgramOutlines
{
    public function __construct(private CampaignRecords $campaigns, private RiskResults $results) {}

    public function forCampaign(string $campaignId): ?ProgramOutline
    {
        $program = Str::isUuid($campaignId) ? Program::query()->where('campaign_id', $campaignId)->first() : null;
        $campaign = $program === null ? null : $this->campaigns->find($campaignId);

        if ($program === null || $campaign === null) {
            return null;
        }

        $items = array_values(ProgramItem::query()->where('program_id', $program->id)->get()->all());
        $actions = array_values(ProgramAction::query()->where('program_id', $program->id)->with('areas')->orderBy('due_on')->orderBy('id')->get()->all());
        $standing = ProgramProgress::standing($items, $actions, ProgramClock::today());
        $shown = [];

        // Only when an action is for areas: the areas Results shows today, by id.
        foreach (array_any($actions, fn (ProgramAction $action): bool => $action->subjects === ActionSubjects::Areas) ? $this->results->areaGroups($campaign) : [] as $area) {
            $shown[$area->id] = $area->name;
        }

        usort($items, fn (ProgramItem $a, ProgramItem $b): int => [$a->scope->position(), $a->label] <=> [$b->scope->position(), $b->label]);

        return new ProgramOutline(
            state: $standing['state']->label(),
            stateKey: $standing['state']->value,
            meter: $standing['meter'],
            highestLevel: $program->highest_level?->label(),
            responsible: $program->responsible_name,
            asOf: ProgramClock::day(CarbonImmutable::parse(ProgramClock::today())),
            items: array_map(fn (ProgramItem $item): ProgramOutlineItem => new ProgramOutlineItem(
                label: $item->label,
                scope: $item->scope->label(),
                level: $item->highest_level?->label(),
                analysis: $item->analysis,
                followUp: self::followUp($item),
                actions: array_values(array_map(fn (ProgramAction $action): ProgramOutlineAction => self::action($action, $shown), array_filter($actions, fn (ProgramAction $action): bool => $action->item_id === $item->id))),
            ), $items),
        );
    }

    /**
     * @param  array<string, string>  $shown  names of the areas Results shows, by id
     */
    private static function action(ProgramAction $action, array $shown): ProgramOutlineAction
    {
        return new ProgramOutlineAction(
            title: $action->title,
            kind: $action->kind->label(),
            level: $action->level->label(),
            subjects: self::subjects($action, $shown),
            startsOn: $action->starts_on === null ? null : ProgramClock::day($action->starts_on),
            dueOn: ProgramClock::day($action->due_on),
            responsible: $action->responsible_name,
            status: $action->status->label(),
            professional: $action->professional?->label(),
            overdue: ProgramProgress::isOverdue($action->status, $action->due_on->toDateString(), ProgramClock::today()),
        );
    }

    /**
     * @param  array<string, string>  $shown
     */
    private static function subjects(ProgramAction $action, array $shown): string
    {
        if ($action->subjects === ActionSubjects::Center) {
            return $action->subjects->label();
        }

        $names = array_values(array_filter(array_map(fn (ActionArea $area): ?string => $shown[$area->area_id] ?? null, $action->areas->all())));

        return $names === [] ? $action->subjects->label() : implode(', ', $names);
    }

    private static function followUp(ProgramItem $item): ?string
    {
        if ($item->follow_up_done_on !== null) {
            return 'Hecha el '.ProgramClock::day($item->follow_up_done_on).': '.($item->follow_up_result ?? '');
        }

        return $item->follow_up_due_on === null ? null : 'Programada para el '.ProgramClock::day($item->follow_up_due_on);
    }
}
