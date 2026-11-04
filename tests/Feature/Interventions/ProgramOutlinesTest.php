<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CancelAction;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\SaveAction;
use App\Modules\Interventions\Actions\SaveProgramResponsible;
use App\Modules\Interventions\Data\ActionData;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Interventions\Support\CompanyProgramOutlines;
use App\Modules\Results\Contracts\ProgramOutlines;
use App\Modules\Results\Data\ProgramOutline;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;

mutates(CompanyProgramOutlines::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create();
    // Producción and Ventas are shown; Compras and Legal add up to 5, so they stay hidden without dragging another one.
    [$this->campaign, $this->areas] = closedEvaluationInAreas($this->tenant, ['Producción' => 7, 'Ventas' => 6, 'Compras' => 3, 'Legal' => 2]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->final = ProgramItem::query()->where('program_id', $this->program?->id)->where('scope', 'final')->sole();
});

it('has no outline for an evaluation without a Programa, or of another company', function (): void {
    [$other] = closedEvaluationInAreas($this->tenant, ['Logística' => 6]);

    expect(resolve(ProgramOutlines::class)->forCampaign($other->id))->toBeNull()
        ->and(resolve(ProgramOutlines::class)->forCampaign('00000000-0000-4000-8000-000000000000'))->toBeNull();

    $stranger = Tenant::factory()->create();
    workIn($stranger);
    expect(resolve(ProgramOutlines::class)->forCampaign($this->campaign->id))->toBeNull();
});

it('writes the Programa as the report reads it: rows, levels, analysis and actions, dated as of today', function (): void {
    resolve(SaveProgramResponsible::class)->handle($this->program?->id ?? '', null, 'Dra. Laura Méndez');
    $member = memberOf($this->tenant, TenantRole::Hr);
    $member->update(['name' => 'Hugo RH']);
    resolve(App\Modules\Interventions\Actions\SaveAnalysis::class)->handle($this->final->id, 'La carga se concentra en producción.');
    resolve(SaveAction::class)->handle(new ActionData(null, $this->final->id, ActionKind::PolicyReview, ActionLevel::Organizational, 'Revisar la política', 'Texto largo de la descripción.', ActionSubjects::Areas, [$this->areas['Producción']->id], '2026-12-01', '2026-12-20', null, $member->id, null), $member->id);
    resolve(SaveAction::class)->handle(new ActionData(null, $this->final->id, ActionKind::ClinicalCare, ActionLevel::Individual, 'Atención psicológica a solicitud', 'Convenio.', ActionSubjects::Center, [], null, '2027-02-01', null, null, App\Modules\Interventions\Enums\Professional::Psychologist), $member->id);
    $this->final->update(['follow_up_due_on' => '2028-11-19']);

    $outline = resolve(ProgramOutlines::class)->forCampaign($this->campaign->id);

    expect($outline)->toBeInstanceOf(ProgramOutline::class)
        ->and($outline->asOf)->toBe('20 nov 2026')
        ->and($outline->highestLevel)->toBe('Muy alto')
        ->and($outline->responsible)->toBe('Dra. Laura Méndez')
        ->and($outline->state)->toBe('En curso')
        ->and($outline->meter)->toBe(['done' => 0, 'total' => 2]);

    $row = array_find($outline->items, fn ($item): bool => $item->label === 'Calificación final');
    expect($row?->scope)->toBe('Calificación final')
        ->and($row->level)->toBe('Muy alto')
        ->and($row->analysis)->toBe('La carga se concentra en producción.')
        ->and($row->followUp)->toBe('Programada para el 19 nov 2028')
        ->and(count($row->actions))->toBe(2);

    $first = array_find($row->actions, fn ($action): bool => $action->title === 'Revisar la política');
    expect($first?->kind)->toBe('Revisión de la política de prevención')
        ->and($first->level)->toBe('Primer nivel: organizacional')
        ->and($first->subjects)->toBe('Producción')
        ->and($first->startsOn)->toBe('1 dic 2026')
        ->and($first->dueOn)->toBe('20 dic 2026')
        ->and($first->responsible)->toBe('Hugo RH')
        ->and($first->status)->toBe('Planeada')
        ->and($first->professional)->toBeNull();

    $clinical = array_find($row->actions, fn ($action): bool => $action->title === 'Atención psicológica a solicitud');
    expect($clinical?->subjects)->toBe('Todo el centro de trabajo')
        ->and($clinical->professional)->toBe('Psicólogo');
});

it('names only the areas Results shows, never one that stays hidden', function (): void {
    $action = ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->final->id, 'subjects' => 'areas']);
    foreach (['Producción', 'Compras'] as $name) {
        ActionArea::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $action->id, 'area_id' => $this->areas[$name]->id]);
    }

    $outline = resolve(ProgramOutlines::class)->forCampaign($this->campaign->id);
    $line = array_find($outline?->items ?? [], fn ($item): bool => $item->label === 'Calificación final')?->actions[0];

    expect($line?->subjects)->toBe('Producción')
        ->and(json_encode($outline, JSON_THROW_ON_ERROR))->not->toContain('Compras');
});

it('carries no evidence, no note of a step, no id of a person and no file path', function (): void {
    $action = ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->final->id, 'created_by' => memberOf($this->tenant, TenantRole::Hr)->id]);
    ProgressEntry::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $action->id, 'note' => 'Hablé con Juan Pérez.', 'evidence_path' => 'evidence/secret.pdf']);

    $json = json_encode(resolve(ProgramOutlines::class)->forCampaign($this->campaign->id), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('Juan')->not->toContain('secret')->not->toContain('evidence')->not->toContain('"description"')->not->toContain('created_by');
});

it('leaves out the actions that were cancelled from the count and marks them as cancelled', function (): void {
    $action = resolve(SaveAction::class)->handle(new ActionData(null, $this->final->id, ActionKind::PolicyReview, ActionLevel::Organizational, 'Revisar', 'Texto.', ActionSubjects::Center, [], null, '2026-12-20', 'Laura', null, null), memberOf($this->tenant, TenantRole::Hr)->id);
    resolve(CancelAction::class)->handle($action->id, 'Ya no aplica.', memberOf($this->tenant, TenantRole::Hr)->id);

    $outline = resolve(ProgramOutlines::class)->forCampaign($this->campaign->id);
    $line = array_find($outline?->items ?? [], fn ($item): bool => $item->label === 'Calificación final')?->actions[0];

    expect($line?->status)->toBe('Cancelada')
        ->and($outline?->meter)->toBe(['done' => 0, 'total' => 0]);
});

it('says an action is overdue when it is', function (): void {
    ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->final->id, 'due_on' => '2026-11-01']);

    $outline = resolve(ProgramOutlines::class)->forCampaign($this->campaign->id);

    expect($outline?->state)->toBe('Con acciones vencidas')
        ->and(array_find($outline->items, fn ($item): bool => $item->label === 'Calificación final')?->actions[0]->overdue)->toBeTrue();
});

it('records what the follow-up found, with its day', function (): void {
    $this->final->update(['follow_up_due_on' => '2028-11-19', 'follow_up_done_on' => '2028-11-02', 'follow_up_result' => 'Bajó a nivel medio.']);

    $row = array_find(resolve(ProgramOutlines::class)->forCampaign($this->campaign->id)?->items ?? [], fn ($item): bool => $item->label === 'Calificación final');

    expect($row?->followUp)->toBe('Hecha el 2 nov 2028: Bajó a nivel medio.');
});

it('reads with a fixed number of queries, however many rows and actions', function (): void {
    foreach (range(1, 6) as $ignored) {
        $action = ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->final->id]);
        ActionArea::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $action->id, 'area_id' => $this->areas['Producción']->id]);
    }

    $this->expectsDatabaseQueryCount(6);
    resolve(ProgramOutlines::class)->forCampaign($this->campaign->id);
});
