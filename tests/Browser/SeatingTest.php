<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
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

// Rediseño v2 (the pattern of board V2-Centros-N037): each center with who works standing there, where it stands and its route.
it('lists the centers of the company with where their route stands', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    SeatingProfile::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'sector' => 'commerce']);
    StandingPosition::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id]);

    visitPage('seating/index', "{$url}/seating", $dark, $mobile)
        ->assertSee('Trabajo de pie en tus centros')
        ->assertSee('Planta Querétaro')
        ->assertSee('Abrir ruta')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
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
        ->assertSee('Paso 1 de 5')
        ->assertSee('Figura 1: dónde va el asiento')
        ->assertSee('Siguiente: Figura 1');
})->with($looks);

// Board LS-Puesto: Figura 1 gives its result as it is answered; with the first two questions, the third is not needed.
it('shows the result of Figura 1 as its questions are answered', function (): void {
    [$url, $center] = seatingCenter();

    visit("{$url}/seating/centers/{$center->id}/positions/create")
        ->press('@position-next')
        ->assertSee('Paso 2 de 5')
        ->click('label:has(input[name="can_work_seated"][value="1"])')
        ->click('label:has(input[name="has_space_for_seat"][value="1"])')
        ->assertSeeIn('[aria-current="true"]', 'En su lugar de trabajo')
        ->assertSee('Con las dos primeras ya hay resultado')
        ->click('label:has(input[name="has_space_for_seat"][value="0"])')
        ->click('label:has(input[name="needs_to_be_nearby"][value="1"])')
        ->assertSeeIn('[aria-current="true"]', 'Cerca de su lugar')
        ->assertNoJavaScriptErrors();
});

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
        ->press('@position-next')
        ->assertSee('Paso 2 de 5')
        ->press('@position-next')
        ->assertSee('Paso 3 de 5')
        ->press('@position-next')
        ->assertSee('Paso 4 de 5')
        ->press('Guardar puesto')
        // The step of the first field it misses opens again.
        ->assertSee('Paso 2 de 5')
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

// Board LS-Portal: first who sees the answers and that answering is voluntary; then the two questions, one at a time.
it('shows the person their own Ley Silla page with the voluntary notice', function (bool $dark, bool $mobile): void {
    [$url] = seatingPortal();

    visitPage('seating/portal', $url, $dark, $mobile)
        ->assertSee('Ley Silla · 2 preguntas')
        ->assertSee('Su trabajo de pie, Ana Ruiz')
        ->assertSee('Contestar es voluntario')
        ->assertSee('Quién ve sus respuestas')
        ->assertSee('Acepto, quiero contestar')
        ->assertSee('Prefiero no contestar')
        ->assertDontSee('Pregunta 1 de 2');
})->with($looks);

it('asks the two questions one at a time, never moving on alone, and sends them', function (bool $dark, bool $mobile): void {
    [$url] = seatingPortal();

    visitPage('seating/portal', $url, $dark, $mobile)
        ->click('@portal-accept')
        ->assertSee('Pregunta 1 de 2')
        ->assertSee('¿Cómo es el calzado que usa para trabajar?')
        ->click('input[name="footwear"][value="1"]')
        ->assertSee('Pregunta 1 de 2')
        ->press('Siguiente')
        ->assertSee('Pregunta 2 de 2')
        ->click('input[name="discomfort"][value="1"]')
        ->press('Enviar mis respuestas')
        ->assertSee('Gracias, quedó enviado')
        ->assertSee('La Comisión lo tomará en cuenta en su evaluación.')
        ->assertNoJavaScriptErrors();
})->with(['light desktop' => [false, false], 'dark mobile' => [true, true]]);

it('tells the person when they already answered', function (): void {
    [$url, $ana] = seatingPortal();
    PersonInput::factory()->create(['tenant_id' => $ana->tenant_id, 'employee_id' => $ana->id, 'work_center_id' => $ana->work_center_id]);

    visitPage('seating/portal', $url)->assertSee('Gracias, quedó enviado')->assertSee('Nos contestó el');
});

it('lets the person decline without consequence, and change their mind', function (): void {
    [$url] = seatingPortal();

    visit($url)
        ->click('Prefiero no contestar')
        ->assertSee('su evaluación se hace sin estos datos')
        ->assertDontSee('Pregunta 1 de 2')
        ->click('Contestar ahora')
        ->assertSee('Quién ve sus respuestas')
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

// Board LS-Evaluacion: beside the conditions, the points chosen so far; the level is computed when it is signed.
it('adds the points of the draft as the Comisión chooses them, and leaves the level to the signature', function (): void {
    [$url, $ana, $position, $center] = assessableCenter();
    $draft = RiskAssessment::factory()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id, 'work_center_id' => $center->id]);

    visit("{$url}/seating/assessments/{$draft->id}")
        ->assertSeeIn('[aria-labelledby="score"]', '13')
        ->assertSee('Del puesto')
        ->assertSee('Sin respuesta de la persona')
        ->assertSee('El nivel de riesgo se calcula al firmar, con las tablas vigentes.')
        ->click('label:has(input[name="scores[footwear]"][value="3"])')
        ->assertSeeIn('[aria-labelledby="score"]', '16')
        ->assertNoJavaScriptErrors();
});

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
        ->assertSee('Plan de medidas y pausas')
        ->assertSee('Planeada')
        ->assertSee('Alternar tareas para cambiar de postura')
        ->assertSee('3 por turno de 10 min')
        ->assertSee('Arrastra la evidencia aquí o elígela')
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
        // C4: the file is dropped on or chosen from the zone, never the native field.
        ->assertSee('Arrastra el PDF aquí o elígelo')
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

it('opens the confidential survey of the seats with its first question about standing', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $campaign = Campaign::factory()->active(now()->addDays(10)->toDateString())->create(['tenant_id' => $tenant->id, 'kind' => CampaignKind::SeatingSurvey, 'instruments' => ['seating.survey']]);
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id, 'token_hash' => Participant::hashToken('the-token')]);
    ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => 'seating.survey']);

    visitPage('campaigns/survey', '/survey/the-token', $dark, $mobile)
        ->assertSee('Encuesta de Ley Silla')
        ->assertSee('Confidencial');
})->with($looks);

it('offers to launch the survey of a center that has none', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();

    visitPage('seating/survey', "{$url}/seating/centers/{$center->id}/survey", $dark, $mobile)
        ->assertSee('Encuesta confidencial')
        ->assertSee('Último día para contestar')
        ->assertSee('Lanzar la encuesta');
})->with($looks);

it('shows the closed survey counted per question, with the minimum of people', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    $survey = Campaign::factory()->closed()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'kind' => CampaignKind::SeatingSurvey, 'instruments' => ['seating.survey']]);
    $area = Area::factory()->create(['tenant_id' => $center->tenant_id, 'name' => 'Almacén']);
    AnonymousResponse::factory()->count(6)->create(['campaign_id' => $survey->id, 'area_id' => $area->id, 'instrument' => 'seating.survey', 'instrument_version' => 'dof-2025-07-17', 'answers' => surveyAnswers('yes')]);

    visitPage('seating/survey', "{$url}/seating/centers/{$center->id}/survey", $dark, $mobile)
        ->assertSee('Todo el centro de trabajo: 6 respuestas')
        ->assertSee('SS-01')
        ->assertSee('Sí 6 · No 0 · No aplica 0');
})->with($looks);

it('shows the board of a center with its thirteen requirements and its tabs', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();

    visitPage('seating/center', "{$url}/seating/centers/{$center->id}", $dark, $mobile)
        ->assertSee('Requisitos del centro')
        ->assertSee('R1')
        ->assertSee('R13')
        ->assertSee('Falta')
        ->assertSee('Inventario')
        ->assertSee('Encuesta');
})->with($looks);

it('shows the printable dossier of a center with a section per requirement and the fine note', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    InternalRegulation::factory()->distributed()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id]);

    visitPage('seating/dossier', "{$url}/seating/centers/{$center->id}/dossier", $dark, $mobile)
        ->assertSee('Expediente de Ley Silla para la inspección')
        ->assertSee('R10')
        ->assertSee('Folio del depósito: CFCRL-2026-0001')
        ->assertSee('Falta')
        ->assertSee('no fija una multa propia')
        ->assertSee('Imprimir o guardar PDF');
})->with($looks);

it('opens the dossier from the center page', function (): void {
    [$url, $center] = seatingCenter();

    visit("{$url}/seating/centers/{$center->id}")
        ->click('Expediente para inspección')
        ->assertSee('Expediente de Ley Silla para la inspección')
        ->assertNoJavaScriptErrors();
});

it('walks the critical path: the Comisión assesses, the person confirms and reports a seat from the phone, and RH reads the board and the dossier', function (): void {
    [$url, $ana, $position, $center] = assessableCenter();
    $draft = RiskAssessment::factory()->create([
        'tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id, 'work_center_id' => $center->id,
        'scores' => ['standing_time' => 3, 'posture_change' => 2, 'surface' => 3, 'footwear' => 1, 'movement_space' => 2, 'discomfort' => 1, 'pauses' => 2],
        'other_aspects' => 'Ninguno.', 'verified_on' => now()->subDay()->toDateString(),
    ]);

    visit("{$url}/seating/assessments/{$draft->id}")
        ->press('Firmar evaluación')
        ->press('Firmar')
        ->assertSee('14 de 21')
        ->assertNoJavaScriptErrors();

    $portal = route('portal.seating.show', resolve(IssuePortalLink::class)->handle($ana->id), absolute: false);

    visit($portal)->on()->mobile()
        ->assertSee('Evaluamos su trabajo de pie')
        ->press('Confirmo que me informaron')
        ->assertSee('Usted confirmó el')
        ->type('description', 'El respaldo de la silla de la caja 2 está flojo.')
        ->press('Avisar del asiento dañado')
        ->assertNoJavaScriptErrors();

    expect(SeatReport::query()->where('work_center_id', $center->id)->sole()->description)->toBe('El respaldo de la silla de la caja 2 está flojo.');

    visit("{$url}/seating/centers/{$center->id}")
        ->assertSee('Requisitos del centro')
        ->assertSee('R8')
        ->assertNoJavaScriptErrors();

    visit("{$url}/seating/centers/{$center->id}/dossier")
        ->assertSee('Expediente de Ley Silla para la inspección')
        ->assertSee('1 persona con evaluación firmada')
        ->assertNoJavaScriptErrors();
});

it('shows the survey in progress and closes it before its last day', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    Campaign::factory()->active(now()->addDays(10)->toDateString())->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'kind' => CampaignKind::SeatingSurvey, 'instruments' => ['seating.survey']]);

    visitPage('seating/survey', "{$url}/seating/centers/{$center->id}/survey", $dark, $mobile)
        ->assertSee('La encuesta está en curso')
        ->assertSee('Cerrar la encuesta ahora');
})->with($looks);

it('closes the survey from its page after asking', function (): void {
    [$url, $center] = seatingCenter();
    $survey = Campaign::factory()->active(now()->addDays(10)->toDateString())->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'kind' => CampaignKind::SeatingSurvey, 'instruments' => ['seating.survey']]);

    visit("{$url}/seating/centers/{$center->id}/survey")
        ->press('Cerrar la encuesta ahora')
        ->press('Cerrar encuesta')
        ->assertSee('Resultados protegidos')
        ->assertNoJavaScriptErrors();

    expect($survey->refresh()->status->value)->toBe('closed');
});

// Rediseño v2 (C7): the list of assessments with nobody to assess says why, and a search that found nobody says so instead.
it('tells a center with nobody to assess apart from a search that found nobody', function (): void {
    [$url, , , $center] = assessableCenter();
    $empty = WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create();

    visit("{$url}/seating/centers/{$empty->id}/assessments")
        ->assertSee('Sin personas que evaluar')
        ->assertDontSee('Nadie coincide')
        ->assertNoJavaScriptErrors();

    visit("{$url}/seating/centers/{$center->id}/assessments?q=Zeta")
        ->assertSee('Nadie coincide')
        ->assertDontSee('Sin personas que evaluar')
        ->assertNoJavaScriptErrors();
});
