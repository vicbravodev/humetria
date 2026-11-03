<?php

declare(strict_types=1);

namespace Database\Factories\Interventions;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Interventions\Actions\CancelAction;
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
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Enums\Professional;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\ProgramClock;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\AreaRisk;
use Carbon\CarbonImmutable;

/**
 * A Programa de intervención as a company would have it some weeks after closing an evaluation, for the demo seeder: drafted
 * from the results, with someone responsible, actions of the three levels (done, running, overdue, planned and cancelled),
 * steps in their log and an analysis. It goes through the real actions, so
 * what the demo shows is what a company gets. No email, no file.
 */
final class ProgramScenario
{
    private CarbonImmutable $today;

    public function __construct()
    {
        $this->today = CarbonImmutable::parse(ProgramClock::today());
    }

    /**
     * Only the draft the system makes when an evaluation closes: its rows from the results, with nothing done yet.
     */
    public function draft(string $workCenterId, int $ownerId): ?Program
    {
        $record = $this->evaluation($workCenterId);

        return $record === null ? null : resolve(CreateProgram::class)->handle($record->id, $ownerId);
    }

    /**
     * Builds the Programa of the closed evaluation of a work center; the company must be the current one.
     */
    public function run(string $workCenterId, int $ownerId): ?Program
    {
        $record = $this->evaluation($workCenterId);
        $program = $this->draft($workCenterId, $ownerId);

        if ($record === null || $program === null) {
            return null;
        }

        resolve(SaveProgramResponsible::class)->handle($program->id, $ownerId, null);
        $areas = array_map(fn (AreaRisk $area): string => $area->id, resolve(RiskResults::class)->areaGroups($record));
        $rows = ProgramItem::query()->where('program_id', $program->id)->orderBy('scope')->orderBy('scope_key')->get();
        $final = $rows->firstWhere('scope', ItemScope::Final) ?? $rows->first();

        if ($final === null) {
            return $program;
        }

        $picked = array_slice(array_values($rows->where('id', '!=', $final->id)->all()), 0, 4);

        if (count($picked) !== 4) {
            return $program;
        }

        $this->actions($final, $picked, $areas, $ownerId);
        resolve(SaveAnalysis::class)->handle($final->id, 'El resultado final se concentra en pocas categorías; las acciones atienden primero la política y la carga de trabajo.');
        resolve(SaveAnalysis::class)->handle($picked[0]->id, 'Hay un patrón claro en el centro: conviene revisar la organización del trabajo antes de pensar en capacitación.');

        return $program;
    }

    /**
     * @param  array{ProgramItem, ProgramItem, ProgramItem, ProgramItem}  $others
     * @param  list<string>  $areas  the areas Results shows
     */
    private function actions(ProgramItem $final, array $others, array $areas, int $ownerId): void
    {
        $save = resolve(SaveAction::class);
        $record = resolve(RecordProgress::class);
        $done = $save->handle($this->data($final, ActionKind::PolicyReview, ActionLevel::Organizational, 'Revisar la política de prevención', -40, -15), $ownerId);
        $record->handle($done->id, ActionStatus::InProgress, $this->day(-35), 'Se convocó al comité para revisar la política.', $ownerId);
        $record->handle($done->id, ActionStatus::Done, $this->day(-16), 'La política se actualizó y se aprobó.', $ownerId);

        $running = $save->handle($this->data($final, ActionKind::PolicyDissemination, ActionLevel::Organizational, 'Difundir la política en los tres turnos', -10, 12), $ownerId);
        $record->handle($running->id, ActionStatus::InProgress, $this->day(-5), 'Se publicó en los tableros y se envió por correo.', $ownerId);

        $overdue = $save->handle($this->data($others[0], ActionKind::WorkOrganization, ActionLevel::Organizational, 'Redistribuir las cargas de trabajo por turno', -45, -12), $ownerId);
        $record->handle($overdue->id, ActionStatus::InProgress, $this->day(-30), 'Falta definir el nuevo reparto con producción.', $ownerId);

        $campaign = $this->data($others[1], ActionKind::AwarenessCampaign, ActionLevel::Group, 'Campaña de sensibilización sobre el trato respetuoso', 5, 40);
        $save->handle($campaign, $ownerId);
        $save->handle($this->areaData($others[2], $areas, $campaign), $ownerId);
        $save->handle($this->data($others[3], ActionKind::SpecificAssessment, ActionLevel::Group, 'Evaluación específica de la carga mental', 15, 60), $ownerId);

        $clinical = $this->data($others[0], ActionKind::ClinicalCare, ActionLevel::Individual, 'Atención psicológica a solicitud, con convenio externo', 0, 90);
        $save->handle(new ActionData(null, $others[0]->id, ActionKind::ClinicalCare, ActionLevel::Individual, $clinical->title, $clinical->description, ActionSubjects::Center, [], $clinical->startsOn, $clinical->dueOn, 'Dra. Laura Méndez', null, Professional::Psychologist), $ownerId);

        $cancelled = $save->handle($this->data($others[3], ActionKind::Training, ActionLevel::Group, 'Curso de manejo del estrés (sustituido por otra medida)', -20, 10), $ownerId);
        resolve(CancelAction::class)->handle($cancelled->id, 'Se sustituyó por la campaña de sensibilización.', $ownerId);
    }

    private function evaluation(string $workCenterId): ?CampaignRecord
    {
        return array_find(resolve(CampaignRecords::class)->closedAt($workCenterId), fn (CampaignRecord $candidate): bool => $candidate->kind === 'nom035_evaluation');
    }

    private function data(ProgramItem $item, ActionKind $kind, ActionLevel $level, string $title, int $startsInDays, int $dueInDays): ActionData
    {
        return new ActionData(
            id: null,
            itemId: $item->id,
            kind: $kind,
            level: $level,
            title: $title,
            description: 'Acción del Programa de intervención de ejemplo, sin nombres de personas.',
            subjects: ActionSubjects::Center,
            areaIds: [],
            startsOn: $this->day($startsInDays),
            dueOn: $this->day($dueInDays),
            responsibleName: null,
            responsibleUserId: null,
            professional: null,
        );
    }

    /**
     * The same action aimed at the areas Results shows, in another row.
     *
     * @param  list<string>  $areas
     */
    private function areaData(ProgramItem $item, array $areas, ActionData $from): ActionData
    {
        return new ActionData(null, $item->id, ActionKind::Training, ActionLevel::Group, 'Taller de comunicación con jefaturas', $from->description, $areas === [] ? ActionSubjects::Center : ActionSubjects::Areas, array_slice($areas, 0, 2), $from->startsOn, $from->dueOn, null, null, null);
    }

    private function day(int $offset): string
    {
        return $this->today->addDays($offset)->toDateString();
    }
}
