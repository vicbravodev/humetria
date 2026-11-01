<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\PublishChecklist;
use App\Modules\Telework\Actions\RegisterCommittee;
use App\Modules\Telework\Actions\RequestAcknowledgements;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Models\TrainingAttendee;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\EquipmentItemFactory;
use Database\Factories\Telework\EvidenceRequestFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\TrainingFactory;
use Database\Factories\Telework\VisitAuthorizationFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the NOM-037 settings with the default period and the base consent', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('telework/settings', "{$url}/telework/settings", $dark, $mobile)
        ->assertSee('Ajustes de NOM-037')
        ->assertSee('Versión 1')
        ->assertScript('document.querySelector("input[type=radio]:checked")?.parentElement?.textContent', '12 meses');
})->with($looks);

it('saves a custom period and a new consent text', function (): void {
    $url = signInToPanel();

    visit("{$url}/telework/settings")
        ->click('Otro')
        ->type('#period-custom', '18')
        ->type('consent_text', 'Texto propio de la empresa.')
        ->press('Guardar ajustes')
        ->assertSee('Ajustes de NOM-037 guardados.')
        ->assertSee('Versión 2')
        ->assertValue('#period-custom', '18')
        ->assertNoJavaScriptErrors();
});

/** @return array{string, WorkCenter} The panel's address and a work center of the company the user signed in to. */
function committeeCenter(): array
{
    $url = signInToPanel();

    return [$url, WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create(['name' => 'Planta Querétaro'])];
}

it('shows the Comisión of a center before it is registered', function (bool $dark, bool $mobile): void {
    [$url, $center] = committeeCenter();

    visitPage('telework/committee', "{$url}/telework/centers/{$center->id}/committee", $dark, $mobile)
        ->assertSee('Comisión de seguridad e higiene')
        ->assertSee('Fecha del acta')
        ->assertSee('Registrar Comisión');
})->with($looks);

it('shows the Comisión of a center with its people and with none', function (bool $dark, bool $mobile): void {
    [$url, $center] = committeeCenter();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $cora = memberOf($tenant, TenantRole::Committee);
    SafetyCommittee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'user_id' => $cora->id]);

    visitPage('telework/committee', "{$url}/telework/centers/{$center->id}/committee", $dark, $mobile)
        ->assertSee($cora->name)
        ->assertSee('1 integrante')
        ->assertSee('Documentos');
})->with($looks);

it('registers the Comisión from the page and then lets the last member go', function (): void {
    [$url, $center] = committeeCenter();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $cora = memberOf($tenant, TenantRole::Committee);

    visit("{$url}/telework/centers/{$center->id}/committee")
        ->click('#committee-constituted-on')
        // A week before the day the calendar opens on: the browser's today may be a day ahead of the server's.
        ->keys('[data-day] button[tabindex="0"]', ['ArrowUp', 'Enter'])
        ->click("#member-{$cora->id}")
        ->press('Registrar Comisión')
        ->assertSee('Comisión registrada')
        ->assertSee($cora->name)
        ->press('Quitar')
        ->assertSee('La Comisión no tiene integrantes')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

/** @return array{string, WorkCenter} The panel of a Comisión member, and the center whose Comisión has its base draft. */
function checklistCenter(): array
{
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $cora = memberOf($tenant, TenantRole::Committee);
    test()->actingAs($cora);
    $url = '/admin/acme';
    $center = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    workIn($tenant);
    resolve(RegisterCommittee::class)->handle($center->id, CarbonImmutable::parse('2026-09-15'), null, [$cora->id], $cora->id);

    return [$url, $center];
}

it('shows the checklist of a center, in draft', function (bool $dark, bool $mobile): void {
    [$url, $center] = checklistCenter();

    visitPage('telework/checklist', "{$url}/telework/centers/{$center->id}/checklist", $dark, $mobile)
        ->assertSee('Lista de verificación')
        ->assertSee('Versión 1 · borrador')
        ->assertSee('Apéndice 3');
})->with($looks);

it('edits a question, sees it become modified, and publishes', function (): void {
    [$url, $center] = checklistCenter();

    visit("{$url}/telework/centers/{$center->id}/checklist")
        ->click('[aria-label="Editar S1-01"]')
        ->fill('#edit-'.ChecklistQuestion::query()->where('code', 'S1-01')->firstOrFail()->id.'-text', 'Pregunta propia de la Comisión')
        ->press('Guardar pregunta')
        ->assertSee('Pregunta propia de la Comisión')
        ->assertSee('Apéndice 3 modificada')
        ->click('[aria-label="Quitar S1-02"]')
        ->assertSee('¿Quitar S1-02 del borrador?')
        ->press('Quitar pregunta')
        ->assertDontSee('S1-02')
        ->press('Publicar versión 1')
        ->assertSee('¿Publicar la versión 1?')
        ->press('Publicar')
        ->assertSee('Versión 1 publicada')
        ->assertSee('Versión 1 · publicada')
        ->assertSee('Iniciar borrador nuevo')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('shows the first group of questions open and the rest closed until asked for, so 122 questions stay light', function (): void {
    [$url, $center] = checklistCenter();

    visit("{$url}/telework/centers/{$center->id}/checklist")
        ->assertPresent('[aria-label="Editar S1-01"]')
        ->assertMissing('[aria-label="Editar S1-13"]')
        ->click('Organización para el trabajo')
        ->assertPresent('[aria-label="Editar S1-13"]')
        ->assertNoJavaScriptErrors();
});

it('adds a question to a closed group and opens that group to show it', function (): void {
    [$url, $center] = checklistCenter();

    visit("{$url}/telework/centers/{$center->id}/checklist")
        ->assertMissing('[aria-label="Editar H2-05"]')
        ->fill('#new-2-heading', 'Condiciones de la norma')
        ->fill('#new-2-text', '¿Cuenta con extintor cerca?')
        ->click('@add-question-2')
        ->assertSee('¿Cuenta con extintor cerca?')
        ->assertPresent('[aria-label="Editar C2-01"]')
        ->assertPresent('[aria-label="Editar H2-05"]')
        ->assertNoJavaScriptErrors();
});

it('tells a stale editor that the version was published meanwhile', function (): void {
    [$url, $center] = checklistCenter();
    $page = visit("{$url}/telework/centers/{$center->id}/checklist")->assertSee('Versión 1 · borrador');

    workIn(Tenant::query()->where('slug', 'acme')->firstOrFail());
    resolve(PublishChecklist::class)->handle(Checklist::query()->sole()->id, User::query()->firstOrFail()->id);

    $page->click('[aria-label="Editar S1-01"]')
        ->fill('#edit-'.ChecklistQuestion::query()->where('code', 'S1-01')->firstOrFail()->id.'-text', 'Tarde')
        ->press('Guardar pregunta')
        ->assertSee('Esta versión ya está publicada')
        ->assertSee('No se pudo guardar el cambio')
        ->assertSee('Versión 1 · publicada')
        ->assertMissing('[aria-label="Quitar S1-02"]')
        ->assertNoJavaScriptErrors();
});

/** @return string The address of the portal of a person with one checklist to answer and one sent. */
function portalAddress(): string
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Ruiz']);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);
    ChecklistApplicationFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id]);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $tenant->id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id, 'street' => 'Calle Juárez'])->id]);
    workIn($tenant);

    return '/portal/'.resolve(IssuePortalLink::class)->handle($employee->id);
}

it('shows the person their portal with what is pending and what was sent', function (bool $dark, bool $mobile): void {
    visitPage('telework/portal', portalAddress(), $dark, $mobile)
        ->assertSee('Hola, Ana Ruiz')
        ->assertSee('Por contestar')
        ->assertSee('Pendiente de contestar')
        ->assertSee('Ya enviado')
        ->assertSee('En revisión de la Comisión');
})->with($looks);

it('shows the person what the Comisión asked for and lets them authorize a visit', function (bool $dark, bool $mobile): void {
    $address = portalAddress();
    $tenant = Tenant::query()->where('trade_name', 'Acme')->firstOrFail();
    workIn($tenant);
    $workplace = Workplace::query()->firstOrFail();
    EvidenceRequestFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id, 'note' => 'Una foto de la silla.']);
    VisitAuthorizationFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id]);

    visitPage('telework/portal', $address, $dark, $mobile)
        ->assertSee('La Comisión le pide')
        ->assertSee('Una foto de la silla.')
        ->assertSee('Enviar fotos')
        ->assertSee('Autorizo la visita')
        ->press('Autorizo la visita')
        ->assertDontSee('Autorizo la visita');

    expect(VisitAuthorization::query()->sole()->confirmed_at)->not->toBeNull();
})->with($looks);

it('tells a link that opens nothing apart from nothing else', function (bool $dark, bool $mobile): void {
    visitPage('telework/portal', '/portal/not-a-link', $dark, $mobile)
        ->assertSee('Este enlace no es válido');
})->with($looks);

/** @return array{string, WorkCenter, Tenant} The panel of RH in acme, and a center of it (with a published list unless said otherwise). */
function teleworkerCenter(bool $published = true): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $center = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);

    if ($published) {
        ChecklistFactory::new()->published()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    }

    return [$url, $center, $tenant];
}

it('shows the page to add a person to telework', function (bool $dark, bool $mobile): void {
    [$url, $center] = teleworkerCenter();

    visitPage('telework/teleworker', "{$url}/telework/centers/{$center->id}/teleworkers/create", $dark, $mobile)
        ->assertSee('Agregar persona')
        ->assertSee('Porcentaje de teletrabajo')
        ->assertSee('Lugar de trabajo')
        ->assertSee('Registrar persona');
})->with($looks);

it('asks for the published list before adding anyone', function (): void {
    [$url, $center] = teleworkerCenter(published: false);

    visitPage('telework/teleworker', "{$url}/telework/centers/{$center->id}/teleworkers/create")
        ->assertSee('La Comisión aún no publica la lista')
        ->assertDontSee('Registrar persona');
});

it('shows a teleworker with their workplaces and no email', function (bool $dark, bool $mobile): void {
    [$url, $center, $tenant] = teleworkerCenter();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'email' => null]);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);
    ChecklistApplicationFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id]);

    visitPage('telework/teleworker', "{$url}/telework/teleworkers/{$teleworker->id}", $dark, $mobile)
        ->assertSee('Ana Ruiz')
        ->assertSee('Sin correo')
        ->assertSee('Generar enlace para copiar')
        ->assertSee('Calle Hidalgo 45')
        ->assertSee('Pendiente de contestar');
})->with($looks);

it('registers a person from the page and hands over a new link once', function (): void {
    [$url, $center, $tenant] = teleworkerCenter();
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'email' => null]);

    visit("{$url}/telework/centers/{$center->id}/teleworkers/create")
        ->click('#aspirant-employee')
        ->type('[role="combobox"][aria-controls="aspirant-employee-options"]', 'ana')
        ->assertSee('Ana Ruiz')
        ->keys('[role="combobox"][aria-controls="aspirant-employee-options"]', 'Enter')
        ->fill('#aspirant-percentage', '60')
        ->fill('#workplace-0-street', 'Calle Hidalgo')
        ->fill('#workplace-0-exterior_number', '45')
        ->fill('#workplace-0-neighborhood', 'Centro')
        ->fill('#workplace-0-postal_code', '76000')
        ->fill('#workplace-0-municipality', 'Querétaro')
        ->click('#workplace-0-state')
        ->type('[role="combobox"][aria-controls="workplace-0-state-options"]', 'quer')
        ->keys('[role="combobox"][aria-controls="workplace-0-state-options"]', 'Enter')
        ->press('Registrar persona')
        ->assertSee('Persona registrada')
        ->assertSee('Calle Hidalgo 45, Centro, Querétaro, Querétaro')
        ->press('Generar enlace para copiar')
        ->assertSee('El enlace anterior de esta persona dejará de funcionar')
        ->press('Generar enlace')
        ->assertSee('Enlace nuevo')
        ->assertScript('() => document.querySelector("#portal-link").value.includes("/portal/")', true)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

/** @return array{string, Tenant, ChecklistQuestion, ChecklistQuestion} The address of a Section I checklist with two questions, a flagged answer and a health one. */
function checklistAddress(): array
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Ruiz']);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);
    $checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $tenant->id, 'work_center_id' => $teleworker->work_center_id]);
    $light = addQuestion($checklist, 1, 'yes');
    $health = addQuestion($checklist, 2, 'yes', sensitive: true);
    $application = ChecklistApplicationFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id, 'checklist_id' => $checklist->id]);
    workIn($tenant);

    return ['/portal/'.resolve(IssuePortalLink::class)->handle($employee->id).'/checklists/'.$application->id, $tenant, $light, $health];
}

it('shows the person the checklist with its instructions, the notice and the consent', function (bool $dark, bool $mobile): void {
    [$address] = checklistAddress();

    visitPage('telework/portal-checklist', $address, $dark, $mobile)
        ->assertSee('Lista de verificación de su lugar de trabajo')
        ->assertSee('Consentimiento')
        ->assertSee('Pregunta 1')
        ->assertDontSee('Pregunta 2')
        ->assertSee('Enviar mi lista');
})->with($looks);

it('tells a checklist link that opens nothing apart from nothing else', function (bool $dark, bool $mobile): void {
    visitPage('telework/portal-checklist', '/portal/not-a-link/checklists/'.Str::uuid(), $dark, $mobile)
        ->assertSee('Este enlace no es válido');
})->with($looks);

it('lets the person answer on their phone with a flagged answer, pick a photo and send it once', function (): void {
    [$address, $tenant, $light, $health] = checklistAddress();

    visit($address)->on()->mobile()
        ->click('Acepto')
        ->assertSee('Pregunta 2')
        ->click("input[name=\"answer-{$light->id}\"][value=\"no\"]")
        ->assertSee('Describa el riesgo que encontró')
        ->assertSee('Agregar foto')
        ->attach("[data-testid=\"photo-{$light->id}\"]", __DIR__.'/../Fixtures/photo-with-gps.jpg')
        ->assertSee('photo-with-gps.jpg')
        // Pest's test server turns every non-UTF-8 byte of an upload into "?", so a real image cannot reach PHP through it:
        // the upload itself (re-encoding, EXIF) is covered by PortalChecklistTest; here the picker is used and emptied.
        ->click('[aria-label="Quitar photo-with-gps.jpg"]')
        ->assertDontSee('photo-with-gps.jpg')
        ->assertSee('Fotos del lugar (opcional)')
        ->attach('[data-testid="photo-general"]', __DIR__.'/../Fixtures/photo.png')
        ->assertSee('photo.png')
        ->click('[aria-label="Quitar photo.png"]')
        ->assertDontSee('photo.png')
        ->press('Enviar mi lista')
        ->assertSee('Describa el riesgo')
        ->fill("#note-{$light->id}", 'La luz es muy poca')
        ->click("input[name=\"answer-{$health->id}\"][value=\"yes\"]")
        ->press('Enviar mi lista')
        ->assertSee('En revisión de la Comisión')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    workIn($tenant);
    expect(ChecklistApplication::query()->whereNotNull('submitted_at')->count())->toBe(1)
        ->and(ChecklistPhoto::query()->count())->toBe(0)
        ->and(ChecklistAnswer::query()->where('question_id', $light->id)->sole()->risk_note)->toBe('La luz es muy poca');
});

/** @return array{string, ChecklistApplication, Tenant} The panel of a Comisión member and a submitted checklist of their center with one flagged answer. */
function reviewAddress(): array
{
    [$url, $center] = checklistCenter();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'job_title' => 'Analista']);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);
    $checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'version' => 2]);
    $risk = addQuestion($checklist, 1, 'yes');
    addQuestion($checklist, 2, 'informative');
    $application = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id, 'checklist_id' => $checklist->id]);
    ChecklistAnswer::query()->create(['tenant_id' => $tenant->id, 'application_id' => $application->id, 'question_id' => $risk->id, 'answer' => 'no', 'risk_note' => 'La luz es muy poca']);

    return ["{$url}/telework/centers/{$center->id}/reviews", $application, $tenant];
}

it('shows the Comisión its inbox of checklists to review', function (bool $dark, bool $mobile): void {
    [$inbox] = reviewAddress();

    visitPage('telework/reviews', $inbox, $dark, $mobile)
        ->assertSee('Listas por revisar')
        ->assertSee('Ana Ruiz')
        ->assertSee('Calle Hidalgo 45');
})->with($looks);

it('shows the Comisión the checklist to review with its flagged answers first', function (bool $dark, bool $mobile): void {
    [, $application] = reviewAddress();

    visitPage('telework/review', "/admin/acme/telework/applications/{$application->id}/review", $dark, $mobile)
        ->assertSee('Con riesgo')
        ->assertSee('La luz es muy poca')
        ->assertSee('Informativas')
        ->assertSee('Tu decisión')
        ->assertSee('Pedir fotos');
})->with($looks);

it('lets the Comisión decide a checklist and leaves the inbox empty', function (): void {
    [$inbox, $application, $tenant] = reviewAddress();

    visit("/admin/acme/telework/applications/{$application->id}/review")
        ->click('No validado')
        ->fill('#decision-notes', 'Falta luz en el escritorio.')
        ->press('Registrar decisión')
        ->assertSee('Decisión registrada')
        ->assertSee('Todo al día')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    workIn($tenant);
    expect(WorkplaceReview::query()->sole()->decision->value)->toBe('not_valid');
});

it('tells the Comisión a remote check cannot validate a place whose person has not answered a new list', function (): void {
    [, $application, $tenant] = reviewAddress();
    workIn($tenant);
    WorkplaceReviewFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $application->workplace_id, 'application_id' => $application->id, 'method' => 'checklist']);

    visit("/admin/acme/telework/applications/{$application->id}/review")
        ->press('Registrar comprobación a distancia')
        ->press('Registrar comprobación')
        ->assertSee('Antes de validar, la persona debe contestar la lista de este lugar.')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    workIn($tenant);
    expect(WorkplaceReview::query()->count())->toBe(1);
});

it('lets the Comisión ask the person for photos from the review', function (): void {
    [, $application, $tenant] = reviewAddress();

    visit("/admin/acme/telework/applications/{$application->id}/review")
        ->press('Pedir fotos')
        ->fill('#ask-photos-note', 'Una foto del escritorio.')
        ->press('Pedir')
        ->assertSee('Solicitud registrada')
        ->assertSee('Evidencia pedida')
        ->assertSee('Una foto del escritorio.')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    workIn($tenant);
    expect(EvidenceRequest::query()->count())->toBe(1);
});

it('lets the Comisión cancel a visit request nobody answered', function (): void {
    [, $application, $tenant] = reviewAddress();
    workIn($tenant);
    VisitAuthorizationFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $application->workplace_id]);

    visit("/admin/acme/telework/applications/{$application->id}/review")
        ->assertSee('Esperando a la persona')
        ->press('Cancelar solicitud')
        ->assertSee('¿Cancelar la solicitud de visita?')
        ->click('[role="dialog"] [data-slot="dialog-footer"] button:last-child')
        ->assertSee('Solicitud de visita cancelada')
        ->assertSee('Cancelada')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

/** @return array{string, Tenant, App\Modules\Telework\Models\Teleworker} An aspirant of acme with a validated workplace in force. */
function validatedAspirant(): array
{
    [$url, $center, $tenant] = teleworkerCenter();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'job_title' => 'Analista de soporte']);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);
    WorkplaceReviewFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id]);

    return [$url, $tenant, $teleworker];
}

it('shows the derived status of each workplace and the way into the roster', function (bool $dark, bool $mobile): void {
    [$url, , $teleworker] = validatedAspirant();

    visitPage('telework/teleworker', "{$url}/telework/teleworkers/{$teleworker->id}", $dark, $mobile)
        ->assertSee('Validado')
        ->assertSee('Vence el')
        ->assertSee('Aún no está en el padrón')
        ->assertSee('Pasar al padrón');
})->with($looks);

it('tells an aspirant without a validated workplace that they cannot join yet', function (): void {
    [$url, $center, $tenant] = teleworkerCenter();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);
    WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);

    visitPage('telework/teleworker', "{$url}/telework/teleworkers/{$teleworker->id}")
        ->assertSee('Pendiente de validar')
        ->assertSee('Necesita al menos un lugar validado y vigente')
        ->assertScript('[...document.querySelectorAll("button")].find((b) => b.textContent.includes("Pasar al padrón")).disabled', true);
});

it('moves an aspirant into the roster and then ends their telework', function (): void {
    [$url, , $teleworker] = validatedAspirant();

    visit("{$url}/telework/teleworkers/{$teleworker->id}")
        ->press('Pasar al padrón')
        ->assertValue('#roster-job-title', 'Analista de soporte')
        ->click('#roster-marital')
        ->type('[role="combobox"][aria-controls="roster-marital-options"]', 'solt')
        ->keys('[role="combobox"][aria-controls="roster-marital-options"]', 'Enter')
        ->fill('#roster-activities', 'Atención de clientes.')
        ->fill('#roster-phone', '4421234567')
        ->fill('#workplace-new-street', 'Calle Reforma')
        ->fill('#workplace-new-exterior_number', '12')
        ->fill('#workplace-new-neighborhood', 'Centro')
        ->fill('#workplace-new-postal_code', '76000')
        ->fill('#workplace-new-municipality', 'Querétaro')
        ->click('#workplace-new-state')
        ->type('[role="combobox"][aria-controls="workplace-new-state-options"]', 'quer')
        ->keys('[role="combobox"][aria-controls="workplace-new-state-options"]', 'Enter')
        ->fill('#roster-no_equipment_reason', 'Usa su propio equipo.')
        ->press('Guardar y pasar al padrón')
        ->assertSee('Persona en el padrón de teletrabajo')
        ->assertSee('En el padrón de teletrabajo')
        ->press('Terminar teletrabajo')
        ->assertSee('¿Terminar el teletrabajo de Ana Ruiz?')
        ->click('Otro motivo')
        ->fill('#end-note', 'Prueba.')
        ->press('Confirmar y terminar')
        ->assertSee('Teletrabajo terminado')
        ->assertSee('Otro motivo')
        ->assertSee('Prueba.')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('shows an ended period as history, without the actions that no longer work', function (): void {
    [$url, $center, $tenant] = teleworkerCenter();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    $teleworker = TeleworkerFactory::new()->ended()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'end_note' => 'Regresó a la oficina.']);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);
    WorkplaceReviewFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id]);

    visitPage('telework/teleworker', "{$url}/telework/teleworkers/{$teleworker->id}")
        ->assertSee('Teletrabajo terminado')
        ->assertSee('Regresó a la oficina.')
        ->assertDontSee('Vence el')
        ->assertDontSee('Agregar lugar')
        ->assertDontSee('Enlace a su portal');
});

it('shows the NOM-037 documents with what each version covers and leaves out', function (bool $dark, bool $mobile): void {
    [$url, $center] = committeeCenter();
    TeleworkDocument::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'kind' => DocumentKind::Policy, 'version' => 2, 'covered_items' => ['a', 'b']]);

    visitPage('telework/documents', "{$url}/telework/documents", $dark, $mobile)
        ->assertSee('Documentos de NOM-037')
        ->assertSee('Paso 1 · Tipo de documento')
        ->assertSee('Política de Teletrabajo · versión 2')
        ->assertSee('Planta Querétaro')
        ->assertSee('2 de 12 incisos')
        ->assertSee('Incisos que faltan')
        ->assertSee('c) Comunicación y difusión a distancia de los procedimientos');
})->with($looks);

it('publishes a document from the page, in steps, to the chosen center', function (): void {
    [$url, $center] = committeeCenter();

    visit("{$url}/telework/documents")
        ->click('input[name="kind"][value="policy"]')
        ->attach('document', __DIR__.'/../Fixtures/policy.pdf')
        ->click('Todos los centros de trabajo')
        ->click('#document-item-a')
        ->click('#document-item-l')
        ->press('Publicar documento')
        ->assertSee('Documento publicado.')
        ->assertSee('Política de Teletrabajo · versión 1')
        ->assertSee('2 de 12 incisos')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    workIn(Tenant::query()->where('slug', 'acme')->firstOrFail());
    expect(TeleworkDocument::query()->sole()->work_center_id)->toBe($center->id);
});

it('asks for the missing steps when publishing', function (): void {
    [$url] = committeeCenter();

    visit("{$url}/telework/documents")
        ->press('Publicar documento')
        ->assertSee('El campo tipo de documento es obligatorio.')
        ->assertSee('El campo documento es obligatorio.')
        ->assertSee('Elige al menos un centro de trabajo.');
});

it('lets a committee user filter the documents by its own centers only', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $cora = memberOf($tenant, TenantRole::Committee);
    $mine = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Propia']);
    WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Ajena']);
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $mine->id, 'user_id' => $cora->id]);
    TeleworkDocument::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $mine->id, 'kind' => DocumentKind::Policy]);
    test()->actingAs($cora);

    visit('/admin/acme/telework/documents')
        ->assertSee('Política de Teletrabajo · versión 1')
        ->click('#filter-document-center')
        ->assertSee('Planta Propia')
        ->assertDontSee('Planta Ajena')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

/** @return array{string, TeleworkDocument} The panel of RH and a policy of a center whose three people were asked to confirm it. */
function acknowledgementsDocument(): array
{
    [$url, $center] = committeeCenter();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    foreach (['Ana Ruiz', 'Beto Soto', 'Carla Díaz'] as $name) {
        EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => $name]);
    }
    $document = TeleworkDocument::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'kind' => DocumentKind::Policy, 'created_at' => now()->subDays(10)]);
    workIn($tenant);
    resolve(RequestAcknowledgements::class)->handle([$document->id]);

    return [$url, $document];
}

it('shows who confirmed a document and who is pending', function (bool $dark, bool $mobile): void {
    [$url, $document] = acknowledgementsDocument();

    visitPage('telework/acknowledgements', "{$url}/telework/documents/{$document->id}/acknowledgements", $dark, $mobile)
        ->assertSee('Política de Teletrabajo · versión 1')
        ->assertSee('0 de 3 (0 %)')
        ->assertSee('Ana Ruiz')
        ->assertSee('Carla Díaz')
        ->assertSee('Pendientes')
        ->assertSee('Registrar acuses en papel');
})->with($looks);

it('records paper acknowledgements of the people ticked, from the page', function (): void {
    [$url, $document] = acknowledgementsDocument();

    visit("{$url}/telework/documents/{$document->id}/acknowledgements")
        ->click('[aria-label="Elegir a Ana Ruiz"]')
        ->click('[aria-label="Elegir a Beto Soto"]')
        ->click('button:has-text("Registrar acuses en papel")')
        // The date starts on today as the server counts it, whatever the browser's clock says.
        ->assertSeeIn('#ack-date', CarbonImmutable::now(config()->string('app.display_timezone'))->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))
        ->fill('#ack-note', 'Hoja de firmas 3')
        ->press('Registrar')
        ->assertSee('Se registraron 2 acuses.')
        ->assertSee('2 de 3 (67 %)')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('records on paper everyone pending the search found, without listing them', function (): void {
    [$url, $document] = acknowledgementsDocument();

    visit("{$url}/telework/documents/{$document->id}/acknowledgements?status=pending")
        ->click('button:has-text("Elegir a las 3 personas pendientes")')
        ->click('button:has-text("Registrar acuses en papel")')
        ->fill('#ack-note', 'Hoja de firmas 4')
        ->press('Registrar')
        ->assertSee('Se registraron 3 acuses.')
        ->assertSee('3 de 3 (100 %)')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('lets the person read and confirm a document from their portal', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $center = WorkCenter::factory()->for($tenant)->create();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    $document = TeleworkDocument::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'kind' => DocumentKind::Policy]);
    workIn($tenant);
    resolve(RequestAcknowledgements::class)->handle([$document->id]);
    $address = '/portal/'.resolve(IssuePortalLink::class)->handle($employee->id);

    visitPage('telework/portal', $address, $dark, $mobile)
        ->assertSee('Documentos por confirmar')
        ->assertSee('Política de Teletrabajo · versión 1')
        ->assertSee('Ver el documento')
        ->press('Confirmo que lo leí')
        ->assertSee('Nada pendiente por ahora');

    expect(Acknowledgement::query()->sole()->acknowledged_at)->not->toBeNull();
})->with($looks);

it('shows the person the equipment waiting for their answer', function (bool $dark, bool $mobile): void {
    $address = portalAddress();
    $tenant = Tenant::query()->where('trade_name', 'Acme')->firstOrFail();
    EquipmentItemFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => App\Modules\Telework\Models\Teleworker::query()->withoutGlobalScopes()->firstOrFail()->id, 'description' => 'Silla con soporte lumbar']);

    visitPage('telework/portal', $address, $dark, $mobile)
        ->assertSee('Equipo por confirmar')
        ->assertSee('Silla con soporte lumbar')
        ->assertSee('Lo recibí')
        ->assertSee('No lo acepto');
})->with($looks);

it('lets RH register equipment and the person confirm one item and refuse another', function (): void {
    [$url, $teleworker, $tenant] = [...(function (): array {
        [$url, $tenant, $teleworker] = validatedAspirant();

        return [$url, $teleworker, $tenant];
    })()];

    visitPage('telework/teleworker', "{$url}/telework/teleworkers/{$teleworker->id}")
        ->assertSee('Equipo entregado')
        ->assertSee('Aún no hay equipo registrado')
        ->press('Registrar equipo')
        ->click('#equipment-kind')
        ->type('[role="combobox"][aria-controls="equipment-kind-options"]', 'silla')
        ->keys('[role="combobox"][aria-controls="equipment-kind-options"]', 'Enter')
        ->fill('#equipment-description', 'Silla con soporte lumbar')
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSee('Equipo registrado')
        ->assertSee('Silla con soporte lumbar')
        ->assertSee('Por confirmar');

    EquipmentItemFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id, 'description' => 'Monitor de 24 pulgadas', 'kind' => 'computer']);
    workIn($tenant);
    $portal = '/portal/'.resolve(IssuePortalLink::class)->handle($teleworker->employee_id);

    visit($portal)
        ->assertSee('Equipo por confirmar')
        ->assertSee('Monitor de 24 pulgadas')
        ->click('Lo recibí')
        ->assertDontSee('Monitor de 24 pulgadas')
        ->assertSee('Silla con soporte lumbar')
        ->click('No lo acepto')
        ->press('Enviar mi negativa')
        ->assertSee('Escriba el motivo')
        ->fill('textarea[id^="reason-"]', 'No cabe en mi espacio.')
        ->press('Enviar mi negativa')
        ->assertSee('Nada pendiente por ahora')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    visit("{$url}/telework/teleworkers/{$teleworker->id}")
        ->assertSee('Recibido')
        ->assertSee('Rechazado')
        ->assertSee('Motivo: No cabe en mi espacio.');

    expect(EquipmentItem::query()->count())->toBe(2);
});

it('shows the trainings with who is due and the topics not covered', function (bool $dark, bool $mobile): void {
    [$url, $center, $tenant] = teleworkerCenter();
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'job_title' => 'Analista']);
    TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'started_at' => now()->subYear()->subDays(10)]);
    $training = TrainingFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'held_on' => now()->subMonths(2)->toDateString(), 'topics' => ['hazards', 'ict']]);
    TrainingAttendee::query()->create(['tenant_id' => $tenant->id, 'training_id' => $training->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id])->id]);

    visitPage('telework/trainings', "{$url}/telework/trainings?center={$center->id}", $dark, $mobile)
        ->assertSee('Capacitación de NOM-037')
        ->assertSee('Quién toca capacitar')
        ->assertSee('Ana Ruiz')
        ->assertSee('Vencida')
        ->assertSee('Temas del 8.1 sin cubrir')
        ->assertSee('Riesgo ergonómico por posturas forzosas')
        ->assertSee('Capacitaciones registradas')
        ->assertSee('1 persona');
})->with($looks);

it('shows the trainings page before there is anything to show', function (): void {
    $url = signInToPanel();

    visitPage('telework/trainings', "{$url}/telework/trainings")
        ->assertSee('Aún no hay personas en el padrón')
        ->assertSee('Aún no hay capacitaciones')
        ->assertSee('Elige un centro de trabajo para ver');
});

it('registers a training from the page', function (): void {
    [$url, $center, $tenant] = teleworkerCenter();
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);

    visit("{$url}/telework/trainings")
        ->press('Registrar capacitación')
        ->click('#training-center')
        ->type('[role="combobox"][aria-controls="training-center-options"]', 'Querétaro')
        ->click('[role="option"]')
        ->click('Remota')
        ->click('#training-topic-hazards')
        ->click('#training-topic-ict')
        ->click('#training-attendee')
        ->type('[role="combobox"][aria-controls="training-attendee-options"]', 'Ana')
        ->click('[role="option"]')
        ->assertScript('document.querySelectorAll(\'input[name="employee_ids[]"]\').length', 1)
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSee('Capacitación registrada')
        ->assertSee('1 persona')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(Training::query()->sole()->topics)->toHaveCount(2)
        ->and(TrainingAttendee::query()->count())->toBe(1);
});

it('shows the person their three written notices', function (bool $dark, bool $mobile): void {
    visitPage('telework/portal-notices', portalAddress().'/notices', $dark, $mobile)
        ->assertSee('Sus avisos por escrito')
        ->assertSee('Alteración de condiciones o fuerza mayor')
        ->assertSee('Cambio de domicilio')
        ->assertSee('Riesgo de trabajo');
})->with($looks);

it('lets the person write a work risk notice, and shows it received', function (): void {
    $address = portalAddress().'/notices';

    visit($address)
        ->click('ul > li:nth-child(3) button')
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSee('descripción es obligatorio')
        ->fill('#work_risk-description', 'Me caí al levantarme de la silla.')
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSee('Avisos enviados')
        ->assertSee('Recibido')
        ->assertDontSee('Me caí al levantarme')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(App\Modules\Telework\Models\WorkerNotice::query()->withoutGlobalScopes()->sole()->kind->value)->toBe('work_risk');
});

function noticesCenter(): array
{
    [$url, $center, $tenant] = teleworkerCenter();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'job_title' => 'Analista']);
    $teleworker = TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id, 'kind' => 'condition_change', 'traumatic_event' => true, 'description' => 'Presencié un asalto camino a casa.']);
    WorkerNoticeFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id, 'description' => 'Me lastimé la espalda.', 'reviewed_by' => User::factory()->create()->id, 'reviewed_at' => now(), 'review_note' => 'Se envió a revisión médica.']);

    return [$url, $teleworker];
}

it('shows the notices to review, the traumatic event with its way into Guía I', function (bool $dark, bool $mobile): void {
    [$url] = noticesCenter();

    visitPage('telework/notices', "{$url}/telework/notices", $dark, $mobile)
        ->assertSee('Avisos de teletrabajo')
        ->assertSee('Presencié un asalto camino a casa.')
        ->assertSee('Acontecimiento traumático severo')
        ->assertSee('Lanzar Guía I')
        ->assertSee('Se envió a revisión médica.')
        ->assertSee('Por revisar');
})->with($looks);

it('shows the notices page before there is any notice', function (): void {
    $url = signInToPanel();

    visitPage('telework/notices', "{$url}/telework/notices")->assertSee('Aún no hay avisos');
});

it('lets RH review a notice with a note and goes on to Guía I with the person ticked', function (): void {
    [$url, $teleworker] = noticesCenter();

    visit("{$url}/telework/notices")
        ->press('Revisar')
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSee('nota es obligatorio')
        ->fill('[role="dialog"] textarea', 'Se dará seguimiento con la Comisión.')
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSee('Se dará seguimiento con la Comisión.')
        ->assertSee('Revisión registrada')
        ->click('Lanzar Guía I')
        ->assertSee('(1 elegidas)')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    visit("{$url}/telework/teleworkers/{$teleworker->id}")
        ->assertSee('Avisos de la persona')
        ->assertSee('Presencié un asalto camino a casa.');
});

it('lists the centers with their progress on the NOM-037 page', function (bool $dark, bool $mobile): void {
    [$url, $center, $tenant] = teleworkerCenter();
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id]);
    WorkplaceReviewFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $person->id])->id]);

    visitPage('telework/index', "{$url}/telework", $dark, $mobile)
        ->assertSee('NOM-037')
        ->assertSee('Planta Querétaro')
        ->assertSee('1 persona')
        ->assertSee('renglones')
        ->assertSee('Ver tablero');
})->with($looks);

it('shows the dashboard of a center with the rows of table 10.3 and its roster', function (bool $dark, bool $mobile): void {
    [$url, $center, $tenant] = teleworkerCenter();
    SafetyCommittee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'job_title' => 'Analista']);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id]);
    WorkplaceReviewFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $person->id])->id]);

    visitPage('telework/center', "{$url}/telework/centers/{$center->id}", $dark, $mobile)
        ->assertSee('Planta Querétaro')
        ->assertSee('Tabla de inspección (10.3)')
        ->assertSee('Listado de personas teletrabajadoras')
        ->assertSee('Política de Teletrabajo')
        ->assertSee('Ana Ruiz')
        ->assertSee('Validado')
        ->assertSee('Padrón');
})->with($looks);

it('shows the next step on a center that has no Comisión yet', function (): void {
    [$url, $center] = teleworkerCenter(published: false);

    visitPage('telework/center', "{$url}/telework/centers/{$center->id}")
        ->assertSee('Registra la Comisión')
        ->assertSee('Aún no hay personas')
        ->assertNoJavaScriptErrors();
});

it('shows the printable dossier of a center with the evidence of each row or Falta', function (bool $dark, bool $mobile): void {
    [$url, $center, $tenant] = teleworkerCenter();
    SafetyCommittee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'job_title' => 'Analista']);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id]);
    WorkplaceReviewFactory::new()->create(['tenant_id' => $tenant->id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $person->id])->id]);

    visitPage('telework/dossier', "{$url}/telework/centers/{$center->id}/dossier", $dark, $mobile)
        ->assertSee('Expediente de NOM-037 para la inspección')
        ->assertSee('Planta Querétaro')
        ->assertSee('Ana Ruiz')
        ->assertSee('Falta')
        ->assertSee('Imprimir o guardar PDF')
        // A section that is done never says «Falta», and 5.1.2 lists the validated place.
        ->assertScript('() => JSON.stringify([...document.querySelectorAll("section")].filter((section) => section.textContent.includes("Cumple") && section.textContent.includes("Falta")).map((section) => section.getAttribute("aria-label")))', '[]')
        ->assertScript('() => document.querySelector(\'section[aria-label="Numeral 5.1.2"]\').textContent.includes("Ana Ruiz")', true)
        ->assertScript('() => [...document.querySelectorAll("section")].filter((section) => section.textContent.includes("Cumple")).length > 2', true);
})->with($looks);

it('says that a row does not apply instead of Falta, and prints the medical exams note once', function (): void {
    [$url, $center, $tenant] = teleworkerCenter();
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'no_equipment_reason' => 'Usa su propio equipo.']);
    $empty = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Vacía']);

    visit("{$url}/telework/centers/{$empty->id}/dossier")
        ->assertSee('No aplica: nadie teletrabaja en este centro todavía.')
        ->assertSee('No aplica: nadie ha enviado un aviso de riesgo de trabajo.')
        ->assertScript('() => document.body.textContent.split("no los cubre Humetría").length - 1', 1);

    visit("{$url}/telework/centers/{$center->id}/dossier")
        ->assertSee('Sin equipo, justificado')
        ->assertNoJavaScriptErrors();
});

it('opens the dossier from the center dashboard', function (): void {
    [$url, $center] = teleworkerCenter();

    visit("{$url}/telework/centers/{$center->id}")
        ->click('Expediente')
        ->assertSee('Expediente de NOM-037 para la inspección')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('downloads the constitution act from the Comisión page', function (): void {
    Storage::fake(config()->string('filesystems.telework_files'));
    [$url, $center, $tenant] = teleworkerCenter();
    $path = "committees/{$tenant->id}/{$center->id}/act-browser.pdf";
    Storage::disk(config()->string('filesystems.telework_files'))->put($path, "%PDF-1.4\n%%EOF\n");
    SafetyCommittee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'act_path' => $path]);

    visit("{$url}/telework/centers/{$center->id}/committee")
        ->assertSee('Ver el acta de constitución (PDF)')
        ->assertScript('() => fetch(document.querySelector(\'a[href$="/committee/act"]\').href).then((response) => response.status + " " + response.headers.get("content-type") + " " + response.headers.get("cache-control"))', '200 application/pdf no-store, private')
        ->assertNoJavaScriptErrors();
});
