<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/**
 * The panel's address and a closed evaluation of the company the user signed in to, answered in two areas.
 *
 * @return array{string, string}
 */
function programEvaluation(): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    [$campaign] = closedEvaluationInAreas($tenant, ['Producción' => 7, 'Ventas' => 6]);

    return [$url, $campaign->id];
}

/** The Programa of the evaluation, with an action planned on its final score and another one done on a domain. */
function programWithActions(string $campaignId): void
{
    $program = resolve(CreateProgram::class)->handle($campaignId, null);
    $rows = ProgramItem::query()->where('program_id', $program?->id)->orderBy('scope_key')->get();
    $tenantId = $program?->tenant_id ?? '';

    ProgramAction::factory()->create(['tenant_id' => $tenantId, 'program_id' => $program?->id, 'item_id' => $rows[0]->id, 'title' => 'Revisar la política', 'due_on' => '2020-01-01']);
    ProgramAction::factory()->done()->create(['tenant_id' => $tenantId, 'program_id' => $program?->id, 'item_id' => $rows[1]->id, 'title' => 'Campaña de sensibilización']);
}

it('offers to create the Programa of an evaluation that asks for one', function (bool $dark, bool $mobile): void {
    [$url, $campaign] = programEvaluation();

    visitPage('interventions/show', "{$url}/campaigns/{$campaign}/program", $dark, $mobile)
        ->assertSee('Programa de intervención')
        ->assertSee('Esta evaluación pide un Programa de intervención')
        ->assertSee('Crear el Programa');
})->with($looks);

it('creates the Programa from the page and shows its rows', function (): void {
    [$url, $campaign] = programEvaluation();

    visit("{$url}/campaigns/{$campaign}/program")
        ->press('Crear el Programa')
        ->assertSee('Programa creado.')
        ->assertSee('Calificación final')
        ->assertSee('Lo que pide la Tabla')
        ->assertSee('Revisar la política de prevención');
});

it('shows the Programa: where it stands, what the Tabla asks and its rows', function (bool $dark, bool $mobile): void {
    [$url, $campaign] = programEvaluation();
    programWithActions($campaign);

    visitPage('interventions/show', "{$url}/campaigns/{$campaign}/program", $dark, $mobile)
        ->assertSee('Con acciones vencidas')
        ->assertSee('1 de 2 acciones hechas')
        ->assertSee('Lo que pide la Tabla')
        ->assertSee('Qué se atiende')
        ->assertSee('Calificación final');
})->with($looks);

it('lists the actions of the Programa with what is overdue', function (bool $dark, bool $mobile): void {
    [$url, $campaign] = programEvaluation();
    programWithActions($campaign);

    visitPage('interventions/show', "{$url}/campaigns/{$campaign}/program?tab=actions", $dark, $mobile)
        ->assertSee('Revisar la política')
        ->assertSee('Vencida')
        ->assertSee('Campaña de sensibilización');
})->with($looks);

it('shows the follow-up evaluation of each row', function (): void {
    [$url, $campaign] = programEvaluation();
    programWithActions($campaign);

    visitPage('interventions/show', "{$url}/campaigns/{$campaign}/program?tab=follow-up")
        ->assertSee('Es opcional')
        ->assertSee('Programar o registrar');
});

it('adds an action to a row from its side panel', function (): void {
    [$url, $campaign] = programEvaluation();
    resolve(CreateProgram::class)->handle($campaign, null);

    visit("{$url}/campaigns/{$campaign}/program")
        ->click('[aria-label="Acciones de Calificación final"]')
        ->click('Agregar acción')
        ->assertSee('No escribas nombres ni datos de personas.')
        ->fill('title', 'Difundir la política')
        ->fill('description', 'Difundir la política entre todos los turnos.')
        ->fill('responsible_name', 'Laura Méndez')
        ->click('#action-due')
        ->click('[data-day="'.now('America/Mexico_City')->format('Y-m-d').'"] button')
        ->press('Guardar acción')
        ->assertSee('Acción registrada.');

    expect(ProgramAction::query()->count())->toBe(1);
});

it('records a step of an action from its menu and keeps it in the history', function (): void {
    [$url, $campaign] = programEvaluation();
    programWithActions($campaign);

    visit("{$url}/campaigns/{$campaign}/program?tab=actions")
        ->click('[aria-label="Opciones de Revisar la política"]')
        ->click('Registrar avance')
        ->fill('note', 'Ya se aprobó el presupuesto.')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Avance registrado.');

    expect(ProgramAction::query()->where('title', 'Revisar la política')->sole()->status->value)->toBe('in_progress');
});

it('lists the Programas of the company, by state', function (bool $dark, bool $mobile): void {
    [$url, $campaign] = programEvaluation();
    programWithActions($campaign);

    visitPage('interventions/index', "{$url}/programs", $dark, $mobile)
        ->assertSee('Programas de intervención')
        ->assertSee('Con acciones vencidas')
        ->assertSee('Evaluaciones cerradas');
})->with($looks);

it('says there are no closed evaluations yet', function (): void {
    $url = signInToPanel();

    visitPage('interventions/index', "{$url}/programs")
        ->assertSee('Aún no hay evaluaciones cerradas');
});

it('prints the Programa in the 7.7 report, next to what HR wrote', function (bool $dark, bool $mobile): void {
    [$url, $campaign] = programEvaluation();
    programWithActions($campaign);

    visitPage('results/report', "{$url}/campaigns/{$campaign}/report", $dark, $mobile)
        ->assertSee('Recomendaciones y acciones de intervención')
        ->assertSee('Programa de intervención · Estado al')
        ->assertSee('Revisar la política')
        ->assertSee('1 de 2 acciones hechas');
})->with($looks);

it('says on the report that the Programa is missing, and offers it on the results', function (): void {
    [$url, $campaign] = programEvaluation();

    visitPage('results/report', "{$url}/campaigns/{$campaign}/report")
        ->assertSee('todavía no se ha creado');
    visitPage('results/show', "{$url}/campaigns/{$campaign}/results")
        ->assertSee('Crear el Programa');
});

it('shows on the results where the Programa stands, with the way to it', function (bool $dark, bool $mobile): void {
    [$url, $campaign] = programEvaluation();
    programWithActions($campaign);

    visitPage('results/show', "{$url}/campaigns/{$campaign}/results", $dark, $mobile)
        ->assertSee('Programa de intervención')
        ->assertSee('Con acciones vencidas')
        ->assertSee('Ver el Programa');
})->with($looks);
