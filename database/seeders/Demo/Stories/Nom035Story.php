<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Campaigns\Actions\CloseCampaign;
use App\Modules\Campaigns\Actions\CreateEvaluationDraft;
use App\Modules\Campaigns\Actions\LaunchCampaign;
use App\Modules\Campaigns\Actions\SubmitSurvey;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\ItemDefinition;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\RecordProgress;
use App\Modules\Interventions\Actions\SaveAction;
use App\Modules\Interventions\Actions\SaveAnalysis;
use App\Modules\Interventions\Actions\SaveProgramResponsible;
use App\Modules\Interventions\Data\ActionData;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\AreaRisk;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;
use InvalidArgumentException;
use LogicException;

/**
 * NOM-035 at Alba del Bajío. Some six months ago every center took its first evaluation (everyone answered) and the plant's
 * night shift, «Producción turno 2», came out in Alto: overtime and a rotation that leaves no rest. HR drew up the Programa
 * de intervención (one action done, one running, one overdue and one yet to start). Twelve days ago a new evaluation opened
 * and about 60 % have answered; the people reserved for the door have their link pending. Every answer goes through the
 * person's link (SubmitSurvey), so the Guías II and III are stored with no key to anyone.
 */
final class Nom035Story implements DemoStory
{
    private const string NIGHT_SHIFT = 'Producción turno 2';

    /** The Guía III items of overtime and work brought home (domains work_day and work_family): the night shift's 4s. */
    private const array OVERTIME_ITEMS = [17, 18, 19, 20];

    /** Two people of the night shift whose Guía I sends them to a clinical assessment (GR.I: three "yes" in section III). */
    private const array SCREENED = ['plant-031' => [3, 9, 10, 11], 'plant-044' => [2, 7, 12, 13]];

    /** About 60 % of each center answers the open evaluation. */
    private const float OPEN_ANSWERED = 0.6;

    /** @var array<string, string> "guide.item.points" => the option that gives them, filled on first use. */
    private array $scale = [];

    public function __construct(
        private readonly CreateEvaluationDraft $draft,
        private readonly LaunchCampaign $launch,
        private readonly SubmitSurvey $submit,
        private readonly CloseCampaign $close,
        private readonly Instruments $instruments,
        private readonly CampaignRecords $campaigns,
        private readonly RiskResults $results,
        private readonly CreateProgram $createProgram,
        private readonly SaveProgramResponsible $responsible,
        private readonly SaveAction $saveAction,
        private readonly RecordProgress $progress,
        private readonly SaveAnalysis $analysis,
    ) {}

    public function tell(DemoContext $context): void
    {
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        $closed = [];

        foreach (array_keys(DemoRoster::CENTERS) as $center) {
            $closed[$center] = $this->evaluate($context, $center, -190, fn (DemoPerson $person): bool => true, first: true);
        }

        [$rotation, $overtime, $workshop] = DemoClock::at('-170 days', fn (): array => $this->program($context, $closed['plant'], $today));
        $step = fn (string $actionId, ActionStatus $to, int $day, string $note) => DemoClock::at("{$day} days", fn () => $this->progress->handle($actionId, $to, $today->addDays($day)->toDateString(), $note, $context->hr->id));
        $step($rotation, ActionStatus::InProgress, -160, 'Se acordó con supervisión una rotación fija por cuadrilla.');
        $step($rotation, ActionStatus::Done, -125, 'La nueva rotación arrancó en las cuatro cuadrillas.');
        $step($overtime, ActionStatus::InProgress, -95, 'Se fijó un tope de nueve horas extra por semana.');
        $step($overtime, ActionStatus::InProgress, -20, 'Las horas extra del turno 2 bajaron a la mitad; falta cubrir dos vacantes.');
        $step($workshop, ActionStatus::InProgress, -50, 'Se dieron dos de las cuatro sesiones; faltan fechas con el turno 2.');

        foreach (array_keys(DemoRoster::CENTERS) as $center) {
            $this->evaluate($context, $center, -12, $this->openAnswerers($center), first: false);
        }
    }

    /**
     * Launches the evaluation of a center on the given day, has the people `$answers` picks answer it over the next days,
     * and closes the first one two weeks after it opened (with its Programa drafted as the system does on closing).
     *
     * @param  callable(DemoPerson): bool  $answers
     */
    private function evaluate(DemoContext $context, string $center, int $day, callable $answers, bool $first): Campaign
    {
        $campaign = DemoClock::at("{$day} days", function () use ($context, $center): Campaign {
            $campaign = $this->draft->handle($context->center($center), Population::All, $context->hr->id);
            $this->launch->handle($campaign, CarbonImmutable::now(DemoClock::TIMEZONE)->addDays(30)->startOfDay());
            $this->markSent($campaign);

            return $campaign;
        });

        $people = $this->peopleById();
        $participants = Participant::query()->where('campaign_id', $campaign->id)->with('instruments')->get()
            ->sortBy(fn (Participant $participant): string => DemoRoster::sortKey($people[$participant->employee_id]->key))->values();
        $risk = array_find($campaign->instruments, fn (string $key): bool => $key !== 'nom035.guide-i');

        foreach ($participants as $i => $participant) {
            $person = $people[$participant->employee_id];

            if (! $answers($person)) {
                continue;
            }

            DemoClock::at(($day + 1 + $i % 11).' days', function () use ($participant, $person, $risk, $first, $i): void {
                $token = $participant->surveyToken();
                $this->submit->handle($token, 'nom035.guide-i', $this->guideOne($first ? self::SCREENED[$person->key] ?? [] : []));

                if ($risk !== null) {
                    $this->submit->handle($token, $risk, $this->riskAnswers($risk, $person, $first, $i));
                }
            });
        }

        if ($first) {
            DemoClock::at(($day + 14).' days', function () use ($campaign, $risk): void {
                $this->close->handle(Campaign::query()->findOrFail($campaign->id), acknowledgedShortfall: true);

                if ($risk !== null) {
                    $this->createProgram->handle($campaign->id, null);
                }
            });
        }

        return $campaign;
    }

    /** The invitation job would store each link's hash and mark it sent; it runs on the queue, so the story does it now. */
    private function markSent(Campaign $campaign): void
    {
        foreach (Participant::query()->where('campaign_id', $campaign->id)->get() as $participant) {
            $participant->update(['token_hash' => Participant::hashToken($participant->surveyToken()), 'invited_at' => now(), 'invitation_status' => InvitationStatus::Sent]);
        }
    }

    /**
     * Who answers the open evaluation: of the center's people not reserved for the door, as many as make 60 % of the
     * center, spread evenly over its areas.
     *
     * @return callable(DemoPerson): bool
     */
    private function openAnswerers(string $center): callable
    {
        $people = array_values(array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->centerKey === $center));
        $free = array_values(array_filter($people, fn (DemoPerson $person): bool => ! $person->reservedForDoor));
        $quota = (int) round(count($people) * self::OPEN_ANSWERED);
        $chosen = [];

        foreach ($free as $i => $person) {
            if (intdiv(($i + 1) * $quota, count($free)) > intdiv($i * $quota, count($free))) {
                $chosen[$person->key] = true;
            }
        }

        return fn (DemoPerson $person): bool => isset($chosen[$person->key]);
    }

    /**
     * A complete Guía I: "no" to the six events unless `$yes` says otherwise, and then the whole guide.
     *
     * @param  list<int>  $yes
     * @return array<int, string>
     */
    private function guideOne(array $yes): array
    {
        $answers = [];

        foreach (range(1, $yes === [] ? 6 : 20) as $number) {
            $answers[$number] = in_array($number, $yes, true) ? 'yes' : 'no';
        }

        return $answers;
    }

    /**
     * A complete Guía II or III scoring a total that depends on where the person works: the night shift, in the first
     * evaluation, between Alto and Muy alto with the overtime items at their worst; the rest of the plant up to Medio; the
     * other centers lower. The open evaluation shows the night shift better. The points are spread evenly over the items.
     *
     * @return array<array-key, string>
     */
    private function riskAnswers(string $guide, DemoPerson $person, bool $first, int $i): array
    {
        $nightShift = $person->centerKey === 'plant' && $person->area === self::NIGHT_SHIFT;
        [$from, $to] = match (true) {
            $nightShift && $first => [100, 150],
            $nightShift => [55, 95],
            $person->centerKey === 'plant' => [30, 90],
            $guide === 'nom035.guide-ii' => [10, 60],
            default => [15, 80],
        };
        // A shuffle of the range by position, so no area gets only its low or its high end.
        $total = $from + intdiv(($to - $from) * (($i * 7) % 11), 10);
        $supervises = in_array($person->key, array_map(fn (DemoPerson $other): ?string => $other->reportsTo, DemoRoster::people()), true);
        $items = [];
        $gated = [];

        foreach ($this->instruments->definition($guide)->sections as $section) {
            $numbers = array_map(fn (ItemDefinition $item): int => $item->number, $section->items);
            $section->gate === null ? array_push($items, ...$numbers) : $gated[$section->gate->key] = $numbers;
        }

        $items = [...$items, ...($supervises ? $gated['supervises'] ?? [] : [])];
        $hot = $nightShift && $first ? array_values(array_intersect(self::OVERTIME_ITEMS, $items)) : [];
        $rest = array_values(array_diff($items, $hot));
        $left = $total - 4 * count($hot);
        $answers = [];

        foreach ($hot as $number) {
            $answers[$number] = $this->option($guide, $number, 4);
        }

        foreach ($rest as $position => $number) {
            $answers[$number] = $this->option($guide, $number, intdiv($left, count($rest)) + ($position < $left % count($rest) ? 1 : 0));
        }

        $answers['serves_clients'] = 'no';
        $answers['supervises'] = $supervises ? 'yes' : 'no';

        return $answers;
    }

    private function option(string $guide, int $item, int $points): string
    {
        if (! isset($this->scale["{$guide}.1.0"])) {
            foreach ($this->instruments->definition($guide)->options as $option) {
                foreach ($this->instruments->definition($guide)->itemNumbers() as $number) {
                    $this->scale["{$guide}.{$number}.{$this->instruments->itemScore($guide, $number, $option['value'])}"] = $option['value'];
                }
            }
        }

        return $this->scale["{$guide}.{$item}.{$points}"] ?? throw new InvalidArgumentException("No option of {$guide} item {$item} gives {$points} points.");
    }

    /**
     * The plant's Programa, as HR draws it up: HR in charge, and on the night shift a new rotation, a cap on overtime and a
     * leadership workshop for its supervisors, and active breaks for the whole plant (yet to start).
     *
     * @return array{string, string, string} the ids of the rotation, the overtime cap and the workshop, whose steps come later
     */
    private function program(DemoContext $context, Campaign $campaign, CarbonImmutable $today): array
    {
        $program = Program::query()->where('campaign_id', $campaign->id)->firstOrFail();
        $this->responsible->handle($program->id, $context->hr->id, null);
        $record = $this->campaigns->find($campaign->id) ?? throw new LogicException('The plant evaluation has no record.');
        $nightShift = array_find($this->results->areaGroups($record), fn (AreaRisk $area): bool => $area->name === self::NIGHT_SHIFT)
            ?? throw new LogicException('The night shift is not shown in the results.');
        $workDay = $this->item($program, 'work_day');
        $final = $this->item($program, 'final');
        $this->analysis->handle($workDay->id, 'La jornada del turno 2 concentra el riesgo: horas extra casi diarias y una rotación que no deja descansar entre turnos.');

        $action = fn (ProgramItem $item, ActionKind $kind, ActionLevel $level, string $title, ?string $area, int $starts, int $due): string => $this->saveAction->handle(new ActionData(
            null, $item->id, $kind, $level, $title, 'Acción del Programa de intervención de la planta.',
            $area === null ? ActionSubjects::Center : ActionSubjects::Areas, $area === null ? [] : [$area],
            $today->addDays($starts)->toDateString(), $today->addDays($due)->toDateString(), null, $context->hr->id, null,
        ), $context->hr->id)->id;
        $action($final, ActionKind::AwarenessCampaign, ActionLevel::Group, 'Pausas activas y descanso entre turnos', null, 10, 60);

        return [
            $action($workDay, ActionKind::WorkOrganization, ActionLevel::Organizational, 'Rotación fija por cuadrilla en el turno 2', $nightShift->id, -165, -120),
            $action($workDay, ActionKind::WorkOrganization, ActionLevel::Organizational, 'Tope de horas extra y registro semanal por supervisor', $nightShift->id, -100, 30),
            $action($final, ActionKind::Training, ActionLevel::Group, 'Taller de liderazgo para los supervisores de producción', $nightShift->id, -60, -7),
        ];
    }

    private function item(Program $program, string $key): ProgramItem
    {
        return ProgramItem::query()->where('program_id', $program->id)->where('scope_key', $key)->firstOrFail();
    }

    /** @return array<string, DemoPerson> */
    private function peopleById(): array
    {
        $people = [];

        foreach (DemoRoster::people() as $person) {
            $people[DemoRoster::employeeId($person->key)] = $person;
        }

        return $people;
    }
}
