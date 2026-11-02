<?php

declare(strict_types=1);

use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Models\MedicalReferral;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** @return array{string, WorkCenter} The panel's address and a work center of the company the user signed in to. */
function seatingCenter(): array
{
    $url = signInToPanel();

    return [$url, WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create(['name' => 'Planta Querétaro', 'main_activity' => 'Manufactura'])];
}

it('lists the centers of the company with their declared sector', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    SeatingProfile::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'sector' => 'commerce']);

    visitPage('seating/index', "{$url}/seating", $dark, $mobile)
        ->assertSee('Ley Silla')
        ->assertSee('Planta Querétaro')
        ->assertSee('Comercio')
        ->assertSee('Abrir centro');
})->with($looks);

it('shows a center with its declaration form', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();

    visitPage('seating/center', "{$url}/seating/centers/{$center->id}", $dark, $mobile)
        ->assertSee('Planta Querétaro')
        ->assertSee('Sector del centro')
        ->assertSee('Guardar declaración');
})->with($looks);

it('declares the sector of a center from the page', function (): void {
    [$url, $center] = seatingCenter();

    visit("{$url}/seating/centers/{$center->id}")
        ->click('Servicios')
        ->press('Guardar declaración')
        ->assertSee('Declaración guardada.')
        ->assertSee('Declarado el')
        ->assertNoJavaScriptErrors();
});

it('lists the standing positions of a center with their result', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    StandingPosition::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Cajera']);

    visitPage('seating/center', "{$url}/seating/centers/{$center->id}", $dark, $mobile)
        ->assertSee('Puestos de pie')
        ->assertSee('Cajera')
        ->assertSee('Resultado A')
        ->assertSee('Nuevo puesto');
})->with($looks);

it('shows the form of a new position with the questions of Figure 1', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();

    visitPage('seating/position', "{$url}/seating/centers/{$center->id}/positions/create", $dark, $mobile)
        ->assertSee('Nuevo puesto')
        ->assertSee('Figura 1: dónde va el asiento')
        ->assertSee('Guardar puesto');
})->with($looks);

it('shows a saved position with its result and its people', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    $position = StandingPosition::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Cajera']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id]);

    visitPage('seating/position', "{$url}/seating/positions/{$position->id}", $dark, $mobile)
        ->assertSee('Resultado A')
        ->assertSee('Se recomienda documentar todas las medidas de cumplimiento.')
        ->assertSee('Ana Ruiz')
        ->assertSee('Quitar');
})->with($looks);

it('shows what a new position still misses instead of saving it', function (): void {
    [$url, $center] = seatingCenter();

    visit("{$url}/seating/centers/{$center->id}/positions/create")
        ->type('name', 'Cajera')
        ->type('tasks', 'Cobra de pie.')
        ->click('Prolongada')
        ->type('continuous_minutes', '240')
        ->type('max_people_at_once', '2')
        ->press('Guardar puesto')
        ->assertSee('es obligatorio')
        ->assertNoJavaScriptErrors();
});

it('shows the seat inventory with its form', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    Seat::factory()->restArea()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'quantity' => 4]);
    $position = StandingPosition::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Cajera', 'max_people_at_once' => 3]);

    visitPage('seating/seats', "{$url}/seating/centers/{$center->id}/seats", $dark, $mobile)
        ->assertSee('Asientos')
        ->assertSee('4 × Silla ergonómica ajustable')
        ->assertSee('Área de descanso')
        ->assertSee('Cajera (0 de 3)')
        ->assertSee('Registrar asiento');
})->with($looks);

it('shows what a new seat still misses instead of registering it', function (): void {
    [$url, $center] = seatingCenter();

    visit("{$url}/seating/centers/{$center->id}/seats")
        ->press('Registrar asiento')
        ->assertSee('es obligatorio')
        ->assertNoJavaScriptErrors();
});

/** @return array{string, App\Modules\Organization\Models\Employee} The address of the person's Ley Silla page and the person. */
function seatingPortal(): array
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Ruiz']);
    workIn($tenant);

    return [route('portal.seating.show', resolve(IssuePortalLink::class)->handle($ana->id), absolute: false), $ana];
}

it('shows the person their own Ley Silla page with the voluntary notice', function (bool $dark, bool $mobile): void {
    [$url] = seatingPortal();

    visitPage('seating/portal', $url, $dark, $mobile)
        ->assertSee('Su trabajo de pie, Ana Ruiz')
        ->assertSee('es voluntario')
        ->assertSee('Ergonómico, con buen soporte y amortiguación')
        ->assertSee('Enviar mis respuestas');
})->with($looks);

it('tells the person when they already answered', function (): void {
    [$url, $ana] = seatingPortal();
    PersonInput::factory()->create(['tenant_id' => $ana->tenant_id, 'employee_id' => $ana->id, 'work_center_id' => $ana->work_center_id]);

    visitPage('seating/portal', $url)->assertSee('Nos contestó el');
});

it('asks for the acceptance of the notice before saving any health data', function (): void {
    [$url] = seatingPortal();

    visit($url)
        ->press('Enviar mis respuestas')
        ->assertSee('debe ser aceptado')
        ->assertNoJavaScriptErrors();
});

/** @return array{string, App\Modules\Organization\Models\Employee, StandingPosition, WorkCenter} The panel's address, a person in a position, the position and its center; the user sits on the center's Comisión. */
function assessableCenter(): array
{
    [$url, $center] = seatingCenter();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'user_id' => auth()->id()]);
    $position = StandingPosition::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Cajera']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'position_id' => $position->id]);

    return [$url, $ana, $position, $center];
}

it('lists the people to assess with their state', function (bool $dark, bool $mobile): void {
    [$url, , , $center] = assessableCenter();

    visitPage('seating/assessments', "{$url}/seating/centers/{$center->id}/assessments", $dark, $mobile)
        ->assertSee('Evaluaciones')
        ->assertSee('Ana Ruiz')
        ->assertSee('Sin evaluar')
        ->assertSee('Evaluar');
})->with($looks);

it('shows the draft of an assessment with its eleven items and seven conditions', function (bool $dark, bool $mobile): void {
    [$url, $ana, $position, $center] = assessableCenter();
    $draft = RiskAssessment::factory()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id, 'work_center_id' => $center->id]);

    visitPage('seating/assessment', "{$url}/seating/assessments/{$draft->id}", $dark, $mobile)
        ->assertSee('Ana Ruiz')
        ->assertSee('Registro de la Comisión')
        ->assertSee('Las siete condicionantes')
        ->assertSee('Calzado')
        ->assertSee('Firmar evaluación');
})->with($looks);

it('shows a signed assessment as final, with its result and the suggestion', function (bool $dark, bool $mobile): void {
    [$url, $ana, $position, $center] = assessableCenter();
    $signed = RiskAssessment::factory()->signed()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id, 'work_center_id' => $center->id]);

    visitPage('seating/assessment', "{$url}/seating/assessments/{$signed->id}", $dark, $mobile)
        ->assertSee('7 de 21')
        ->assertSee('Sugerencia de Humetría')
        ->assertDontSee('Firmar evaluación')
        ->assertDontSee('Guardar borrador');
})->with($looks);

it('says what is missing when the Comisión tries to sign an incomplete assessment', function (): void {
    [$url, $ana, $position, $center] = assessableCenter();
    $draft = RiskAssessment::factory()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id, 'work_center_id' => $center->id]);

    visit("{$url}/seating/assessments/{$draft->id}")
        ->press('Firmar evaluación')
        ->press('Firmar')
        ->assertSee('Falta completar para firmar')
        ->assertNoJavaScriptErrors();
});

it('lists the seat reports of a center, open and resolved', function (bool $dark, bool $mobile): void {
    [$url, , , $center] = assessableCenter();
    SeatReport::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'description' => 'La silla de la caja 2 está floja.']);

    visitPage('seating/reports', "{$url}/seating/centers/{$center->id}/reports", $dark, $mobile)
        ->assertSee('Reportes de asientos')
        ->assertSee('La silla de la caja 2 está floja.')
        ->assertSee('Abiertos')
        ->assertSee('Resueltos');
})->with($looks);

it('shows the person the result of their assessment to confirm, and the seat report form', function (bool $dark, bool $mobile): void {
    [$url, $ana] = seatingPortal();
    RiskAssessment::factory()->signed()->create(['tenant_id' => $ana->tenant_id, 'employee_id' => $ana->id, 'work_center_id' => $ana->work_center_id]);

    visitPage('seating/portal', $url, $dark, $mobile)
        ->assertSee('Evaluamos su trabajo de pie')
        ->assertSee('Confirmo que me informaron')
        ->assertSee('Avisar del asiento dañado');
})->with($looks);

it('confirms from the portal and says so', function (): void {
    [$url, $ana] = seatingPortal();
    RiskAssessment::factory()->signed()->create(['tenant_id' => $ana->tenant_id, 'employee_id' => $ana->id, 'work_center_id' => $ana->work_center_id]);

    visit($url)->press('Confirmo que me informaron')->assertSee('Usted confirmó el')->assertNoJavaScriptErrors();
});

it('shows the referrals of a person on their assessment, with the form to add one', function (bool $dark, bool $mobile): void {
    [$url, $ana, $position, $center] = assessableCenter();
    $signed = RiskAssessment::factory()->signed()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id, 'work_center_id' => $center->id]);
    MedicalReferral::factory()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'work_center_id' => $center->id, 'note' => 'Valoración en medicina del trabajo.']);

    visitPage('seating/assessment', "{$url}/seating/assessments/{$signed->id}", $dark, $mobile)
        ->assertSee('Canalización')
        ->assertSee('Se canalizó a atención médica')
        ->assertSee('Valoración en medicina del trabajo.')
        ->assertSee('Registrar');
})->with($looks);

it('shows the action plan with its measures and its pause agreements', function (bool $dark, bool $mobile): void {
    [$url, , $position, $center] = assessableCenter();
    PreventiveMeasure::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'position_id' => $position->id, 'kind' => 'task_rotation']);
    PauseAgreement::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'position_id' => $position->id]);

    visitPage('seating/plan', "{$url}/seating/centers/{$center->id}/plan", $dark, $mobile)
        ->assertSee('Plan de acción')
        ->assertSee('Alternar tareas para cambiar de postura')
        ->assertSee('3 por turno de 10 min')
        ->assertSee('Registrar un acuerdo de pausas');
})->with($looks);

it('lets the Comisión propose a measure from the assessment', function (): void {
    [$url, $ana, $position, $center] = assessableCenter();
    $signed = RiskAssessment::factory()->signed()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id, 'work_center_id' => $center->id]);

    visit("{$url}/seating/assessments/{$signed->id}")
        ->assertSee('Proponer una medida')
        ->assertSee('Sugerencia de Humetría')
        ->assertNoJavaScriptErrors();
});

it('lists the documents of a center with their incisos and the form to add one', function (bool $dark, bool $mobile): void {
    [$url, , , $center] = assessableCenter();
    SeatingDocument::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'title' => 'Acta de febrero', 'incisos' => ['B', 'C']]);

    visitPage('seating/documents', "{$url}/seating/centers/{$center->id}/documents", $dark, $mobile)
        ->assertSee('Acta de febrero')
        ->assertSee('Acta de la Comisión')
        ->assertSee('B, C')
        ->assertSee('Guardar documento');
})->with($looks);

it('shows the regulation before it is drafted, with the notice that it is not legal advice', function (bool $dark, bool $mobile): void {
    [$url, , , $center] = assessableCenter();

    visitPage('seating/regulation', "{$url}/seating/centers/{$center->id}/regulation", $dark, $mobile)
        ->assertSee('no es asesoría legal')
        ->assertSee('Todavía no hay borrador')
        ->assertSee('Preparar borrador con los datos del centro');
})->with($looks);

it('shows a drafted regulation with its parts, its progress and the next step', function (bool $dark, bool $mobile): void {
    [$url, , , $center] = assessableCenter();
    InternalRegulation::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id]);

    visitPage('seating/regulation', "{$url}/seating/centers/{$center->id}/regulation", $dark, $mobile)
        ->assertSee('no es asesoría legal')
        ->assertSee('Borrador')
        ->assertSee('Registrar la firma de la comisión mixta')
        ->assertSee('Imprimir o guardar PDF');
})->with($looks);

it('drafts the regulation from the page', function (): void {
    [$url, , , $center] = assessableCenter();

    visit("{$url}/seating/centers/{$center->id}/regulation")
        ->press('Preparar borrador con los datos del centro')
        ->assertSee('Factores de riesgo')
        ->assertNoJavaScriptErrors();
});
