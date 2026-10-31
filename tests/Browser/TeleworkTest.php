<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\PublishChecklist;
use App\Modules\Telework\Actions\RegisterCommittee;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\EvidenceRequestFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\VisitAuthorizationFactory;
use Database\Factories\Telework\WorkplaceFactory;
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
        ->assertSee('1 integrante');
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
